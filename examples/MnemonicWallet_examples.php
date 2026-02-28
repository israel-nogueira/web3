<?php

/**
 * MnemonicWallet — Exemplos de Uso Completos
 * ============================================
 * BIP39 + BIP44 HD Wallets — standalone, Web3PHP e FakeChain.
 * Funciona 100% offline.
 *
 * Requer:
 *   composer require kornrunner/keccak simplito/elliptic-php
 *
 * Rodar:
 *   php exemplo_mnemonic.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/MnemonicWallet.php';
require_once __DIR__ . '/src/FakeChain.php';

use Web3PHP\MnemonicWallet;
use FakeChain\FakeChainHD;

$mn = new MnemonicWallet();

// ─────────────────────────────────────────────────────────────────────────────
// 1. GERAR MNEMÔNICA
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. GERAÇÃO DE MNEMÔNICAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$mnemonic12 = $mn->generate(12); // padrão MetaMask
$mnemonic24 = $mn->generate(24); // padrão Ledger
$mnemonic15 = $mn->generate(15); // também BIP-39 válido

echo "12 palavras:\n  {$mnemonic12}\n\n";
echo "24 palavras:\n  {$mnemonic24}\n\n";
echo "15 palavras:\n  {$mnemonic15}\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 2. VALIDAR MNEMÔNICA
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2. VALIDAÇÃO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Mnemônica gerada válida:   " . ($mn->validate($mnemonic12) ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Mnemônica com 11 palavras: " . ($mn->validate('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon') ? '✅' : '❌ NÃO (esperado)') . "\n";
echo "Palavra não-BIP39:         " . ($mn->validate('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon XYZXYZ') ? '✅' : '❌ NÃO (esperado)') . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 3. SEED E MASTER KEY
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3. SEED E MASTER KEY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$seed = $mn->mnemonicToSeed($mnemonic12);
echo "Seed (hex, 512 bits):\n  " . bin2hex($seed) . "\n\n";

$seedWithPass = $mn->mnemonicToSeed($mnemonic12, 'minha_senha_secreta');
echo "Seed com passphrase (diferente!):\n  " . bin2hex($seedWithPass) . "\n\n";

$masterKey = $mn->seedToMasterKey($seed);
echo "Master Private Key:\n  {$masterKey['private_key']}\n";
echo "Master Chain Code:\n  {$masterKey['chain_code']}\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 4. DERIVAR CARTEIRAS — BIP-44
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4. DERIVAÇÃO DE CARTEIRAS (BIP-44)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$ethWallet  = $mn->deriveWallet($mnemonic12, index: 0, network: 'ethereum');
$btcWallet  = $mn->deriveWallet($mnemonic12, index: 0, network: 'bitcoin');
$solWallet  = $mn->deriveWallet($mnemonic12, index: 0, network: 'solana');
$tronWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'tron');
$polyWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'polygon');

echo "🔷 ETHEREUM\n";
echo "   Path:        {$ethWallet['path']}\n";
echo "   Address:     {$ethWallet['address']}\n";
echo "   Checksum:    {$ethWallet['checksum_address']}\n";
echo "   Private Key: {$ethWallet['private_key']}\n";
echo "   Public Key:  {$ethWallet['public_key']}\n\n";

echo "🟠 BITCOIN\n";
echo "   Path:        {$btcWallet['path']}\n";
echo "   Address:     {$btcWallet['address']}\n";
echo "   Private WIF: {$btcWallet['private_key_wif']}\n";
echo "   Private Key: {$btcWallet['private_key']}\n\n";

echo "🟣 SOLANA\n";
echo "   Path:        {$solWallet['path']}\n";
echo "   Address:     {$solWallet['address']}\n";
echo "   Private Key: {$solWallet['private_key']}\n\n";

echo "🔴 TRON\n";
echo "   Path:        {$tronWallet['path']}\n";
echo "   Address:     {$tronWallet['address']}\n";
echo "   Private Key: {$tronWallet['private_key']}\n\n";

echo "🟪 POLYGON\n";
echo "   Path:        {$polyWallet['path']}\n";
echo "   Address:     {$polyWallet['address']}\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 5. MÚLTIPLAS CARTEIRAS DO MESMO MNEMONIC
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "5. MÚLTIPLAS CARTEIRAS (HD Wallet — índices 0-4)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$wallets = $mn->deriveMultiple($mnemonic12, count: 5, startIndex: 0, network: 'ethereum');

echo sprintf("  %-5s %-45s %s\n", 'Index', 'Address', 'Path');
echo "  " . str_repeat('─', 80) . "\n";
foreach ($wallets as $w) {
    echo sprintf("  %-5d %-45s %s\n", $w['index'], $w['address'], $w['path']);
}
echo "\n";

echo "  Índices customizados:\n";
foreach ([0, 5, 10, 50, 100] as $i) {
    $w = $mn->deriveWallet($mnemonic12, $i, 'ethereum');
    echo sprintf("  [%3d] %s\n", $i, $w['address']);
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 6. MULTI-CHAIN — MESMA MNEMÔNICA, TODAS AS REDES
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6. MULTI-CHAIN — mesma mnemônica, todas as redes\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$multiChain = $mn->deriveMultiChain($mnemonic12, [
    'ethereum', 'bitcoin', 'solana', 'tron', 'polygon', 'bsc', 'avalanche', 'arbitrum'
]);

$labels = [
    'ethereum'  => '🔷 ETH ', 'bitcoin'   => '🟠 BTC ', 'solana'    => '🟣 SOL ',
    'tron'      => '🔴 TRX ', 'polygon'   => '🟪 MATIC', 'bsc'       => '🟡 BNB ',
    'avalanche' => '🔺 AVAX', 'arbitrum'  => '🔵 ARB ',
];
foreach ($multiChain as $network => $wallet) {
    echo "  {$labels[$network]}  {$wallet['address']}  (path: {$wallet['path']})\n";
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 7. IMPORTAR MNEMÔNICA EXTERNA (MetaMask, Ledger, Trust Wallet)
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "7. IMPORTAR MNEMÔNICA EXTERNA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$testMnemonic = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';

try {
    $imported = $mn->importMnemonic($testMnemonic);
    echo "✅ Mnemônica importada com sucesso!\n";
    echo "   Palavras:   {$imported['word_count']}\n";
    echo "   Seed:       " . substr($imported['seed_hex'], 0, 32) . "...\n";
    echo "   Master Key: " . substr($imported['master_key'], 0, 32) . "...\n\n";

    $w0 = $mn->deriveWallet($testMnemonic, 0, 'ethereum');
    $w1 = $mn->deriveWallet($testMnemonic, 1, 'ethereum');
    echo "   ETH wallet[0]: {$w0['address']}\n";
    echo "   ETH wallet[1]: {$w1['address']}\n\n";
} catch (\InvalidArgumentException $e) {
    echo "❌ {$e->getMessage()}\n\n";
}

// Erro esperado: mnemônica inválida
try {
    $mn->importMnemonic('palavra invalida aqui mesmo nao existe nada abc');
} catch (\InvalidArgumentException $e) {
    echo "❌ Esperado: {$e->getMessage()}\n\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. PASSPHRASE (BIP-39 opcional — 25ª palavra)
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "8. PASSPHRASE — 25ª palavra (BIP-39)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$semSenha  = $mn->deriveWallet($mnemonic12, 0, 'ethereum', passphrase: '');
$comSenha  = $mn->deriveWallet($mnemonic12, 0, 'ethereum', passphrase: 'senha_secreta');
$comSenha2 = $mn->deriveWallet($mnemonic12, 0, 'ethereum', passphrase: 'senha_diferente');

echo "  Sem passphrase:          {$semSenha['address']}\n";
echo "  Com passphrase 'senha1': {$comSenha['address']}\n";
echo "  Com passphrase 'senha2': {$comSenha2['address']}\n";
echo "  ⚠️  Cada passphrase gera carteiras completamente diferentes!\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 9. BITCOIN — WIF e ENDEREÇOS
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "9. BITCOIN — WIF e ENDEREÇOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$btc0 = $mn->deriveWallet($mnemonic12, 0, 'bitcoin');
$btc1 = $mn->deriveWallet($mnemonic12, 1, 'bitcoin');

echo "  BTC[0]\n";
echo "    Address:     {$btc0['address']}\n";
echo "    WIF:         {$btc0['private_key_wif']}\n";
echo "    Private Key: {$btc0['private_key']}\n";
echo "    Public Key:  {$btc0['public_key']}\n\n";

echo "  BTC[1]\n";
echo "    Address:     {$btc1['address']}\n";
echo "    WIF:         {$btc1['private_key_wif']}\n\n";

$decoded = $mn->wifToPrivateKey($btc0['private_key_wif']);
echo "  WIF → Private Key (roundtrip): " . ($decoded === $btc0['private_key'] ? '✅ OK' : "⚠️  {$decoded}") . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 10. FAKECHAIN + HD WALLETS
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "10. FAKECHAIN + HD WALLETS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$chain = new FakeChainHD(['auto_mine' => true]);

$alice = $chain->createHDWallet('Alice', 100.0, wordCount: 12);
$bob   = $chain->createHDWallet('Bob',   50.0,  wordCount: 12);

echo "👛 Alice:\n";
echo "   Mnemônica: {$alice['mnemonic']}\n";
echo "   Path:      {$alice['path']}\n";
echo "   Address:   {$alice['address']}\n";
echo "   Priv Key:  {$alice['private_key']}\n";
echo "   Balance:   " . $chain->wallet->getBalance($alice['address']) . " ETH\n\n";

echo "👛 Bob:\n";
echo "   Mnemônica: {$bob['mnemonic']}\n";
echo "   Address:   {$bob['address']}\n";
echo "   Balance:   " . $chain->wallet->getBalance($bob['address']) . " ETH\n\n";

$txHash = $chain->sendTransfer(
    from:       $alice['address'],
    to:         $bob['address'],
    amount:     10.0,
    privateKey: $alice['private_key']
);
echo "💸 TX: {$txHash}\n";
echo "   Alice após TX: " . $chain->wallet->getBalance($alice['address']) . " ETH\n";
echo "   Bob após TX:   " . $chain->wallet->getBalance($bob['address'])   . " ETH\n\n";

$carolMnemonic = 'legal winner thank year wave sausage worth useful legal winner thank yellow';
try {
    $carol = $chain->importMnemonic($carolMnemonic, 'Carol', 25.0);
    echo "📥 Carol importada:\n";
    echo "   Address: {$carol['address']}\n";
    echo "   Balance: " . $chain->wallet->getBalance($carol['address']) . " ETH\n\n";
} catch (\Exception $e) {
    echo "   (mnemônica de exemplo não passou checksum BIP-39)\n\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// 11. BATCH — DERIVAR E REGISTRAR MÚLTIPLAS CARTEIRAS NO FAKECHAIN
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "11. BATCH — derivar e registrar 5 carteiras de uma mnemônica\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$masterMnemonic = $mn->generate(12);
echo "Mnemônica mestre: {$masterMnemonic}\n\n";

$batchWallets = $chain->hd()->deriveAndRegister(
    mnemonic:       $masterMnemonic,
    count:          5,
    initialBalance: 10.0,
    startIndex:     0
);

echo sprintf("  %-5s %-45s %s\n", 'Idx', 'Address', 'Balance');
echo "  " . str_repeat('─', 70) . "\n";
foreach ($batchWallets as $w) {
    $bal = $chain->wallet->getBalance($w['address']);
    echo sprintf("  %-5d %-45s %s ETH\n", $w['index'], $w['address'], $bal);
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 12. CHECKSUM ADDRESS (EIP-55)
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "12. CHECKSUM ADDRESS (EIP-55)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$w = $mn->deriveWallet($mnemonic12, 0, 'ethereum');
echo "  Lowercase:  {$w['address']}\n";
echo "  Checksum:   {$w['checksum_address']}\n\n";

$rawAddr = '0xfb6916095ca1df60bb79ce92ce3ea74c37c5d359';
echo "  Input:    {$rawAddr}\n";
echo "  Checksum: " . $mn->toChecksumAddress($rawAddr) . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 13. BASE58 ENCODE/DECODE
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "13. BASE58 ENCODE/DECODE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$original = random_bytes(32);
$encoded  = $mn->base58Encode($original);
$decoded  = $mn->base58Decode($encoded);

echo "  Original (hex): " . bin2hex($original) . "\n";
echo "  Base58:         {$encoded}\n";
echo "  Roundtrip OK:   " . ($decoded === $original ? '✅' : '❌') . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 14. INTEGRAÇÃO COM Web3PHP (via MnemonicTrait)
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "14. INTEGRAÇÃO WEB3PHP (via MnemonicTrait)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Para ativar, adicione `use MnemonicTrait;` na classe Web3PHP e use assim:
//
//   $eth = new Web3PHP(['network' => 'ethereum', 'provider' => 'infura', 'api_key' => '...']);
//   $hdWallet = $eth->createHDWallet();              // gera mnemônica + deriva index 0
//   $hdWallet = $eth->createHDWallet($mnemonic);     // usa mnemônica existente
//   $wallets  = $eth->deriveHDWallets($mnemonic, 5); // deriva índices 0-4

$mn2      = new MnemonicWallet();
$mnemonic = $mn2->generate(12);
$wallet   = $mn2->deriveWallet($mnemonic, 0, 'ethereum');

echo "  Mnemônica: {$mnemonic}\n";
echo "  Address:   {$wallet['address']}\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// 15. CARTEIRA COMPLETA — DUMP TOTAL
// ─────────────────────────────────────────────────────────────────────────────

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "15. CARTEIRA COMPLETA — DUMP TOTAL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$full = $mn->deriveWallet($mn->generate(12), 0, 'ethereum');
echo "  🔑 MNEMÔNICA:          {$full['mnemonic']}\n";
echo "  🌱 SEED (início):      " . substr($full['seed'], 0, 40) . "...\n";
echo "  🗝️  MASTER PRIV KEY:   " . substr($full['master_private_key'], 0, 40) . "...\n";
echo "  ⛓️  MASTER CHAIN CODE: " . substr($full['master_chain_code'], 0, 40) . "...\n";
echo "  📍 PATH:               {$full['path']}\n";
echo "  🔐 PRIVATE KEY:        {$full['private_key']}\n";
echo "  📢 PUBLIC KEY:         {$full['public_key']}\n";
echo "  🏠 ADDRESS:            {$full['address']}\n";
echo "  ✅ CHECKSUM ADDR:      {$full['checksum_address']}\n";
echo "  🌐 COIN TYPE:          {$full['coin_type']}\n\n";

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  ✅ Todos os exemplos executados com sucesso!           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";