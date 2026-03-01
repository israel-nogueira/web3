<?php

// ═══════════════════════════════════════════════════════════
//  Web3PHP — TRON: Freeze/Unfreeze TRX por Energia/Bandwidth
//  O TRX é travado, vira recurso, depois volta pra você
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Web3PHP\Web3PHP;
use Web3PHP\Math;

$tron = new Web3PHP([
    'network'  => 'tron',
    'provider' => 'public',
    'api_key'  => 'SEU_TRONGRID_KEY',
]);

$minhaWallet = ['address' => 'TSUA_CARTEIRA', 'private_key' => 'SUA_PK'];
$outraWallet = 'TCARTEIRA_DO_USUARIO'; // pode delegar recursos pra outra wallet


// ════════════════════════════════════════════════════════════
// PASSO 1 — Ver recursos disponíveis antes do freeze
// ════════════════════════════════════════════════════════════

$recursos = $tron->network->getTronBandwidth($minhaWallet['address']);

echo "=== RECURSOS ATUAIS ===" . PHP_EOL;
echo "Bandwidth disponível:    " . ($recursos['freeNetLimit'] ?? 0)      . PHP_EOL;
echo "Bandwidth usado:         " . ($recursos['freeNetUsed'] ?? 0)       . PHP_EOL;
echo "Energia disponível:      " . ($recursos['EnergyLimit'] ?? 0)       . PHP_EOL;
echo "Energia usada:           " . ($recursos['EnergyUsed'] ?? 0)        . PHP_EOL;
echo "TRX frozen (energia):    " . ($recursos['frozen_supply'] ?? 0)     . PHP_EOL;
echo "TRX frozen (bandwidth):  " . ($recursos['delegated_frozen'] ?? 0)  . PHP_EOL;

// Saldo TRX atual
$saldoTrx = $tron->wallet->getBalance($minhaWallet['address']);
echo "Saldo TRX livre:         {$saldoTrx} TRX" . PHP_EOL;


// ════════════════════════════════════════════════════════════
// PASSO 2 — Calcular quanto TRX congelar
//
// Referência (aproximada, varia com a rede):
//   1 TRX frozen = ~420 Energia/dia
//   1 TRX frozen = ~1000 Bandwidth/dia
//
// Uma transferência USDT (TRC-20) consome ~30.000 Energia
// Ou seja: 72 TRX frozen cobrem 1 transfer por dia
//
// Cálculo: quantas transfers por dia você quer absorver?
// ════════════════════════════════════════════════════════════

$transfersPorDia   = 50;                          // sua demanda estimada
$energiaPorTransfer = 30_000;                     // energia média por TRC-20 transfer
$energiaNecessaria  = $transfersPorDia * $energiaPorTransfer; // 1.500.000

$energiaPorTrx      = 420;                        // energia gerada por 1 TRX frozen
$trxNecessario      = (int)ceil($energiaNecessaria / $energiaPorTrx); // ~3.572 TRX

echo PHP_EOL . "=== CÁLCULO ===" . PHP_EOL;
echo "Transfers/dia desejados: {$transfersPorDia}"       . PHP_EOL;
echo "Energia necessária:      {$energiaNecessaria}"     . PHP_EOL;
echo "TRX a congelar:          {$trxNecessario} TRX"     . PHP_EOL;
echo "TRX volta em:            14 dias (unfreeze)"       . PHP_EOL;
echo "Custo real:              R\$ 0,00 (TRX não é gasto)" . PHP_EOL;


// ════════════════════════════════════════════════════════════
// PASSO 3 — Freeze TRX por ENERGIA (para TRC-20 transfers)
//
// resource: 'ENERGY'    → para smart contracts / TRC-20
// resource: 'BANDWIDTH' → para TRX transfers simples
// receiver: endereço que vai usar o recurso (pode ser outra wallet)
// ════════════════════════════════════════════════════════════

$freezeTx = $tron->rpc('wallet/freezebalancev2', [
    'owner_address'    => base58_to_hex($minhaWallet['address']),
    'frozen_balance'   => Math::sunToTrx($trxNecessario) * 1_000_000, // em SUN (1 TRX = 1.000.000 SUN)
    'resource'         => 'ENERGY',   // ou 'BANDWIDTH'
    'visible'          => true,
]);

// Assinar e broadcast
$freezeSigned    = assinarTronTx($freezeTx, $minhaWallet['private_key']);
$freezeResultado = $tron->transfer->broadcastTron($freezeSigned);

echo PHP_EOL . "=== FREEZE ===" . PHP_EOL;
echo "TX Hash:   " . $freezeResultado['txid']   . PHP_EOL;
echo "Status:    " . $freezeResultado['result']  . PHP_EOL;
echo "TRX frozen: {$trxNecessario} TRX"          . PHP_EOL;
echo "Recurso:   ENERGY"                          . PHP_EOL;
echo "Unfreeze disponível em: " . date('Y-m-d', strtotime('+14 days')) . PHP_EOL;


// ════════════════════════════════════════════════════════════
// PASSO 4 — Delegar energia para outra carteira (opcional)
//
// Útil quando você quer que as wallets dos usuários
// usem SUA energia — eles nunca precisam de TRX
// ════════════════════════════════════════════════════════════

$delegarTx = $tron->rpc('wallet/delegateresource', [
    'owner_address'    => base58_to_hex($minhaWallet['address']),
    'receiver_address' => base58_to_hex($outraWallet),
    'balance'          => 100_000_000_000, // SUN a delegar
    'resource'         => 'ENERGY',
    'lock'             => false,           // false = pode revogar a qualquer hora
    'visible'          => true,
]);

$delegarSigned    = assinarTronTx($delegarTx, $minhaWallet['private_key']);
$delegarResultado = $tron->transfer->broadcastTron($delegarSigned);

echo PHP_EOL . "=== DELEGAÇÃO ===" . PHP_EOL;
echo "Energia delegada para: {$outraWallet}"       . PHP_EOL;
echo "TX: " . $delegarResultado['txid']             . PHP_EOL;
// A partir daqui, $outraWallet faz transfers USDT sem gastar TRX


// ════════════════════════════════════════════════════════════
// PASSO 5 — Revogar delegação (quando quiser de volta)
// ════════════════════════════════════════════════════════════

$revogarTx = $tron->rpc('wallet/undelegateresource', [
    'owner_address'    => base58_to_hex($minhaWallet['address']),
    'receiver_address' => base58_to_hex($outraWallet),
    'balance'          => 100_000_000_000,
    'resource'         => 'ENERGY',
    'visible'          => true,
]);

$revogarSigned    = assinarTronTx($revogarTx, $minhaWallet['private_key']);
$revogarResultado = $tron->transfer->broadcastTron($revogarSigned);

echo PHP_EOL . "=== REVOGAÇÃO ===" . PHP_EOL;
echo "Delegação revogada: " . $revogarResultado['txid'] . PHP_EOL;


// ════════════════════════════════════════════════════════════
// PASSO 6 — Unfreeze TRX (após 14 dias)
//           Seu TRX volta integralmente
// ════════════════════════════════════════════════════════════

$unfreezeTx = $tron->rpc('wallet/unfreezebalancev2', [
    'owner_address'  => base58_to_hex($minhaWallet['address']),
    'unfreeze_balance' => Math::sunToTrx($trxNecessario) * 1_000_000,
    'resource'       => 'ENERGY',
    'visible'        => true,
]);

$unfreezeSigned    = assinarTronTx($unfreezeTx, $minhaWallet['private_key']);
$unfreezeResultado = $tron->transfer->broadcastTron($unfreezeSigned);

echo PHP_EOL . "=== UNFREEZE ===" . PHP_EOL;
echo "TX: "     . $unfreezeResultado['txid']   . PHP_EOL;
echo "Status: " . $unfreezeResultado['result'] . PHP_EOL;
echo "TRX de volta: {$trxNecessario} TRX"      . PHP_EOL;
// ⚠️  Após unfreeze há um período de 14 dias de "unstaking" antes do TRX
//     ficar disponível novamente para uso (protocolo Stake 2.0 do Tron)


// ════════════════════════════════════════════════════════════
// PASSO 7 — Withdraw após unstaking (após os 14 dias do unfreeze)
// ════════════════════════════════════════════════════════════

$withdrawTx = $tron->rpc('wallet/withdrawexpireunfreeze', [
    'owner_address' => base58_to_hex($minhaWallet['address']),
    'visible'       => true,
]);

$withdrawSigned    = assinarTronTx($withdrawTx, $minhaWallet['private_key']);
$withdrawResultado = $tron->transfer->broadcastTron($withdrawSigned);

echo PHP_EOL . "=== WITHDRAW ===" . PHP_EOL;
echo "TRX retirado do unstaking: {$trxNecessario} TRX" . PHP_EOL;
echo "TX: " . $withdrawResultado['txid']               . PHP_EOL;


// ════════════════════════════════════════════════════════════
// RESUMO DO CICLO COMPLETO
// ════════════════════════════════════════════════════════════

//  freeze(TRX)                     → você trava o TRX, ganha energia
//  delegateresource(energia)       → você empresta energia pra outra wallet
//  [usuários fazem transfers USDT] → consomem SUA energia, não TRX deles
//  undelegateresource()            → revoga energia quando quiser
//  unfreeze(TRX)                   → inicia unstaking (14 dias)
//  withdrawexpireunfreeze()        → TRX volta pra você
//
//  Custo real de todo o ciclo = R$ 0,00
//  O TRX nunca é queimado, apenas travado temporariamente


// ════════════════════════════════════════════════════════════
// HELPER — Verificar recursos de uma wallet antes de transferir
// ════════════════════════════════════════════════════════════

function temEnergiaParaTransferir(Web3PHP $tron, string $address, int $energiaNecessaria = 30_000): bool
{
    $recursos  = $tron->network->getTronBandwidth($address);
    $disponivel = ($recursos['EnergyLimit'] ?? 0) - ($recursos['EnergyUsed'] ?? 0);
    return $disponivel >= $energiaNecessaria;
}

function temBandwidthParaTransferir(Web3PHP $tron, string $address): bool
{
    $recursos  = $tron->network->getTronBandwidth($address);
    $disponivel = ($recursos['freeNetLimit'] ?? 0) - ($recursos['freeNetUsed'] ?? 0);
    return $disponivel >= 300; // bandwidth mínimo para TRX transfer
}

// Uso:
if (temEnergiaParaTransferir($tron, $outraWallet)) {
    echo "✅ Wallet tem energia — transfer USDT sem custo" . PHP_EOL;
} else {
    echo "⚠️  Wallet sem energia — vai gastar TRX ou precisar de top-up" . PHP_EOL;
}


// ════════════════════════════════════════════════════════════
// HELPER — Função placeholder para assinar TX Tron
// (em produção use a lib tron-api ou equivalente)
// ════════════════════════════════════════════════════════════

function assinarTronTx(array $tx, string $privateKey): array
{
    // Produção: usar ECDSA secp256k1 para assinar o raw_data_hex
    // $signature = tronSign($tx['raw_data_hex'], $privateKey);
    // return array_merge($tx, ['signature' => [$signature]]);

    return array_merge($tx, ['signature' => ['PLACEHOLDER_USE_ECDSA_REAL']]);
}

function base58_to_hex(string $address): string
{
    // Produção: converter endereço Tron Base58 → hex
    // return TronAddress::toHex($address);
    return $address; // placeholder
}