<?php

/**
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  PAYMENT FLOW — Recebimento e envio de cripto                          │
 * │                                                                         │
 * │  Passos:                                                                │
 * │    1. Criar mnemônica mestre e derivar carteiras                        │
 * │    2. Simular depósito externo do usuário                               │
 * │    3. Verificar transações recebidas                                     │
 * │    4. Confirmar depósito (N blocos de confirmação)                      │
 * │    5. Calcular gas necessário para o saque                              │
 * │    6. Top-up de gas se a carteira não tiver o suficiente                │
 * │    7. Varrer USDT da carteira de depósito → hot wallet (sweep)          │
 * │    8. Saque: hot wallet → carteira externa do usuário                   │
 * │                                                                         │
 * │  Instalação:                                                            │
 * │    composer require web3php/web3php                                     │
 * │                                                                         │
 * │  Modo teste  : USE_FAKECHAIN = true  (zero config, roda offline)       │
 * │  Modo real   : USE_FAKECHAIN = false (configure as constantes abaixo)  │
 * └─────────────────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);

use Web3PHP\MnemonicWallet;
use Web3PHP\Web3PHP;
use FakeChain\FakeChainHD;

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURAÇÃO
// ─────────────────────────────────────────────────────────────────────────────

const USE_FAKECHAIN = true;   // ← mude para false para usar rede real

// Rede real — preencha quando USE_FAKECHAIN = false
// Polygon é ideal para pagamentos: gas barato (~0.001 USD por TX), USDT nativo
const RPC_NETWORK      = 'polygon';
const RPC_PROVIDER     = 'alchemy';
const RPC_API_KEY      = 'SUA_ALCHEMY_KEY';
const EXPLORER_API_KEY = 'SUA_POLYGONSCAN_KEY';

// Contratos na Polygon mainnet
const USDT_ADDRESS  = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
const TOKEN_DECIMALS = 6;

// Sua mnemônica mestre — gere UMA VEZ e guarde com segurança
// Nunca gere uma nova: você perderia o acesso a todas as carteiras derivadas
const MASTER_MNEMONIC = 'word1 word2 word3 word4 word5 word6 word7 word8 word9 word10 word11 word12';

// Índices fixos para carteiras especiais
const HOT_WALLET_IDX = 0;     // carteira quente da empresa
const FIRST_USER_IDX = 1000;  // usuários a partir do índice 1000

// Quantos blocos aguardar antes de considerar depósito confirmado
// Polygon: 20 blocos ≈ 40 segundos. Ethereum: 12 blocos ≈ 2.5 min
const MIN_CONFIRMATIONS = 20;


// ─────────────────────────────────────────────────────────────────────────────
// HELPERS DE OUTPUT
// ─────────────────────────────────────────────────────────────────────────────

function titulo(string $passo, string $titulo): void {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║  {$passo}  {$titulo}" . str_repeat(' ', max(0, 55 - strlen($passo) - strlen($titulo))) . "║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
}

function ok(string $msg):   void { echo "  ✅  {$msg}\n"; }
function info(string $k, string $v): void { echo "  " . str_pad($k . ":", 26) . $v . "\n"; }
function aviso(string $msg): void { echo "  ⚠️   {$msg}\n"; }
function erro(string $msg):  void { echo "  ❌  {$msg}\n"; }
function linha(): void { echo "  " . str_repeat("─", 60) . "\n"; }


// ─────────────────────────────────────────────────────────────────────────────
// BOOTSTRAP — instanciar o chain (fake ou real)
// ─────────────────────────────────────────────────────────────────────────────

if (USE_FAKECHAIN) {
    $chain = new FakeChainHD([
        'network'   => 'polygon',
        'chain_id'  => 137,
        'symbol'    => 'MATIC',
        'gas_price' => 0.000000030,   // 30 Gwei
        'auto_mine' => true,
    ]);

    // Deploy do USDT fake para os testes
    $systemWallet = $chain->createWallet('_system', 10000.0);
    $USDT = $chain->deployERC20('Tether USD', 'USDT', TOKEN_DECIMALS, 50_000_000.0, $systemWallet['address']);

    // Simulação de um usuário externo que vai depositar
    $externalUser = $chain->createWallet('UsuarioExterno', 5.0);
    $chain->sendTokenTransfer($systemWallet['address'], $USDT, $externalUser['address'], 1000.0, $systemWallet['private_key'], TOKEN_DECIMALS);

} else {
    $chain = new Web3PHP([
        'network'  => RPC_NETWORK,
        'provider' => RPC_PROVIDER,
        'api_key'  => RPC_API_KEY,
    ]);
    $USDT = USDT_ADDRESS;
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 1 — Mnemônica mestre e derivação de carteiras
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 1", "Mnemônica e derivação de carteiras");

$mn = new MnemonicWallet();

// Em produção: use MASTER_MNEMONIC (constante acima, nunca regenere)
// No FakeChain: geramos uma nova só para a demo
$mnemonic = USE_FAKECHAIN ? $mn->generate(24) : MASTER_MNEMONIC;

info("Mnemônica", substr($mnemonic, 0, 48) . "...");
echo "\n";

// Carteira quente da empresa — recebe todos os fundos varridos
$hotWallet = $mn->deriveWallet($mnemonic, index: HOT_WALLET_IDX, network: 'polygon');
info("Hot Wallet address", $hotWallet['checksum_address']);
info("Hot Wallet path",    $hotWallet['path']);

echo "\n";

// Cada usuário recebe uma carteira de depósito única
// Estratégia: user_id como índice BIP-44 → sempre recuperável pela mnemônica
$userId        = 1042;
$depositWallet = $mn->deriveWallet($mnemonic, index: $userId, network: 'polygon');

info("User #{$userId} deposit addr", $depositWallet['checksum_address']);
info("User #{$userId} path",         $depositWallet['path']);

echo "\n";

// Gerar carteiras para múltiplos usuários de uma vez (onboarding em batch)
$batchSize    = 5;
$batchWallets = $mn->deriveMultiple($mnemonic, count: $batchSize, startIndex: FIRST_USER_IDX, network: 'polygon');

ok("Batch de {$batchSize} carteiras derivadas (índices " . FIRST_USER_IDX . "–" . (FIRST_USER_IDX + $batchSize - 1) . "):");
foreach ($batchWallets as $w) {
    echo "       [{$w['index']}] {$w['checksum_address']}\n";
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 2 — Depósito: usuário externo envia USDT para a carteira de depósito
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 2", "Simular depósito externo");

$depositAmount = 250.0; // USDT que o usuário vai depositar

if (USE_FAKECHAIN) {
    // Registrar carteira de depósito no chain com um pouco de gas inicial
    $chain->faucet($depositWallet['address'], 0.005); // 0.005 MATIC de gas
    $chain->faucet($hotWallet['address'],     2.0);   // hot wallet tem bastante gas

    // Usuário externo envia 250 USDT para a carteira de depósito do usuário #1042
    $depositTxHash = $chain->sendTokenTransfer(
        from:            $externalUser['address'],
        contractAddress: $USDT,
        to:              $depositWallet['address'],
        amount:          $depositAmount,
        privateKey:      $externalUser['private_key'],
        decimals:        TOKEN_DECIMALS
    );

    ok("Depósito simulado");
    info("TX Hash",  $depositTxHash);
    info("Valor",    "{$depositAmount} USDT");
    info("Para",     $depositWallet['address']);

} else {
    // Em produção: este passo é feito pelo usuário na carteira dele
    // Aqui só exibimos o endereço que deve ser monitorado
    ok("Endereço de depósito gerado para o usuário #{$userId}:");
    info("Envie USDT para", $depositWallet['checksum_address']);
    aviso("Aguardando depósito externo...");
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 3 — Verificar transações recebidas
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 3", "Verificar transações recebidas");

// Histórico de transações nativas (MATIC)
$txHistory = USE_FAKECHAIN
    ? $chain->wallet->getTransactionHistory($depositWallet['address'])
    : $chain->wallet->getTransactionHistory($depositWallet['checksum_address'], EXPLORER_API_KEY);

// Transferências de token (USDT especificamente)
$tokenTxs = USE_FAKECHAIN
    ? $chain->wallet->getTokenTransfers($depositWallet['address'], contractAddress: $USDT)
    : $chain->wallet->getTokenTransfers($depositWallet['checksum_address'], EXPLORER_API_KEY, USDT_ADDRESS);

info("TXs nativas encontradas", (string)count($txHistory));
info("TXs USDT encontradas",    (string)count($tokenTxs));

echo "\n";

// Exibir detalhes das transações de token recebidas
$tokenTxsReceived = array_filter($tokenTxs, fn($tx) =>
    strtolower($tx['to'] ?? '') === strtolower($depositWallet['address'])
);

if (empty($tokenTxsReceived)) {
    aviso("Nenhuma transferência USDT recebida ainda.");
} else {
    ok(count($tokenTxsReceived) . " transferência(s) de USDT recebida(s):");
    linha();
    foreach (array_slice(array_values($tokenTxsReceived), 0, 5) as $i => $tx) {
        $n = $i + 1;
        info("  TX #{$n} hash",    substr($tx['hash'], 0, 30) . "...");
        info("  TX #{$n} de",      $tx['from']);
        info("  TX #{$n} valor",   ($tx['token_amount'] ?? $tx['value_eth'] ?? '?') . " USDT");
        info("  TX #{$n} bloco",   (string)($tx['block'] ?? '?'));
        info("  TX #{$n} status",  $tx['status'] ?? 'unknown');
        echo "\n";
    }
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 4 — Confirmar depósito (aguardar N blocos)
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 4", "Confirmar depósito");

$latestBlock = USE_FAKECHAIN
    ? $chain->block->getLatestBlockNumber()
    : $chain->block->getLatestBlockNumber();

info("Bloco atual da rede", (string)$latestBlock);

// Pegar a TX de USDT mais recente
$lastTokenTx        = array_values($tokenTxsReceived)[0] ?? null;
$depositConfirmado  = false;
$confirmedAmount    = '0';

if (!$lastTokenTx) {
    erro("Nenhum depósito encontrado. Encerrando.");
    exit(1);
}

$txBlock       = (int)($lastTokenTx['block'] ?? 0);
$confirmations = max(0, $latestBlock - $txBlock);

info("TX no bloco",     (string)$txBlock);
info("Confirmações",    "{$confirmations} / " . MIN_CONFIRMATIONS . " mínimo");

if ($confirmations >= MIN_CONFIRMATIONS) {
    $depositConfirmado = true;
    ok("Depósito CONFIRMADO com {$confirmations} confirmações");
} else {
    aviso("Aguardando confirmações ({$confirmations}/" . MIN_CONFIRMATIONS . ")");

    if (USE_FAKECHAIN) {
        // No FakeChain: minerar mais blocos para simular confirmações
        aviso("FakeChain: minerando blocos extras para simular confirmações...");
        for ($i = 0; $i < MIN_CONFIRMATIONS; $i++) {
            $chain->mineBlock();
        }
        $latestBlock   = $chain->block->getLatestBlockNumber();
        $confirmations = $latestBlock - $txBlock;
        $depositConfirmado = true;
        ok("Depósito CONFIRMADO após mineração forçada ({$confirmations} confirmações)");
    } else {
        erro("Depósito ainda não tem confirmações suficientes. Tente novamente em instantes.");
        exit(0);
    }
}

// Verificar saldo atual de USDT
$usdtBalance = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($depositWallet['address'], $USDT, TOKEN_DECIMALS)
    : $chain->wallet->getTokenBalance($depositWallet['checksum_address'], USDT_ADDRESS, TOKEN_DECIMALS);

$gasBalance = USE_FAKECHAIN
    ? $chain->wallet->getBalance($depositWallet['address'])
    : $chain->wallet->getBalance($depositWallet['checksum_address']);

echo "\n";
info("Saldo USDT na carteira", "{$usdtBalance} USDT");
info("Saldo MATIC (gas)",      "{$gasBalance} MATIC");

if ((float)$usdtBalance <= 0) {
    erro("Saldo USDT zero após confirmação. Encerrando.");
    exit(1);
}

$confirmedAmount = $usdtBalance;


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 5 — Calcular gas necessário para o sweep (token transfer)
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 5", "Calcular gas necessário");

// Gas info da rede
$gasInfo = USE_FAKECHAIN
    ? $chain->block->getGasInfo()
    : $chain->block->getGasInfo();

// Estimar gas para uma transferência ERC-20
// Passa um tx object realista para o estimador
$estimateTx = [
    'from' => $depositWallet['address'],
    'to'   => $USDT,
    'data' => '0xa9059cbb',  // selector de transfer(address,uint256)
];

$gasEstimated = USE_FAKECHAIN
    ? (int)$chain->block->estimateGas($estimateTx)
    : (int)$chain->block->estimateGas($estimateTx);

// Buffer de 20% para evitar underestimate
$gasLimit    = (int)ceil($gasEstimated * 1.2);
$gasPriceWei = $gasInfo['gas_price_wei'];

// Custo total = gasLimit × gasPrice (em wei → converter para MATIC)
$gasCostWei   = bcmul((string)$gasLimit, $gasPriceWei, 0);
$gasCostMatic = bcdiv($gasCostWei, bcpow('10', '18', 0), 8);

info("Gas estimado (ERC-20)",  "{$gasEstimated} units");
info("Gas limit (+ 20% buf)", "{$gasLimit} units");
info("Gas price",              "{$gasInfo['gas_price_gwei']} Gwei");
info("Custo total",            "{$gasCostMatic} MATIC");
info("Saldo gas disponível",   "{$gasBalance} MATIC");

echo "\n";

// Comparar saldo disponível com o necessário
$gasInsuficiente = bccomp($gasBalance, $gasCostMatic, 8) < 0;
$gasNecessario   = bcsub($gasCostMatic, $gasBalance, 8);

if ($gasInsuficiente) {
    aviso("Gas insuficiente! Faltam {$gasNecessario} MATIC");
} else {
    ok("Gas suficiente (sobra " . bcsub($gasBalance, $gasCostMatic, 8) . " MATIC)");
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 6 — Top-up de gas se necessário
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 6", "Gas top-up");

if (!$gasInsuficiente) {
    ok("Sem necessidade de top-up. Pulando.");

} else {

    // Enviar 2× o necessário para ter margem de segurança
    // (variações de gas price entre a estimativa e a execução)
    $topUpAmount = bcmul($gasCostMatic, '2.0', 8);

    info("Hot Wallet enviará",  "{$topUpAmount} MATIC");
    info("Para",                $depositWallet['address']);

    // Verificar se a hot wallet tem MATIC suficiente para o top-up
    $hotGasBalance = USE_FAKECHAIN
        ? $chain->wallet->getBalance($hotWallet['address'])
        : $chain->wallet->getBalance($hotWallet['checksum_address']);

    if (bccomp($hotGasBalance, $topUpAmount, 8) < 0) {
        erro("Hot wallet sem MATIC suficiente para top-up. Hot wallet: {$hotGasBalance} MATIC");
        exit(1);
    }

    // Enviar MATIC da hot wallet para a carteira de depósito
    if (USE_FAKECHAIN) {
        $topUpUnsigned = $chain->transfer->buildNativeTransfer(
            from:      $hotWallet['address'],
            to:        $depositWallet['address'],
            amount:    (float)$topUpAmount
        );
        $topUpTxHash = $chain->transfer->signAndSend($hotWallet['private_key'], $topUpUnsigned);
    } else {
        $topUpUnsigned = $chain->transfer->buildNativeTransfer(
            from:      $hotWallet['checksum_address'],
            to:        $depositWallet['checksum_address'],
            amount:    (float)$topUpAmount
        );
        $topUpTxHash = $chain->transfer->signAndSend($hotWallet['private_key'], $topUpUnsigned);
        $chain->transfer->waitForConfirmation($topUpTxHash);
    }

    ok("Top-up enviado: {$topUpTxHash}");

    $gasBalance = USE_FAKECHAIN
        ? $chain->wallet->getBalance($depositWallet['address'])
        : $chain->wallet->getBalance($depositWallet['checksum_address']);

    info("Novo saldo MATIC",  "{$gasBalance} MATIC");
    ok("Gas agora suficiente para o sweep.");
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 7 — Sweep: varrer USDT da carteira de depósito → hot wallet
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 7", "Sweep → Hot Wallet");

// Reler saldo atual (pode ter mudado)
$usdtParaVarrer = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($depositWallet['address'], $USDT, TOKEN_DECIMALS)
    : $chain->wallet->getTokenBalance($depositWallet['checksum_address'], USDT_ADDRESS, TOKEN_DECIMALS);

info("USDT a varrer",      "{$usdtParaVarrer} USDT");
info("De",                 $depositWallet['checksum_address']);
info("Para hot wallet",    $hotWallet['checksum_address']);

if ((float)$usdtParaVarrer <= 0) {
    erro("Nada a varrer. Saldo USDT zerou.");
    exit(1);
}

if (USE_FAKECHAIN) {
    $sweepUnsigned = $chain->transfer->buildTokenTransfer(
        from:            $depositWallet['address'],
        contractAddress: $USDT,
        to:              $hotWallet['address'],
        amount:          (float)$usdtParaVarrer,
        decimals:        TOKEN_DECIMALS,
        customGas:       $gasLimit
    );
    $sweepTxHash = $chain->transfer->signAndSend($depositWallet['private_key'], $sweepUnsigned);
    $sweepReceipt = $chain->transfer->waitForConfirmation($sweepTxHash);
} else {
    $sweepUnsigned = $chain->transfer->buildTokenTransfer(
        from:            $depositWallet['checksum_address'],
        contractAddress: USDT_ADDRESS,
        to:              $hotWallet['checksum_address'],
        amount:          (float)$usdtParaVarrer,
        decimals:        TOKEN_DECIMALS,
        customGas:       $gasLimit
    );
    $sweepTxHash  = $chain->transfer->signAndSend($depositWallet['private_key'], $sweepUnsigned);
    $sweepReceipt = $chain->transfer->waitForConfirmation($sweepTxHash);
}

ok("Sweep concluído!");
info("TX Hash",       $sweepTxHash);
info("Bloco",         (string)$sweepReceipt['block']);
info("Gas usado",     (string)$sweepReceipt['gas_used'] . " units");
info("Status",        $sweepReceipt['status']);

echo "\n";

// Confirmar saldos após sweep
$postSweepDepositUsdt = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($depositWallet['address'], $USDT, TOKEN_DECIMALS)
    : $chain->wallet->getTokenBalance($depositWallet['checksum_address'], USDT_ADDRESS, TOKEN_DECIMALS);

$postSweepHotUsdt = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($hotWallet['address'], $USDT, TOKEN_DECIMALS)
    : $chain->wallet->getTokenBalance($hotWallet['checksum_address'], USDT_ADDRESS, TOKEN_DECIMALS);

info("Carteira depósito USDT", "{$postSweepDepositUsdt} USDT  (esperado: 0)");
info("Hot Wallet USDT",        "{$postSweepHotUsdt} USDT");


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 8 — Saque: hot wallet envia USDT para a carteira externa do usuário
// ═════════════════════════════════════════════════════════════════════════════

titulo("PASSO 8", "Saque → Carteira externa");

// Em produção: este endereço vem do pedido de saque do usuário no seu sistema
$destinoSaque  = '0x742d35Cc6634C0532925a3b8D4C9B9D4d2f75AeB';
$valorSaque    = 200.0; // USDT

info("Destino",        $destinoSaque);
info("Valor",          "{$valorSaque} USDT");
info("Saldo hot wal.", "{$postSweepHotUsdt} USDT disponível");

echo "\n";

if ((float)$postSweepHotUsdt < $valorSaque) {
    erro("Hot wallet sem saldo suficiente para o saque ({$postSweepHotUsdt} USDT disponível).");
    exit(1);
}

if (USE_FAKECHAIN) {
    // Registrar destino no chain (necessário no FakeChain)
    $chain->faucet($destinoSaque, 0.0);

    $withdrawUnsigned = $chain->transfer->buildTokenTransfer(
        from:            $hotWallet['address'],
        contractAddress: $USDT,
        to:              $destinoSaque,
        amount:          $valorSaque,
        decimals:        TOKEN_DECIMALS
    );
    $withdrawTxHash  = $chain->transfer->signAndSend($hotWallet['private_key'], $withdrawUnsigned);
    $withdrawReceipt = $chain->transfer->waitForConfirmation($withdrawTxHash);
} else {
    $withdrawUnsigned = $chain->transfer->buildTokenTransfer(
        from:            $hotWallet['checksum_address'],
        contractAddress: USDT_ADDRESS,
        to:              $destinoSaque,
        amount:          $valorSaque,
        decimals:        TOKEN_DECIMALS
    );
    $withdrawTxHash  = $chain->transfer->signAndSend($hotWallet['private_key'], $withdrawUnsigned);
    $withdrawReceipt = $chain->transfer->waitForConfirmation($withdrawTxHash);
}

ok("Saque processado com sucesso!");
info("TX Hash",   $withdrawTxHash);
info("Bloco",     (string)$withdrawReceipt['block']);
info("Gas usado", (string)$withdrawReceipt['gas_used'] . " units");
info("Status",    $withdrawReceipt['status']);

echo "\n";

$finalHotUsdt   = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($hotWallet['address'], $USDT, TOKEN_DECIMALS)
    : $chain->wallet->getTokenBalance($hotWallet['checksum_address'], USDT_ADDRESS, TOKEN_DECIMALS);

$finalDestinoUsdt = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($destinoSaque, $USDT, TOKEN_DECIMALS)
    : $chain->wallet->getTokenBalance($destinoSaque, USDT_ADDRESS, TOKEN_DECIMALS);

info("Hot Wallet USDT restante", "{$finalHotUsdt} USDT");
info("Destino recebeu",          "{$finalDestinoUsdt} USDT");


// ═════════════════════════════════════════════════════════════════════════════
// RESUMO FINAL
// ═════════════════════════════════════════════════════════════════════════════

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMO DO FLUXO COMPLETO                                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$modo = USE_FAKECHAIN ? "FakeChain (offline)" : "Rede real (" . RPC_NETWORK . ")";
info("Modo de execução",    $modo);
info("Rede",                "Polygon (EVM, MATIC)");
info("Token",               "USDT (ERC-20, 6 decimais)");
info("Usuário atendido",    "#{$userId}");

echo "\n";
ok("Passo 1 — Mnemônica mestre + carteiras HD derivadas");
ok("Passo 2 — Depósito de {$depositAmount} USDT recebido");
ok("Passo 3 — Transações verificadas via histórico");
ok("Passo 4 — Depósito confirmado com " . MIN_CONFIRMATIONS . "+ blocos");
ok("Passo 5 — Gas calculado: {$gasCostMatic} MATIC por TX ERC-20");
ok("Passo 6 — " . ($gasInsuficiente ? "Top-up enviado da hot wallet" : "Sem necessidade de top-up"));
ok("Passo 7 — {$usdtParaVarrer} USDT varridos para hot wallet (sweep)");
ok("Passo 8 — Saque de {$valorSaque} USDT processado para o usuário");

echo "\n";
if (USE_FAKECHAIN) {
    echo "  ────────────────────────────────────────────────────────────\n";
    echo "  Para usar em produção:\n";
    echo "    1. Mude USE_FAKECHAIN para false\n";
    echo "    2. Preencha RPC_API_KEY e EXPLORER_API_KEY\n";
    echo "    3. Gere MASTER_MNEMONIC com: \$mn->generate(24)\n";
    echo "    4. Guarde a mnemônica com segurança (nunca no código)\n";
    echo "  ────────────────────────────────────────────────────────────\n";
}
echo "\n";
