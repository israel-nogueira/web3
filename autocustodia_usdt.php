<?php

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  Web3PHP — Auto-Custódia Completa com USDT (Polygon)                   ║
 * ║  Exemplo de ponta a ponta para quem acabou de clonar o repositório      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ANTES DE COMEÇAR:
 *   1. Clone o repositório:
 *        git clone https://github.com/web3php/web3php
 *        cd web3php
 *
 *   2. Instale as dependências:
 *        composer install
 *
 *   3. Rode este arquivo:
 *        php examples/autocustodia_usdt.php
 *
 * FLUXO IMPLEMENTADO:
 *   ① Configurar rede (Polygon + USDT)
 *   ② Gerar mnemônica mestre e armazenar com segurança
 *   ③ Derivar carteiras únicas por usuário (BIP-44 HD)
 *   ④ Usuário deposita USDT → verificar recebimento
 *   ⑤ Confirmar blocos suficientes
 *   ⑥ Calcular gas necessário para o sweep
 *   ⑦ Enviar MATIC de gas para a carteira de depósito (se necessário)
 *   ⑧ Varrer USDT para a hot wallet (carteira mãe)
 *   ⑨ Verificar transação e finalizar operação
 *
 * REDES / TOKENS:
 *   Rede  → Polygon (EVM, chainId 137, moeda nativa: MATIC)
 *   Token → USDT na Polygon (ERC-20, 6 decimais)
 *
 * ⚠️  Por padrão roda em modo SIMULADO (FakeChain).
 *      Para produção, mude USE_FAKECHAIN para false e preencha as constantes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Web3PHP\Web3PHP;
use Web3PHP\MnemonicWallet;
use Web3PHP\Math;

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURAÇÕES — ajuste aqui para produção
// ─────────────────────────────────────────────────────────────────────────────

const USE_FAKECHAIN    = true;   // false → usa rede real

// Provider (infura | alchemy | quicknode | public)
const RPC_PROVIDER     = 'alchemy';
const RPC_API_KEY      = 'SUA_ALCHEMY_KEY_AQUI';

// Explorer API para histórico de txs (Polygonscan)
const EXPLORER_API_KEY = 'SUA_POLYGONSCAN_KEY_AQUI';

// Contrato USDT na Polygon Mainnet (ERC-20, 6 decimais)
const USDT_ADDRESS     = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
const USDT_DECIMALS    = 6;

// Mínimo de confirmações antes de fazer o sweep
const MIN_CONFIRMATIONS = 12;

// Mínimo de USDT para iniciar sweep (evita custos de gas em valores irrisórios)
const MIN_SWEEP_AMOUNT  = 1.0;

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS DE OUTPUT
// ─────────────────────────────────────────────────────────────────────────────

function titulo(string $num, string $texto): void {
    echo "\n";
    echo "╔══════════════════════════════════════════════╗\n";
    echo "║  PASSO {$num} — {$texto}\n";
    echo "╚══════════════════════════════════════════════╝\n";
}

function ok(string $msg): void    { echo "  ✅  {$msg}\n"; }
function info(string $k, string $v): void { printf("  %-28s %s\n", $k . ':', $v); }
function aviso(string $msg): void { echo "  ⚠️   {$msg}\n"; }
function erro(string $msg): void  { echo "  ❌  {$msg}\n"; }
function separador(): void        { echo "  ────────────────────────────────────────\n"; }

// ─────────────────────────────────────────────────────────────────────────────
// MODO: FakeChain (offline/testes) ou Rede Real
// ─────────────────────────────────────────────────────────────────────────────

if (USE_FAKECHAIN) {
    // FakeChain simula a blockchain localmente — zero rede, zero gas real
    $chain = new \FakeChain\FakeChainHD(['auto_mine' => true]);
    $USDT  = '0xFAKE_USDT_CONTRACT'; // endereço fictício no FakeChain
    echo "\n  🧪  Modo SIMULADO (FakeChain) — nenhuma TX real será enviada.\n";
} else {
    // Conexão real à Polygon Mainnet
    $chain = new Web3PHP([
        'network'  => 'polygon',
        'provider' => RPC_PROVIDER,
        'api_key'  => RPC_API_KEY,
    ]);
    $USDT = USDT_ADDRESS;
    echo "\n  🌐  Modo PRODUÇÃO — Polygon Mainnet\n";
}

// ═════════════════════════════════════════════════════════════════════════════
// PASSO 1 — Gerar / importar mnemônica mestre e derivar carteiras
// ═════════════════════════════════════════════════════════════════════════════

titulo('1', 'Mnemônica Mestre e HD Wallets');

$mn = new MnemonicWallet();

// ── 1a. Gerar mnemônica ──────────────────────────────────────────────────────
//
// Em produção: gere UMA VEZ, guarde em lugar SEGURO (HSM, cofre criptografado,
// variável de ambiente encriptada — NUNCA hardcoded no código ou no banco).
//
// Para recarregar numa execução futura, leia do ambiente:
//   $masterMnemonic = getenv('MASTER_MNEMONIC');
//
$masterMnemonic = $mn->generate(24); // 24 palavras = segurança máxima (Ledger-style)

echo "\n";
aviso("⚠️  GUARDE AS PALAVRAS ABAIXO COM MÁXIMA SEGURANÇA!");
aviso("    Quem tiver essas palavras controla TODAS as carteiras.");
echo "\n";
echo "  Mnemônica (24 palavras):\n";
echo "  ┌─────────────────────────────────────────────────────┐\n";
$words = explode(' ', $masterMnemonic);
for ($i = 0; $i < 24; $i += 4) {
    printf("  │  %2d. %-10s %2d. %-10s %2d. %-10s %2d. %-10s│\n",
        $i+1, $words[$i],   $i+2, $words[$i+1] ?? '',
        $i+3, $words[$i+2] ?? '', $i+4, $words[$i+3] ?? ''
    );
}
echo "  └─────────────────────────────────────────────────────┘\n\n";

// ── 1b. Como armazenar com segurança ────────────────────────────────────────
//
// OPÇÃO A — Variável de ambiente (recomendado para produção):
//   export MASTER_MNEMONIC="palavra1 palavra2 ... palavra24"
//   No PHP: $masterMnemonic = getenv('MASTER_MNEMONIC');
//
// OPÇÃO B — Arquivo encriptado fora do webroot:
//   $encrypted = openssl_encrypt($masterMnemonic, 'aes-256-cbc', $chave, 0, $iv);
//   file_put_contents('/var/secrets/mnemonic.enc', $encrypted);
//
// OPÇÃO C — Serviço de secrets (AWS Secrets Manager, HashiCorp Vault, etc.)
//
// ❌ NUNCA: salvar no banco de dados em texto puro
// ❌ NUNCA: commitar no Git
// ❌ NUNCA: logar em arquivos de log

// Validar que a mnemônica gerada é correta
if (!$mn->validate($masterMnemonic)) {
    erro("Mnemônica inválida! Abortando.");
    exit(1);
}
ok("Mnemônica de 24 palavras gerada e validada (BIP-39 ✓)");

// ── 1c. Derivar carteiras por usuário (BIP-44) ───────────────────────────────
//
// Cada usuário do seu sistema recebe uma carteira única.
// A carteira é derivada deterministicamente a partir do índice do usuário.
// Você nunca precisa armazenar a chave privada — deriva na hora que precisar.
//
// Path BIP-44: m/44'/60'/0'/0/{userId}
//              rede Polygon usa coin-type 60 (mesmo que Ethereum)
//

$userId          = 1042;       // ID do usuário no seu sistema
$hotWalletIndex  = 0;          // Índice 0 = hot wallet / carteira mãe

// Carteira mãe (hot wallet) — recebe os fundos varridos
$hotWallet = $mn->deriveWallet($masterMnemonic, index: $hotWalletIndex, network: 'polygon');

// Carteira de depósito do usuário #1042
$depositWallet = $mn->deriveWallet($masterMnemonic, index: $userId, network: 'polygon');

echo "\n";
ok("Hot Wallet (carteira mãe) derivada:");
info("  Endereço", $hotWallet['checksum_address']);
info("  Path BIP-44", $hotWallet['path']);

separador();

ok("Carteira de depósito do usuário #{$userId}:");
info("  Endereço", $depositWallet['checksum_address']);
info("  Path BIP-44", $depositWallet['path']);

// A chave privada fica em memória apenas quando necessária para assinar
// Você pode derivar novamente a qualquer momento a partir da mnemônica
echo "\n";
aviso("Chave privada derivada em memória (nunca armazenada em disco).");

// ── 1d. Derivar em batch (pré-aquecer pool de endereços) ────────────────────
$batchSize = 5;
echo "\n";
ok("Exemplo de batch — primeiros {$batchSize} endereços de depósito:");
for ($i = 1; $i <= $batchSize; $i++) {
    $w = $mn->deriveWallet($masterMnemonic, index: $i, network: 'polygon');
    printf("    [%d] %s  (path: %s)\n", $i, $w['checksum_address'], $w['path']);
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 2 — Simular / aguardar depósito externo de USDT
// ═════════════════════════════════════════════════════════════════════════════

titulo('2', 'Depósito de USDT');

$depositAmount = 250.0; // USDT que o usuário irá enviar

if (USE_FAKECHAIN) {
    // Registrar as carteiras no chain simulado com saldo inicial de MATIC (gas)
    $chain->faucet($depositWallet['address'], 0.005); // gas mínimo
    $chain->faucet($hotWallet['address'], 2.0);       // hot wallet com bastante gas

    // Criar um usuário externo no FakeChain para simular o depósito
    $externalUser = $chain->createAccount('ExternalUser', 0, 0);
    $chain->faucet($externalUser['address'], 0.1);
    // Dar USDT ao usuário externo
    $chain->mintToken($USDT, $externalUser['address'], 1000.0, USDT_DECIMALS);

    // Usuário externo envia USDT para a carteira de depósito
    $depositTxHash = $chain->sendTokenTransfer(
        from:            $externalUser['address'],
        contractAddress: $USDT,
        to:              $depositWallet['address'],
        amount:          $depositAmount,
        privateKey:      $externalUser['private_key'],
        decimals:        USDT_DECIMALS
    );

    ok("Depósito simulado com sucesso!");
    info("  TX Hash", $depositTxHash);
    info("  Valor",   "{$depositAmount} USDT");
    info("  Para",    $depositWallet['address']);

} else {
    // Em produção: exiba o endereço para o usuário e aguarde o depósito
    echo "\n";
    ok("Endereço de depósito gerado para usuário #{$userId}:");
    echo "\n";
    echo "  ╔══════════════════════════════════════════════════════╗\n";
    echo "  ║  Envie USDT (Polygon/MATIC network) para:           ║\n";
    printf("  ║  %-52s  ║\n", $depositWallet['checksum_address']);
    echo "  ╚══════════════════════════════════════════════════════╝\n\n";
    aviso("Aguardando depósito... (em produção, use um cron job ou webhook)");

    $depositTxHash = '0x_HASH_DA_TX_DO_DEPOSITO'; // seria obtido via monitoramento
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 3 — Verificar transações recebidas na carteira
// ═════════════════════════════════════════════════════════════════════════════

titulo('3', 'Verificar transações recebidas');

$depositAddress = USE_FAKECHAIN
    ? $depositWallet['address']
    : $depositWallet['checksum_address'];

// Histórico de transferências do token USDT especificamente
$tokenTxs = USE_FAKECHAIN
    ? $chain->wallet->getTokenTransfers($depositAddress, contractAddress: $USDT)
    : $chain->wallet->getTokenTransfers($depositAddress, EXPLORER_API_KEY, USDT_ADDRESS);

info("Transferências USDT encontradas", (string)count($tokenTxs));

// Filtrar apenas as recebidas
$received = array_filter($tokenTxs, fn($tx) =>
    strtolower($tx['to'] ?? '') === strtolower($depositAddress)
);

if (empty($received)) {
    aviso("Nenhuma transferência recebida ainda. Tente mais tarde.");
} else {
    ok(count($received) . " transferência(s) USDT recebida(s):");
    foreach ($received as $tx) {
        printf("    TX: %s  |  Valor: %s USDT  |  Bloco: %s\n",
            substr($tx['hash'], 0, 20) . '...',
            $tx['value'] ?? '?',
            $tx['block'] ?? '?'
        );
    }
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 4 — Confirmar saldo e aguardar confirmações suficientes
// ═════════════════════════════════════════════════════════════════════════════

titulo('4', 'Confirmar depósito (blocos)');

$usdtBalance = USE_FAKECHAIN
    ? $chain->wallet->getTokenBalance($depositAddress, $USDT, USDT_DECIMALS)
    : $chain->wallet->getTokenBalance($depositAddress, USDT_ADDRESS, USDT_DECIMALS);

$maticBalance = USE_FAKECHAIN
    ? $chain->wallet->getBalance($depositAddress)
    : $chain->wallet->getBalance($depositAddress);

info("Saldo USDT na carteira", "{$usdtBalance} USDT");
info("Saldo MATIC (gas)",      "{$maticBalance} MATIC");

if ((float)$usdtBalance < MIN_SWEEP_AMOUNT) {
    erro("Saldo insuficiente para sweep. Mínimo: " . MIN_SWEEP_AMOUNT . " USDT");
    exit(0);
}

// Verificar confirmações da TX de depósito
$currentBlock = USE_FAKECHAIN
    ? $chain->block->getLatestBlockNumber()
    : $chain->block->getLatestBlockNumber();

if (!USE_FAKECHAIN && !empty($tokenTxs)) {
    $txBlock = (int)($tokenTxs[0]['block'] ?? $currentBlock);
    $confirmations = $currentBlock - $txBlock;
    info("Bloco atual",       (string)$currentBlock);
    info("Bloco da TX",       (string)$txBlock);
    info("Confirmações",      "{$confirmations} / " . MIN_CONFIRMATIONS);

    if ($confirmations < MIN_CONFIRMATIONS) {
        aviso("Aguardando mais confirmações. Tente em instantes.");
        exit(0);
    }
}

ok("Depósito confirmado! Saldo: {$usdtBalance} USDT");
$confirmedAmount = (float)$usdtBalance;


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 5 — Calcular gas necessário para o sweep (ERC-20 transfer)
// ═════════════════════════════════════════════════════════════════════════════

titulo('5', 'Calcular gas para sweep');

// Obter info de gas da rede
$gasInfo = $chain->block->getGasInfo();

// Estimar gas para uma transferência ERC-20 típica
// ERC-20 transfer usa em média ~65.000 gas; pedimos estimativa real com buffer
$estimateTx = [
    'from' => $depositAddress,
    'to'   => $USDT,
    'data' => '0xa9059cbb', // selector de transfer(address,uint256)
];

$gasEstimado = (int)$chain->block->estimateGas($estimateTx);
$gasLimit    = (int)ceil($gasEstimado * 1.25); // +25% de margem de segurança

// Calcular custo em MATIC
$gasPriceWei  = $gasInfo['gas_price_wei'] ?? '30000000000'; // fallback: 30 Gwei
$gasCostWei   = bcmul((string)$gasLimit, $gasPriceWei, 0);
$gasCostMatic = bcdiv($gasCostWei, bcpow('10', '18', 0), 8);

info("Gas estimado (ERC-20 transfer)", "{$gasEstimado} units");
info("Gas limit (+ 25% buffer)",       "{$gasLimit} units");
info("Gas price",                       ($gasInfo['gas_price_gwei'] ?? '?') . " Gwei");
info("Custo total em MATIC",            "{$gasCostMatic} MATIC");
info("Saldo MATIC disponível",          "{$maticBalance} MATIC");

$gasInsuficiente = bccomp($maticBalance, $gasCostMatic, 8) < 0;
$gasNecessario   = $gasInsuficiente
    ? bcsub($gasCostMatic, $maticBalance, 8)
    : '0';

if ($gasInsuficiente) {
    aviso("Gas insuficiente! Faltam {$gasNecessario} MATIC.");
} else {
    ok("Gas suficiente (sobra " . bcsub($maticBalance, $gasCostMatic, 8) . " MATIC).");
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 6 — Enviar MATIC de gas para a carteira de depósito (se necessário)
// ═════════════════════════════════════════════════════════════════════════════

titulo('6', 'Top-up de gas (MATIC)');

if (!$gasInsuficiente) {
    ok("Sem necessidade de top-up. Pulando.");

} else {
    // Enviamos da hot wallet para a carteira de depósito o MATIC necessário
    // (+margem extra para cobrir variações de preço de gas)
    $maticParaEnviar = bcadd($gasNecessario, '0.001', 8); // +0.001 MATIC de margem

    info("Enviando MATIC da hot wallet", "{$maticParaEnviar} MATIC");

    if (USE_FAKECHAIN) {
        $topupTxHash = $chain->sendTransfer(
            from:       $hotWallet['address'],
            to:         $depositWallet['address'],
            amount:     (float)$maticParaEnviar,
            privateKey: $hotWallet['private_key']
        );
    } else {
        // Montar TX de transferência nativa (MATIC)
        $unsignedTx = $chain->transfer->buildNativeTransfer(
            from:   $hotWallet['checksum_address'],
            to:     $depositWallet['checksum_address'],
            amount: (float)$maticParaEnviar
        );

        // Assinar com kornrunner/ethereum-offline-raw-tx
        $transaction = new \kornrunner\Ethereum\Transaction(
            $unsignedTx['nonce'],
            $unsignedTx['gasPrice'],
            $unsignedTx['gas'],
            $unsignedTx['to'],
            $unsignedTx['value'],
            $unsignedTx['data']
        );
        $chainId    = hexdec($unsignedTx['chainId']); // Polygon = 137
        $rawSigned  = $transaction->getRaw($hotWallet['private_key'], $chainId);
        $topupTxHash = $chain->transfer->sendRaw($rawSigned);

        // Aguardar confirmação do top-up antes de continuar
        $receipt = $chain->transfer->waitForConfirmation($topupTxHash, timeout: 120);
        if ($receipt['status'] !== 'success') {
            erro("Top-up falhou: " . $receipt['status']);
            exit(1);
        }
    }

    ok("Top-up enviado!");
    info("  TX Hash", $topupTxHash);
    info("  Valor",   "{$maticParaEnviar} MATIC");
}


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 7 — Varrer USDT para a hot wallet (sweep)
// ═════════════════════════════════════════════════════════════════════════════

titulo('7', 'Sweep — varrer USDT para hot wallet');

// Reler saldo atualizado (pode ter chegado mais MATIC do top-up)
$usdtParaVarrer = (float)$chain->wallet->getTokenBalance(
    USE_FAKECHAIN ? $depositWallet['address'] : $depositWallet['checksum_address'],
    $USDT,
    USDT_DECIMALS
);

$hotAddress = USE_FAKECHAIN
    ? $hotWallet['address']
    : $hotWallet['checksum_address'];

info("USDT a varrer",   "{$usdtParaVarrer} USDT");
info("Destino (hot wallet)", $hotAddress);

if (USE_FAKECHAIN) {
    $sweepTxHash = $chain->sendTokenTransfer(
        from:            $depositWallet['address'],
        contractAddress: $USDT,
        to:              $hotWallet['address'],
        amount:          $usdtParaVarrer,
        privateKey:      $depositWallet['private_key'],
        decimals:        USDT_DECIMALS
    );

} else {
    // Montar TX de token ERC-20
    $unsignedTokenTx = $chain->transfer->buildTokenTransfer(
        from:            $depositWallet['checksum_address'],
        contractAddress: USDT_ADDRESS,
        to:              $hotWallet['checksum_address'],
        amount:          $usdtParaVarrer,
        decimals:        USDT_DECIMALS,
        customGas:       $gasLimit
    );

    // Assinar com a chave privada da carteira de depósito (derivada na hora)
    $transaction = new \kornrunner\Ethereum\Transaction(
        $unsignedTokenTx['nonce'],
        $unsignedTokenTx['gasPrice'],
        $unsignedTokenTx['gas'],
        $unsignedTokenTx['to'],
        '0x0',                       // valor em MATIC = 0 (só transferindo token)
        $unsignedTokenTx['data']
    );
    $chainId       = hexdec($unsignedTokenTx['chainId']); // 137
    $rawSigned     = $transaction->getRaw($depositWallet['private_key'], $chainId);
    $sweepTxHash   = $chain->transfer->sendRaw($rawSigned);
}

ok("Sweep enviado!");
info("  TX Hash",  $sweepTxHash);
info("  Valor",    "{$usdtParaVarrer} USDT");
info("  Para",     $hotAddress);


// ═════════════════════════════════════════════════════════════════════════════
// PASSO 8 — Verificar transação e aguardar confirmação
// ═════════════════════════════════════════════════════════════════════════════

titulo('8', 'Verificar TX e aguardar confirmação');

// Aguardar mineração da TX (timeout: 3 minutos)
$sweepReceipt = $chain->transfer->waitForConfirmation($sweepTxHash, timeout: 180);

if ($sweepReceipt['status'] !== 'success') {
    erro("Sweep falhou! Status: " . $sweepReceipt['status']);
    erro("Verifique a TX no explorer: https://polygonscan.com/tx/{$sweepTxHash}");
    exit(1);
}

ok("Sweep confirmado na blockchain!");
info("  TX Hash",    $sweepTxHash);
info("  Bloco",      (string)$sweepReceipt['block']);
info("  Gas usado",  (string)$sweepReceipt['gas_used'] . " units");
info("  Status",     $sweepReceipt['status']);

// Verificar saldos finais
$finalHotUsdt = $chain->wallet->getTokenBalance(
    USE_FAKECHAIN ? $hotWallet['address'] : $hotWallet['checksum_address'],
    $USDT,
    USDT_DECIMALS
);
$finalDepositUsdt = $chain->wallet->getTokenBalance(
    USE_FAKECHAIN ? $depositWallet['address'] : $depositWallet['checksum_address'],
    $USDT,
    USDT_DECIMALS
);

echo "\n";
info("Hot wallet USDT (após sweep)",       "{$finalHotUsdt} USDT");
info("Carteira depósito USDT (restante)",  "{$finalDepositUsdt} USDT");


// ═════════════════════════════════════════════════════════════════════════════
// RESUMO FINAL
// ═════════════════════════════════════════════════════════════════════════════

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMO DO FLUXO COMPLETO                    ║\n";
echo "╠══════════════════════════════════════════════════════════════════╣\n";
$modo = USE_FAKECHAIN ? "FakeChain (simulado, offline)" : "Polygon Mainnet (real)";
printf("║  Modo: %-57s ║\n", $modo);
printf("║  Usuário: #%-54d ║\n", $userId);
printf("║  Depósito confirmado: %-43s ║\n", "{$confirmedAmount} USDT");
printf("║  Varrido para hot wallet: %-38s ║\n", "{$usdtParaVarrer} USDT");
printf("║  TX de sweep: %-50s ║\n", substr($sweepTxHash, 0, 46) . '...');
echo "╠══════════════════════════════════════════════════════════════════╣\n";
echo "║  PASSOS COMPLETADOS:                                            ║\n";
echo "║   ✅ 1 — Mnemônica 24 palavras gerada (BIP-39)                 ║\n";
echo "║   ✅ 2 — Carteiras derivadas por userId (BIP-44 HD)             ║\n";
echo "║   ✅ 3 — Depósito USDT detectado                               ║\n";
echo "║   ✅ 4 — Confirmações verificadas                               ║\n";
echo "║   ✅ 5 — Gas calculado (estimativa + 25% buffer)                ║\n";
echo "║   ✅ 6 — Top-up de MATIC enviado (se necessário)               ║\n";
echo "║   ✅ 7 — Sweep USDT → hot wallet executado                     ║\n";
echo "║   ✅ 8 — TX confirmada on-chain                                ║\n";
echo "╠══════════════════════════════════════════════════════════════════╣\n";
echo "║  PRÓXIMOS PASSOS PARA PRODUÇÃO:                                 ║\n";
echo "║   1. Mude USE_FAKECHAIN para false                              ║\n";
echo "║   2. Preencha RPC_API_KEY e EXPLORER_API_KEY                    ║\n";
echo "║   3. Armazene MASTER_MNEMONIC em variável de ambiente segura    ║\n";
echo "║   4. Rode este fluxo via cron job ou fila (ex: Laravel Queue)  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
