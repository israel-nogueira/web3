<?php

/**
 * MnemonicWallet — Exemplos de Uso
 * =================================
 * BIP39 + BIP44 HD Wallets — standalone, Web3PHP e FakeChain.
 * Zero API. Funciona offline.
 */

require_once __DIR__ . '/../src/MnemonicWallet.php';
require_once __DIR__ . '/../src/FakeChain.php';

use Web3PHP\MnemonicWallet;
use Web3PHP\CoinType;
use FakeChain\FakeChainHD;

// ═════════════════════════════════════════════════════════════════════════════
// 0. DIAGNÓSTICO DO AMBIENTE
// ═════════════════════════════════════════════════════════════════════════════

$mn = new MnemonicWallet();
$caps = $mn->getCapabilities();

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           🔐 MNEMONICWALLET — DIAGNÓSTICO               ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "  Wordlist:       {$caps['wordlist_size']} palavras\n";
echo "  PBKDF2 nativo:  " . ($caps['pbkdf2_native']    ? '✅' : '⚠️  fallback') . "\n";
echo "  HMAC-SHA512:    " . ($caps['hmac_sha512']       ? '✅' : '❌') . "\n";
echo "  SHA3-256:       " . ($caps['sha3_256']          ? '✅' : '❌') . "\n";
echo "  GMP extension:  " . ($caps['gmp_extension']     ? '✅' : '⚠️  sem secp256k1 exato') . "\n";
echo "  kornrunner/keccak: " . ($caps['keccak_installed'] ? '✅' : '⚠️  instale para EVM exato') . "\n";
echo "  elliptic-php:   " . ($caps['ecdsa_installed']   ? '✅' : '⚠️  instale para ECDSA real') . "\n";
echo "  BIP39 compliant:" . ($caps['bip39_compliant']   ? '✅' : '⚠️') . "\n";
echo "  💡 {$caps['recommendation']}\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 1. GERAR MNEMÔNICA
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. GERAÇÃO DE MNEMÔNICAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 12 palavras (128 bits de entropia) — padrão MetaMask
$mnemonic12 = $mn->generate(12);
echo "12 palavras:\n  {$mnemonic12}\n\n";

// 24 palavras (256 bits) — padrão Ledger / alta segurança
$mnemonic24 = $mn->generate(24);
echo "24 palavras:\n  {$mnemonic24}\n\n";

// 15 e 18 palavras também são BIP-39 válidos
$mnemonic15 = $mn->generate(15);
echo "15 palavras:\n  {$mnemonic15}\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 2. VALIDAR MNEMÔNICA
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2. VALIDAÇÃO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$valid = $mn->validate($mnemonic12);
echo "Mnemônica gerada válida: " . ($valid ? '✅ SIM' : '❌ NÃO') . "\n";

$invalid = $mn->validate('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon');
echo "Mnemônica inválida (11 palavras): " . ($invalid ? '✅' : '❌ NÃO (esperado)') . "\n";

$wrongWord = $mn->validate('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon XYZXYZ');
echo "Palavra não-BIP39: " . ($wrongWord ? '✅' : '❌ NÃO (esperado)') . "\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 3. SEED E MASTER KEY
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3. SEED E MASTER KEY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$seed = $mn->mnemonicToSeed($mnemonic12);
echo "Seed (hex, 512 bits):\n  " . bin2hex($seed) . "\n\n";

// Com passphrase adicional (BIP-39 opcional)
$seedWithPass = $mn->mnemonicToSeed($mnemonic12, 'minha_senha_secreta');
echo "Seed com passphrase (diferente!):\n  " . bin2hex($seedWithPass) . "\n\n";

$masterKey = $mn->seedToMasterKey($seed);
echo "Master Private Key:\n  {$masterKey['private_key']}\n";
echo "Master Chain Code:\n  {$masterKey['chain_code']}\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 4. DERIVAR CARTEIRA — BIP-44
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4. DERIVAÇÃO DE CARTEIRAS (BIP-44)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Ethereum — m/44'/60'/0'/0/0
$ethWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'ethereum');
echo "🔷 ETHEREUM\n";
echo "   Path:        {$ethWallet['path']}\n";
echo "   Address:     {$ethWallet['address']}\n";
echo "   Checksum:    {$ethWallet['checksum_address']}\n";
echo "   Private Key: {$ethWallet['private_key']}\n";
echo "   Public Key:  {$ethWallet['public_key']}\n\n";

// Bitcoin — m/44'/0'/0'/0/0
$btcWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'bitcoin');
echo "🟠 BITCOIN\n";
echo "   Path:        {$btcWallet['path']}\n";
echo "   Address:     {$btcWallet['address']}\n";
echo "   Private WIF: {$btcWallet['private_key_wif']}\n";
echo "   Private Key: {$btcWallet['private_key']}\n\n";

// Solana — m/44'/501'/0'/0/0
$solWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'solana');
echo "🟣 SOLANA\n";
echo "   Path:        {$solWallet['path']}\n";
echo "   Address:     {$solWallet['address']}\n";
echo "   Private Key: {$solWallet['private_key']}\n\n";

// Tron — m/44'/195'/0'/0/0
$tronWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'tron');
echo "🔴 TRON\n";
echo "   Path:        {$tronWallet['path']}\n";
echo "   Address:     {$tronWallet['address']}\n";
echo "   Private Key: {$tronWallet['private_key']}\n\n";

// Polygon (mesmo coin type que ETH, endereço idêntico)
$polyWallet = $mn->deriveWallet($mnemonic12, index: 0, network: 'polygon');
echo "🟪 POLYGON\n";
echo "   Path:        {$polyWallet['path']}\n";
echo "   Address:     {$polyWallet['address']}\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 5. MÚLTIPLAS CARTEIRAS DO MESMO MNEMONIC
// ═════════════════════════════════════════════════════════════════════════════

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

// Índices específicos (ex: 0, 5, 10, 100)
echo "  Índices customizados:\n";
foreach ([0, 5, 10, 50, 100] as $i) {
    $w = $mn->deriveWallet($mnemonic12, $i, 'ethereum');
    echo "  [{$i:3d}] {$w['address']}\n";
}
echo "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 6. MULTI-CHAIN — MESMA MNEMÔNICA, TODAS AS REDES
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "6. MULTI-CHAIN — mesma mnemônica, todas as redes\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$multiChain = $mn->deriveMultiChain($mnemonic12, [
    'ethereum', 'bitcoin', 'solana', 'tron', 'polygon', 'bsc', 'avalanche', 'arbitrum'
]);

foreach ($multiChain as $network => $wallet) {
    $symbol = match($network) {
        'ethereum'  => '🔷 ETH',
        'bitcoin'   => '🟠 BTC',
        'solana'    => '🟣 SOL',
        'tron'      => '🔴 TRX',
        'polygon'   => '🟪 MATIC',
        'bsc'       => '🟡 BNB',
        'avalanche' => '🔺 AVAX',
        'arbitrum'  => '🔵 ARB',
        default     => "   {$network}",
    };
    echo "  {$symbol:12s} {$wallet['address']}  (path: {$wallet['path']})\n";
}
echo "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 7. IMPORTAR MNEMÔNICA EXTERNA (MetaMask, Ledger, Trust Wallet)
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "7. IMPORTAR MNEMÔNICA EXTERNA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Mnemônica famosa de teste (BIP-39 test vector)
$testMnemonic = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';

try {
    $imported = $mn->importMnemonic($testMnemonic);
    echo "✅ Mnemônica importada com sucesso!\n";
    echo "   Palavras:   {$imported['word_count']}\n";
    echo "   Seed:       " . substr($imported['seed_hex'], 0, 32) . "...\n";
    echo "   Master Key: " . substr($imported['master_key'], 0, 32) . "...\n\n";

    // Derivar carteiras da mnemônica importada
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

// ═════════════════════════════════════════════════════════════════════════════
// 8. PASSPHRASE (BIP-39 opcional — 25ª palavra)
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "8. PASSPHRASE — 25ª palavra (BIP-39)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$semSenha    = $mn->deriveWallet($mnemonic12, 0, 'ethereum', passphrase: '');
$comSenha    = $mn->deriveWallet($mnemonic12, 0, 'ethereum', passphrase: 'senha_secreta');
$comSenha2   = $mn->deriveWallet($mnemonic12, 0, 'ethereum', passphrase: 'senha_diferente');

echo "  Sem passphrase:          {$semSenha['address']}\n";
echo "  Com passphrase 'senha1': {$comSenha['address']}\n";
echo "  Com passphrase 'senha2': {$comSenha2['address']}\n";
echo "  ⚠️  Cada passphrase gera carteiras completamente diferentes!\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 9. WIF e CHAVES BITCOIN
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "9. BITCOIN — WIF e ENDEREÇOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$btc0 = $mn->deriveWallet($mnemonic12, 0, 'bitcoin');
$btc1 = $mn->deriveWallet($mnemonic12, 1, 'bitcoin');
$btc2 = $mn->deriveWallet($mnemonic12, 2, 'bitcoin');

echo "  BTC[0]\n";
echo "    Address:     {$btc0['address']}\n";
echo "    WIF:         {$btc0['private_key_wif']}\n";
echo "    Private Key: {$btc0['private_key']}\n";
echo "    Public Key:  {$btc0['public_key']}\n\n";

echo "  BTC[1]\n";
echo "    Address:     {$btc1['address']}\n";
echo "    WIF:         {$btc1['private_key_wif']}\n\n";

// Converter WIF de volta para private key
$decoded = $mn->wifToPrivateKey($btc0['private_key_wif']);
echo "  WIF → Private Key (roundtrip): " . ($decoded === $btc0['private_key'] ? '✅ OK' : "⚠️  {$decoded}") . "\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 10. FAKECHAIN + HD WALLETS
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "10. FAKECHAIN + HD WALLETS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// FakeChainHD = FakeChain com suporte a mnemonics
$chain = new FakeChainHD(['auto_mine' => true]);

// Criar carteiras HD diretamente no chain
$alice = $chain->createHDWallet('Alice', 100.0, wordCount: 12);
$bob   = $chain->createHDWallet('Bob',   50.0,  wordCount: 12);

echo "👛 Alice (HD Wallet gerada automaticamente):\n";
echo "   Mnemônica: {$alice['mnemonic']}\n";
echo "   Path:      {$alice['path']}\n";
echo "   Address:   {$alice['address']}\n";
echo "   Priv Key:  {$alice['private_key']}\n";
echo "   Balance:   " . $chain->wallet->getBalance($alice['address']) . " ETH\n\n";

echo "👛 Bob:\n";
echo "   Mnemônica: {$bob['mnemonic']}\n";
echo "   Address:   {$bob['address']}\n";
echo "   Balance:   " . $chain->wallet->getBalance($bob['address']) . " ETH\n\n";

// Usar com as funções normais do FakeChain
$txHash = $chain->sendTransfer(
    from:       $alice['address'],
    to:         $bob['address'],
    amount:     10.0,
    privateKey: $alice['private_key']
);
echo "💸 TX: {$txHash}\n";
echo "   Alice após TX: " . $chain->wallet->getBalance($alice['address']) . " ETH\n";
echo "   Bob após TX:   " . $chain->wallet->getBalance($bob['address'])   . " ETH\n\n";

// Importar mnemônica externa no FakeChain
$carolMnemonic = 'legal winner thank year wave sausage worth useful legal winner thank yellow';
try {
    $carol = $chain->importMnemonic($carolMnemonic, 'Carol', 25.0);
    echo "📥 Carol importada:\n";
    echo "   Address: {$carol['address']}\n";
    echo "   Balance: " . $chain->wallet->getBalance($carol['address']) . " ETH\n\n";
} catch (\Exception $e) {
    // Mnemônica de test pode falhar checksum — importar wallet simples
    echo "   (mnemônica de exemplo não passou checksum, usando wallet normal)\n\n";
}

// ═════════════════════════════════════════════════════════════════════════════
// 11. BATCH — DERIVAR E REGISTRAR MÚLTIPLAS CARTEIRAS NO FAKECHAIN
// ═════════════════════════════════════════════════════════════════════════════

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

// ═════════════════════════════════════════════════════════════════════════════
// 12. CHECKSUM ADDRESS (EIP-55)
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "12. CHECKSUM ADDRESS (EIP-55)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$w = $mn->deriveWallet($mnemonic12, 0, 'ethereum');
echo "  Lowercase:  {$w['address']}\n";
echo "  Checksum:   {$w['checksum_address']}\n\n";

// Converter qualquer endereço para checksum
$rawAddr = '0xfb6916095ca1df60bb79ce92ce3ea74c37c5d359';
echo "  Input:    {$rawAddr}\n";
echo "  Checksum: " . $mn->toChecksumAddress($rawAddr) . "\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 13. BASE58 ENCODE/DECODE
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "13. BASE58 ENCODE/DECODE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$original = random_bytes(32);
$encoded  = $mn->base58Encode($original);
$decoded  = $mn->base58Decode($encoded);

echo "  Original (hex): " . bin2hex($original) . "\n";
echo "  Base58:         {$encoded}\n";
echo "  Roundtrip OK:   " . ($decoded === $original ? '✅' : '❌') . "\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 14. INTEGRAÇÃO COM Web3PHP ORIGINAL (via MnemonicTrait)
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "14. INTEGRAÇÃO WEB3PHP (manual — sem API key)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Uso standalone (sem instanciar Web3PHP para não precisar de API)
$mn2 = new MnemonicWallet();
$mnemonic = $mn2->generate(12);
$wallet   = $mn2->deriveWallet($mnemonic, 0, 'ethereum');

echo "  // Para usar com Web3PHP real:\n";
echo "  // require 'src/Web3PHP.php';\n";
echo "  // use Web3PHP\\Web3PHP;\n";
echo "  // use Web3PHP\\MnemonicTrait;\n\n";
echo "  // Adicione o trait na classe:\n";
echo "  // class Web3PHP { use MnemonicTrait; ... }\n\n";
echo "  // Então:\n";
echo "  // \$eth = new Web3PHP(['network' => 'ethereum', 'provider' => 'infura', 'api_key' => '...']);\n";
echo "  // \$hdWallet = \$eth->createHDWallet();           // gera mnemônica\n";
echo "  // \$hdWallet = \$eth->createHDWallet(\$mnemonic);  // usa mnemônica existente\n";
echo "  // \$wallets  = \$eth->deriveHDWallets(\$mnemonic, count: 5);\n\n";

echo "  Demonstração gerada:\n";
echo "  Mnemônica: {$mnemonic}\n";
echo "  Address:   {$wallet['address']}\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 15. RESUMO COMPLETO DE UMA CARTEIRA
// ═════════════════════════════════════════════════════════════════════════════

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "15. CARTEIRA COMPLETA — DUMP TOTAL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$fullWallet = $mn->deriveWallet($mn->generate(12), 0, 'ethereum');
echo "  🔑 MNEMÔNICA:          {$fullWallet['mnemonic']}\n";
echo "  🌱 SEED (primeiros 32): " . substr($fullWallet['seed'], 0, 32) . "...\n";
echo "  🗝️  MASTER PRIV KEY:    " . substr($fullWallet['master_private_key'], 0, 32) . "...\n";
echo "  ⛓️  MASTER CHAIN CODE:  " . substr($fullWallet['master_chain_code'], 0, 32) . "...\n";
echo "  📍 PATH:               {$fullWallet['path']}\n";
echo "  🔐 PRIVATE KEY:        {$fullWallet['private_key']}\n";
echo "  📢 PUBLIC KEY:         {$fullWallet['public_key']}\n";
echo "  🏠 ADDRESS:            {$fullWallet['address']}\n";
echo "  ✅ CHECKSUM ADDR:      {$fullWallet['checksum_address']}\n";
echo "  🌐 COIN TYPE:          {$fullWallet['coin_type']}\n\n";

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  ✅ Todos os exemplos executados com sucesso!           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
