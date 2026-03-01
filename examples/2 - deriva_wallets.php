<?php

// ═══════════════════════════════════════════════════════════
//  Web3PHP — Derivação de Wallets (BIP-39 + BIP-44)
//  Aplicações práticas
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Web3PHP\MnemonicWallet;
use Web3PHP\Web3PHP;
use Web3PHP\Math;

$mn = new MnemonicWallet();


// ─── 1. GERAR E VALIDAR MNEMÔNICA ────────────────────────
$mnemonic12 = $mn->generate(12); // padrão MetaMask
$mnemonic24 = $mn->generate(24); // padrão Ledger

echo $mnemonic12 . PHP_EOL;
echo $mnemonic24 . PHP_EOL;

var_dump($mn->validate($mnemonic12));  // true
var_dump($mn->validate('palavra errada xyz')); // false


// ─── 2. SEED E MASTER KEY ────────────────────────────────
$seed      = $mn->mnemonicToSeed($mnemonic12);
$seedComSenha = $mn->mnemonicToSeed($mnemonic12, 'minha_senha');

$masterKey = $mn->seedToMasterKey($seed);
echo $masterKey['private_key'] . PHP_EOL;
echo $masterKey['chain_code']  . PHP_EOL;


// ─── 3. DERIVAR CARTEIRAS POR REDE ───────────────────────
$eth  = $mn->deriveWallet($mnemonic12, index: 0, network: 'ethereum');
$btc  = $mn->deriveWallet($mnemonic12, index: 0, network: 'bitcoin');
$sol  = $mn->deriveWallet($mnemonic12, index: 0, network: 'solana');
$tron = $mn->deriveWallet($mnemonic12, index: 0, network: 'tron');
$poly = $mn->deriveWallet($mnemonic12, index: 0, network: 'polygon');
$bsc  = $mn->deriveWallet($mnemonic12, index: 0, network: 'bsc');

// Todos do mesmo mnemonic, cada rede com seu formato
echo $eth['checksum_address']  . PHP_EOL; // 0xAb58...  (EIP-55)
echo $eth['path']              . PHP_EOL; // m/44'/60'/0'/0/0
echo $eth['private_key']       . PHP_EOL;
echo $eth['public_key']        . PHP_EOL;

echo $btc['address']           . PHP_EOL; // 1LoVG...   (Base58Check)
echo $btc['private_key_wif']   . PHP_EOL; // WIF

echo $sol['address']           . PHP_EOL; // GkSGP...   (Base58)
echo $tron['address']          . PHP_EOL; // TJmV3...   (Base58Check T...)


// ─── 4. MÚLTIPLAS CARTEIRAS DO MESMO MNEMONIC ────────────
//  Caso de uso: exchange / plataforma que gera 1 endereço por usuário
$wallets = $mn->deriveMultiple($mnemonic12, count: 5, startIndex: 0, network: 'ethereum');

foreach ($wallets as $w) {
    echo "[{$w['index']}] {$w['address']}  {$w['path']}" . PHP_EOL;
}

// Derivar pelo ID do usuário diretamente
foreach ([0, 5, 10, 100, 1042] as $userId) {
    $w = $mn->deriveWallet($mnemonic12, $userId, 'polygon');
    echo "[user #{$userId}] {$w['checksum_address']}" . PHP_EOL;
}


// ─── 5. APLICAÇÃO PRÁTICA: Auto-Custódia (Exchange) ──────
//
//  Fluxo:
//    1 mnemônica mestre gerada no servidor
//    Cada usuário recebe 1 endereço derivado do índice (userId)
//    Hot wallet = índice 0 (recebe os sweeps)
//    Chave privada NUNCA armazenada — derivada na hora de assinar

$masterMnemonic = $mn->generate(24);

// ARMAZENAMENTO SEGURO — escolha uma das opções:
// ✅ Variável de ambiente:  getenv('MASTER_MNEMONIC')
// ✅ Arquivo encriptado:    openssl_encrypt(...)
// ✅ Vault / AWS Secrets Manager
// ❌ NUNCA: banco em texto puro ou Git

$hotWallet     = $mn->deriveWallet($masterMnemonic, index: 0,    network: 'polygon');
$userWallet    = $mn->deriveWallet($masterMnemonic, index: 1042, network: 'polygon');

echo $hotWallet['checksum_address']  . PHP_EOL; // Destino dos sweeps
echo $userWallet['checksum_address'] . PHP_EOL; // Endereço de depósito do usuário


// ─── 6. APLICAÇÃO PRÁTICA: Verificar Depósito ────────────
$w3 = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

$usdtContract = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
$usdtDecimals = 6;

$saldoUsdt = $w3->wallet->getTokenBalance(
    $userWallet['checksum_address'],
    $usdtContract,
    $usdtDecimals
);

echo "Saldo USDT do usuário: {$saldoUsdt}" . PHP_EOL;


// ─── 7. APLICAÇÃO PRÁTICA: Sweep (varrer para hot wallet) ─
//  Quando o saldo supera o mínimo, varremos para a hot wallet
//  A chave privada é derivada na hora, usada em memória, descartada

if ((float)$saldoUsdt >= 1.0) {

    $privateKey = $userWallet['private_key']; // derivada agora, não armazenada

    $amountWei = Math::parseUnits($saldoUsdt, $usdtDecimals);

    $tx = $w3->contract($usdtContract)->buildTransaction(
        from:      $userWallet['checksum_address'],
        signature: 'transfer(address,uint256)',
        types:     ['address', 'uint256'],
        values:    [$hotWallet['checksum_address'], $amountWei]
    );

    // Assinar externamente com $privateKey e enviar via:
    // $w3->transfer->sendRaw($rawSignedHex);

    unset($privateKey); // descarta da memória após uso
}


// ─── 8. APLICAÇÃO PRÁTICA: Multi-Sig / Multi-Conta ────────
//  account=0 → conta operacional
//  account=1 → conta reserva / cold
//  account=2 → conta de testes

$operacional = $mn->deriveWallet($masterMnemonic, 0, 'ethereum', account: 0);
$reserva     = $mn->deriveWallet($masterMnemonic, 0, 'ethereum', account: 1);
$testes      = $mn->deriveWallet($masterMnemonic, 0, 'ethereum', account: 2);

echo $operacional['path'] . PHP_EOL; // m/44'/60'/0'/0/0
echo $reserva['path']     . PHP_EOL; // m/44'/60'/1'/0/0
echo $testes['path']      . PHP_EOL; // m/44'/60'/2'/0/0


// ─── 9. APLICAÇÃO PRÁTICA: Endereços de Troco (change) ────
//  change=0 → endereços externos (receber pagamentos)
//  change=1 → endereços de troco internos (Bitcoin / privacidade)

$externo = $mn->deriveWallet($mnemonic12, 0, 'bitcoin', change: 0);
$troco   = $mn->deriveWallet($mnemonic12, 0, 'bitcoin', change: 1);

echo $externo['path'] . PHP_EOL; // m/44'/0'/0'/0/0
echo $troco['path']   . PHP_EOL; // m/44'/0'/0'/1/0


// ─── 10. TRATAMENTO DE ERROS ─────────────────────────────
try {
    $mn->deriveWallet('mnemonica invalida aqui', 0, 'ethereum');
} catch (\InvalidArgumentException $e) {
    echo "Erro: " . $e->getMessage() . PHP_EOL;
}