<?php

// ═══════════════════════════════════════════════════════════
//  Web3PHP — Transferir Cripto com Gestão de Gas
//  Fluxo completo: verificar gas → top-up → transferir
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Web3PHP\Web3PHP;
use Web3PHP\Math;
use kornrunner\Ethereum\Transaction;

$w3 = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

$hotWallet      = ['address' => '0xHOT_WALLET',    'private_key' => 'HOT_PK'];
$depositWallet  = ['address' => '0xDEPOSIT_WALLET', 'private_key' => 'DEPOSIT_PK'];
$destino        = '0xENDERECO_DESTINO';

$usdtContract   = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
$usdtDecimals   = 6;
$chainId        = 137; // Polygon


// ════════════════════════════════════════════════════════════
// PASSO 1 — Verificar saldos atuais
// ════════════════════════════════════════════════════════════

$saldoUsdt  = $w3->wallet->getTokenBalance($depositWallet['address'], $usdtContract, $usdtDecimals);
$saldoMatic = $w3->wallet->getBalance($depositWallet['address']);

echo "Saldo USDT:  {$saldoUsdt}"  . PHP_EOL;
echo "Saldo MATIC: {$saldoMatic}" . PHP_EOL;

if ((float)$saldoUsdt <= 0) {
    echo "Nada a transferir." . PHP_EOL;
    exit(0);
}


// ════════════════════════════════════════════════════════════
// PASSO 2 — Calcular gas necessário
// ════════════════════════════════════════════════════════════

$gasInfo = $w3->block->getGasInfo();

echo "Gas price: " . $gasInfo['gas_price_gwei'] . " Gwei" . PHP_EOL;
echo "Base fee:  " . ($gasInfo['base_fee_gwei'] ?? 'n/a') . " Gwei" . PHP_EOL;

// Estimar gas real para essa TX específica
$gasEstimado = (int)$w3->block->estimateGas([
    'from' => $depositWallet['address'],
    'to'   => $usdtContract,
    'data' => '0xa9059cbb', // selector transfer(address,uint256)
]);

// Aplicar buffer de segurança de 25%
$gasLimit = (int)ceil($gasEstimado * 1.25);

// Calcular custo total em MATIC
$gasPriceWei  = $gasInfo['gas_price_wei'];
$gasCostWei   = bcmul((string)$gasLimit, $gasPriceWei, 0);
$gasCostMatic = bcdiv($gasCostWei, bcpow('10', '18', 0), 8);

echo "Gas estimado:  {$gasEstimado} units" . PHP_EOL;
echo "Gas limit:     {$gasLimit} units (+25% buffer)" . PHP_EOL;
echo "Custo em MATIC: {$gasCostMatic}" . PHP_EOL;


// ════════════════════════════════════════════════════════════
// PASSO 3 — Top-up de gas se necessário
// ════════════════════════════════════════════════════════════

$gasInsuficiente = bccomp($saldoMatic, $gasCostMatic, 8) < 0;

if ($gasInsuficiente) {

    $faltaMatic  = bcsub($gasCostMatic, $saldoMatic, 8);
    $topUpAmount = bcadd($faltaMatic, '0.002', 8); // +0.002 de margem extra

    echo "Gas insuficiente. Enviando {$topUpAmount} MATIC da hot wallet..." . PHP_EOL;

    // Verificar se a hot wallet tem saldo suficiente para o top-up
    $hotSaldoMatic = $w3->wallet->getBalance($hotWallet['address']);
    if (bccomp($hotSaldoMatic, $topUpAmount, 8) < 0) {
        echo "Hot wallet sem MATIC suficiente ({$hotSaldoMatic}). Abortando." . PHP_EOL;
        exit(1);
    }

    // Montar TX de MATIC nativo (hot wallet → deposit wallet)
    $topUpTx = $w3->transfer->buildNativeTransfer(
        from:   $hotWallet['address'],
        to:     $depositWallet['address'],
        amount: (float)$topUpAmount
    );

    // Assinar e enviar
    $topUpSigned = new Transaction(
        $topUpTx['nonce'],
        $topUpTx['gasPrice'],
        $topUpTx['gas'],
        $topUpTx['to'],
        $topUpTx['value'],
        $topUpTx['data']
    );
    $topUpRaw  = $topUpSigned->getRaw($hotWallet['private_key'], $chainId);
    $topUpHash = $w3->transfer->sendRaw($topUpRaw);

    echo "Top-up enviado: {$topUpHash}" . PHP_EOL;

    // Aguardar confirmação antes de continuar
    $topUpReceipt = $w3->transfer->waitForConfirmation($topUpHash, timeout: 120);
    if ($topUpReceipt['status'] !== 'success') {
        echo "Top-up falhou: " . $topUpReceipt['status'] . PHP_EOL;
        exit(1);
    }

    echo "Top-up confirmado no bloco {$topUpReceipt['block']}" . PHP_EOL;

} else {
    $sobra = bcsub($saldoMatic, $gasCostMatic, 8);
    echo "Gas suficiente (sobra {$sobra} MATIC)" . PHP_EOL;
}


// ════════════════════════════════════════════════════════════
// PASSO 4A — Transferir TOKEN (USDT / ERC-20)
// ════════════════════════════════════════════════════════════

$tokenTx = $w3->transfer->buildTokenTransfer(
    from:            $depositWallet['address'],
    contractAddress: $usdtContract,
    to:              $destino,
    amount:          (float)$saldoUsdt,
    decimals:        $usdtDecimals,
    customGas:       $gasLimit
);

$tokenSigned = new Transaction(
    $tokenTx['nonce'],
    $tokenTx['gasPrice'],
    $tokenTx['gas'],
    $tokenTx['to'],
    '0x0',             // value = 0 para transferência de token
    $tokenTx['data']
);
$tokenRaw  = $tokenSigned->getRaw($depositWallet['private_key'], $chainId);
$tokenHash = $w3->transfer->sendRaw($tokenRaw);

echo "TX token enviada: {$tokenHash}" . PHP_EOL;

// Aguardar confirmação
$tokenReceipt = $w3->transfer->waitForConfirmation($tokenHash, timeout: 180);
echo "Status:    {$tokenReceipt['status']}"   . PHP_EOL;
echo "Bloco:     {$tokenReceipt['block']}"    . PHP_EOL;
echo "Gas usado: {$tokenReceipt['gas_used']}" . PHP_EOL;


// ════════════════════════════════════════════════════════════
// PASSO 4B — Transferir NATIVO (MATIC/ETH/BNB)
//  Use este bloco no lugar do 4A para moeda nativa
// ════════════════════════════════════════════════════════════

$valorNativo = 1.5; // MATIC

$nativeTx = $w3->transfer->buildNativeTransfer(
    from:      $depositWallet['address'],
    to:        $destino,
    amount:    $valorNativo,
    customGas: $gasLimit
);

$nativeSigned = new Transaction(
    $nativeTx['nonce'],
    $nativeTx['gasPrice'],
    $nativeTx['gas'],
    $nativeTx['to'],
    $nativeTx['value'],
    $nativeTx['data']
);
$nativeRaw  = $nativeSigned->getRaw($depositWallet['private_key'], $chainId);
$nativeHash = $w3->transfer->sendRaw($nativeRaw);

echo "TX nativa enviada: {$nativeHash}" . PHP_EOL;

$nativeReceipt = $w3->transfer->waitForConfirmation($nativeHash, timeout: 180);
echo "Status:    {$nativeReceipt['status']}"   . PHP_EOL;
echo "Bloco:     {$nativeReceipt['block']}"    . PHP_EOL;
echo "Gas usado: {$nativeReceipt['gas_used']}" . PHP_EOL;


// ════════════════════════════════════════════════════════════
// RESUMO — Saldos finais
// ════════════════════════════════════════════════════════════

$saldoUsdtFinal  = $w3->wallet->getTokenBalance($depositWallet['address'], $usdtContract, $usdtDecimals);
$saldoMaticFinal = $w3->wallet->getBalance($depositWallet['address']);

echo "USDT final:  {$saldoUsdtFinal}"  . PHP_EOL;
echo "MATIC final: {$saldoMaticFinal}" . PHP_EOL;