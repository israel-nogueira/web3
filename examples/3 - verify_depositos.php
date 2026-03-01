<?php

// ═══════════════════════════════════════════════════════════
//  Web3PHP — Verificar Depósitos no Bloco
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Web3PHP\Web3PHP;
use Web3PHP\Math;

$w3 = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

$minhaCarteira  = '0xSEU_ENDERECO_AQUI';
$explorerApiKey = 'SEU_POLYGONSCAN_KEY';
$usdtContract   = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
$usdtDecimals   = 6;


// ─── 1. BLOCO ATUAL ──────────────────────────────────────
$blocoAtual = $w3->block->getLatestBlockNumber();
echo "Bloco atual: {$blocoAtual}" . PHP_EOL;

// Detalhes do bloco mais recente
$bloco = $w3->block->getBlock('latest');
echo $bloco['number']    . PHP_EOL; // número
echo $bloco['hash']      . PHP_EOL; // hash
echo $bloco['datetime']  . PHP_EOL; // timestamp human-readable
echo $bloco['tx_count']  . PHP_EOL; // quantas TXs no bloco
echo $bloco['gas_used']  . PHP_EOL;

// Bloco com todas as transações expandidas
$blocoCompleto = $w3->block->getBlock('latest', fullTransactions: true);
foreach ($blocoCompleto['transactions'] as $tx) {
    echo $tx['hash'] . ' — ' . $tx['value'] . PHP_EOL;
}

// Range de blocos (últimos 10)
$blocos = $w3->block->getBlockRange($blocoAtual - 10, $blocoAtual);
foreach ($blocos as $b) {
    echo "#{$b['number']} — {$b['tx_count']} txs — {$b['datetime']}" . PHP_EOL;
}


// ─── 2. HISTÓRICO DE TXs NATIVAS (MATIC/ETH) ────────────
//  Requer chave do explorer (Polygonscan, Etherscan, etc.)

$historico = $w3->wallet->getTransactionHistory(
    address:        $minhaCarteira,
    explorerApiKey: $explorerApiKey,
    page:           1,
    offset:         20       // últimas 20 TXs
);

foreach ($historico as $tx) {
    $direcao = strtolower($tx['to']) === strtolower($minhaCarteira) ? '← RECEBEU' : '→ ENVIOU';
    echo "[{$tx['datetime']}] {$direcao} {$tx['value_eth']} MATIC | {$tx['status']}" . PHP_EOL;
    echo "  hash:  {$tx['hash']}" . PHP_EOL;
    echo "  from:  {$tx['from']}" . PHP_EOL;
    echo "  bloco: {$tx['block']}" . PHP_EOL;
}


// ─── 3. TRANSFERÊNCIAS DE TOKEN (USDT, ERC-20) ───────────
$tokenTxs = $w3->wallet->getTokenTransfers(
    address:         $minhaCarteira,
    explorerApiKey:  $explorerApiKey,
    contractAddress: $usdtContract   // omitir para ver todos os tokens
);

// Filtrar apenas os depósitos recebidos
$depositos = array_filter($tokenTxs, fn($tx) =>
    strtolower($tx['to'] ?? '') === strtolower($minhaCarteira)
);

foreach ($depositos as $tx) {
    echo "[{$tx['datetime']}] ← RECEBEU {$tx['token_amount']} USDT" . PHP_EOL;
    echo "  de:    {$tx['from']}" . PHP_EOL;
    echo "  hash:  {$tx['hash']}" . PHP_EOL;
    echo "  bloco: {$tx['block']}" . PHP_EOL;
}


// ─── 4. VERIFICAR UMA TX ESPECÍFICA ──────────────────────
$txHash = '0xSUA_TX_HASH';
$tx = $w3->getTransaction($txHash);

echo $tx['hash']     . PHP_EOL;
echo $tx['from']     . PHP_EOL;
echo $tx['to']       . PHP_EOL;
echo $tx['value']    . PHP_EOL; // valor em unidade nativa
echo $tx['status']   . PHP_EOL; // success | failed | pending
echo $tx['block']    . PHP_EOL;
echo $tx['datetime'] . PHP_EOL;


// ─── 5. CONFIRMAR DEPÓSITO (AGUARDAR N BLOCOS) ───────────
//  Boa prática: só creditar o usuário após N confirmações

$MIN_CONFIRMACOES = 12;

$txBloco       = (int)$tx['block'];
$confirmacoes  = $blocoAtual - $txBloco;

if ($confirmacoes >= $MIN_CONFIRMACOES) {
    echo "✅ Depósito confirmado ({$confirmacoes} confirmações)" . PHP_EOL;
} else {
    echo "⏳ Aguardando... {$confirmacoes}/{$MIN_CONFIRMACOES}" . PHP_EOL;
}


// ─── 6. APLICAÇÃO PRÁTICA: Monitor de Depósitos ──────────
//  Roda em loop (cron a cada 30s, por exemplo)
//  Verifica novas TXs recebidas desde o último bloco checado

$ultimoBlocoChecado = $blocoAtual - 50; // começa dos últimos 50 blocos
$depositos          = [];

$txsRecentes = $w3->wallet->getTokenTransfers(
    address:         $minhaCarteira,
    explorerApiKey:  $explorerApiKey,
    contractAddress: $usdtContract
);

foreach ($txsRecentes as $tx) {
    $txBloco      = (int)($tx['block'] ?? 0);
    $confirmacoes = $blocoAtual - $txBloco;
    $ehDeposito   = strtolower($tx['to'] ?? '') === strtolower($minhaCarteira);
    $ehNovo       = $txBloco > $ultimoBlocoChecado;
    $confirmado   = $confirmacoes >= $MIN_CONFIRMACOES;

    if ($ehDeposito && $ehNovo && $confirmado) {
        $depositos[] = [
            'hash'         => $tx['hash'],
            'valor'        => $tx['token_amount'],
            'de'           => $tx['from'],
            'bloco'        => $txBloco,
            'confirmacoes' => $confirmacoes,
            'datetime'     => $tx['datetime'],
        ];
    }
}

// Processar cada depósito novo confirmado
foreach ($depositos as $dep) {
    echo "💰 Novo depósito: {$dep['valor']} USDT de {$dep['de']}" . PHP_EOL;
    echo "   TX:    {$dep['hash']}" . PHP_EOL;
    echo "   Bloco: {$dep['bloco']} ({$dep['confirmacoes']} confirmações)" . PHP_EOL;

    // Aqui: creditar usuário no banco, enviar notificação, etc.
    // creditarUsuario($dep['de'], $dep['valor']);
}

// Atualizar o último bloco checado para a próxima execução
$ultimoBlocoChecado = $blocoAtual;
// Persistir em banco/cache: cache()->set('ultimo_bloco', $ultimoBlocoChecado);


// ─── 7. GAS INFO (saber se a rede está cara) ─────────────
$gasInfo = $w3->block->getGasInfo();
echo $gasInfo['gas_price_gwei']  . ' Gwei'  . PHP_EOL;
echo $gasInfo['base_fee_gwei']   . ' Gwei'  . PHP_EOL;
echo $gasInfo['gas_price_wei']   . ' wei'   . PHP_EOL;

// Estimar gas de uma TX antes de enviar
$gasEstimado = $w3->block->estimateGas([
    'from' => $minhaCarteira,
    'to'   => '0xDESTINO',
    'data' => '0x',
]);
echo "Gas estimado: {$gasEstimado}" . PHP_EOL; // ex: 21000