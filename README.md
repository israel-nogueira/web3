# Web3PHP 🔗

**Multi-Blockchain PHP Integration Library**

Uma biblioteca PHP completa, pronta para produção, para integrar com as principais blockchains do mercado.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/web3php/web3php)](https://packagist.org/packages/web3php/web3php)

---

## 📋 Índice

- [Redes Suportadas](#-redes-suportadas)
- [Providers Suportados](#-providers-suportados)
- [Instalação](#-instalação)
- [Quickstart](#-quickstart)
- [Configuração de Providers Externos](#-configuração-de-providers-externos)
- [HD Wallets — BIP-39 + BIP-44](#-hd-wallets--bip-39--bip-44)
- [Verificar Depósitos e Blocos](#-verificar-depósitos-e-blocos)
- [Transferências com Gestão de Gas](#-transferências-com-gestão-de-gas)
- [Tron: Freeze / Unfreeze / Delegate](#-tron-freeze--unfreeze--delegate)
- [Módulos da API](#-módulos-da-api)
- [Utilitários](#-utilitários)
- [FakeChain — Testes Locais](#-fakechain--testes-locais)
- [Segurança](#-segurança)

---

## ✅ Redes Suportadas

| Rede | Tipo | Testnet |
|------|------|---------|
| Ethereum | EVM | Goerli, Sepolia |
| Polygon | EVM | Mumbai |
| BSC (Binance Smart Chain) | EVM | BSC Testnet |
| Avalanche (C-Chain) | EVM | Fuji |
| Arbitrum | EVM | — |
| Optimism | EVM | — |
| Base | EVM | — |
| Fantom | EVM | — |
| Cronos | EVM | — |
| Hardhat / Ganache | EVM local | — |
| Bitcoin | UTXO | — |
| Solana | SVM | Devnet |
| Tron | TVM | Shasta |

---

## 🔌 Providers Suportados

| Provider | Configuração |
|----------|-------------|
| **Infura** | `provider: 'infura'`, `api_key: 'KEY'` |
| **Alchemy** | `provider: 'alchemy'`, `api_key: 'KEY'` |
| **QuickNode** | `provider: 'quicknode'`, `rpc_url: 'URL_COMPLETA'` |
| **Moralis** | `provider: 'moralis'`, `api_key: 'KEY'` |
| **Nó local** | `provider: 'local'`, `rpc_url: 'http://...'` |
| **RPC Público** | (padrão, sem chave) |

---

## 📦 Instalação

```bash
composer require web3php/web3php
```

**Requisitos PHP:** 8.1+ | `ext-curl` | `ext-json` | `ext-bcmath` | `ext-gmp` | `ext-hash`

---

## 🚀 Quickstart

```php
require 'vendor/autoload.php';
use Web3PHP\Web3PHP;

$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'YOUR_KEY',
]);

echo $eth->wallet->getBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
echo $eth->latestBlock();
$tx = $eth->getTransaction('0x...');
```

---

## 🔧 Configuração de Providers Externos

```php
use Web3PHP\Web3PHP;

// Infura — Ethereum, Polygon, Arbitrum, Optimism, Avalanche
$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'SEU_INFURA_KEY',
]);

// Alchemy — Ethereum, Polygon, Arbitrum, Base, Optimism
$poly = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

// QuickNode — endpoint completo fornecido pelo painel
$qn = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'quicknode',
    'rpc_url'  => 'https://SEU_ENDPOINT.quiknode.pro/SEU_TOKEN/',
]);

// RPC Público — sem chave, OK para desenvolvimento
$bsc  = new Web3PHP(['network' => 'bsc']);
$arb  = new Web3PHP(['network' => 'arbitrum']);
$base = new Web3PHP(['network' => 'base']);
$avax = new Web3PHP(['network' => 'avalanche']);
$op   = new Web3PHP(['network' => 'optimism']);

// Nó Local — Hardhat / Geth / Ganache
$local = new Web3PHP([
    'network'  => 'hardhat',
    'provider' => 'local',
    'rpc_url'  => 'http://127.0.0.1:8545',
]);

// Bitcoin via mempool.space
$btc = new Web3PHP(['network' => 'bitcoin']);

// Solana
$sol = new Web3PHP(['network' => 'solana']);

// Tron via TronGrid
$tron = new Web3PHP([
    'network'  => 'tron',
    'provider' => 'public',
    'api_key'  => 'SEU_TRONGRID_KEY', // opcional — aumenta rate limit
]);

// Trocar de rede reutilizando as credenciais do provider
$goerli  = $eth->switchNetwork('goerli');
$polygon = $eth->switchNetwork('polygon');

// Info da conexão
print_r($eth->info());
// ['library' => 'Web3PHP', 'network' => 'ethereum', 'chain_id' => 1, ...]
```

---

## 🔐 HD Wallets — BIP-39 + BIP-44

Gera e deriva carteiras determinísticas para qualquer rede. A partir de uma única mnemônica mestre você recupera qualquer carteira a qualquer momento — sem armazenar chaves privadas.

```php
use Web3PHP\MnemonicWallet;

$mn = new MnemonicWallet();

// ── 1. Gerar mnemônica ───────────────────────────────────
$mnemonic12 = $mn->generate(12); // padrão MetaMask
$mnemonic24 = $mn->generate(24); // padrão Ledger

// ── 2. Validar ───────────────────────────────────────────
var_dump($mn->validate($mnemonic12));       // true
var_dump($mn->validate('palavra errada'));  // false

// ── 3. Derivar por rede ──────────────────────────────────
$eth  = $mn->deriveWallet($mnemonic12, index: 0, network: 'ethereum');
$btc  = $mn->deriveWallet($mnemonic12, index: 0, network: 'bitcoin');
$sol  = $mn->deriveWallet($mnemonic12, index: 0, network: 'solana');
$tron = $mn->deriveWallet($mnemonic12, index: 0, network: 'tron');
$poly = $mn->deriveWallet($mnemonic12, index: 0, network: 'polygon');

echo $eth['checksum_address'];  // 0xAb58...  (EIP-55)
echo $eth['path'];              // m/44'/60'/0'/0/0
echo $eth['private_key'];       // hex 256 bits
echo $btc['address'];           // 1LoVG... (Base58Check)
echo $btc['private_key_wif'];   // WIF
echo $sol['address'];           // Base58
echo $tron['address'];          // T... (Base58Check)

// ── 4. Múltiplas carteiras (exchange / plataforma) ───────
// Cada usuário recebe 1 endereço derivado do seu ID
$hotWallet     = $mn->deriveWallet($mnemonic24, index: 0,    network: 'polygon');
$userWallet    = $mn->deriveWallet($mnemonic24, index: 1042, network: 'polygon');

// Batch — 5 carteiras de uma vez
$wallets = $mn->deriveMultiple($mnemonic12, count: 5, startIndex: 0, network: 'ethereum');
foreach ($wallets as $w) {
    echo "[{$w['index']}] {$w['address']}  {$w['path']}" . PHP_EOL;
}

// ── 5. Multi-conta BIP-44 ────────────────────────────────
$operacional = $mn->deriveWallet($mnemonic24, 0, 'ethereum', account: 0); // m/44'/60'/0'/0/0
$reserva     = $mn->deriveWallet($mnemonic24, 0, 'ethereum', account: 1); // m/44'/60'/1'/0/0

// ── 6. Endereços de troco (Bitcoin) ─────────────────────
$externo = $mn->deriveWallet($mnemonic12, 0, 'bitcoin', change: 0); // m/44'/0'/0'/0/0
$troco   = $mn->deriveWallet($mnemonic12, 0, 'bitcoin', change: 1); // m/44'/0'/0'/1/0
```

### Armazenamento seguro da mnemônica mestre

```php
// ✅ Variável de ambiente
$masterMnemonic = getenv('MASTER_MNEMONIC');

// ✅ Arquivo encriptado fora do webroot
$encrypted = openssl_encrypt($masterMnemonic, 'aes-256-cbc', $chave, 0, $iv);
file_put_contents('/var/secrets/mnemonic.enc', $encrypted);

// ✅ Serviços de secrets — AWS Secrets Manager, HashiCorp Vault

// ❌ NUNCA: banco de dados em texto puro
// ❌ NUNCA: commitar no Git
// ❌ NUNCA: logar em arquivos de log
```

---

## 📦 Verificar Depósitos e Blocos

```php
use Web3PHP\Web3PHP;

$w3 = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

$minhaCarteira  = '0xSEU_ENDERECO';
$explorerApiKey = 'SEU_POLYGONSCAN_KEY';
$usdtContract   = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';

// ── Bloco atual ──────────────────────────────────────────
$blocoAtual = $w3->block->getLatestBlockNumber();

$bloco = $w3->block->getBlock('latest');
echo $bloco['number'];   // número
echo $bloco['datetime']; // timestamp human-readable
echo $bloco['tx_count']; // TXs no bloco
echo $bloco['gas_used'];

// Bloco com TXs expandidas
$blocoCompleto = $w3->block->getBlock('latest', fullTransactions: true);

// Range dos últimos 10 blocos
$blocos = $w3->block->getBlockRange($blocoAtual - 10, $blocoAtual);
foreach ($blocos as $b) {
    echo "#{$b['number']} — {$b['tx_count']} txs — {$b['datetime']}" . PHP_EOL;
}

// ── Histórico de TXs nativas (MATIC/ETH) ────────────────
// Requer chave do explorer (Polygonscan, Etherscan...)
$historico = $w3->wallet->getTransactionHistory(
    address:        $minhaCarteira,
    explorerApiKey: $explorerApiKey,
    page:           1,
    offset:         20
);

foreach ($historico as $tx) {
    $dir = strtolower($tx['to']) === strtolower($minhaCarteira) ? '← RECEBEU' : '→ ENVIOU';
    echo "[{$tx['datetime']}] {$dir} {$tx['value_eth']} MATIC | bloco: {$tx['block']}" . PHP_EOL;
}

// ── Transferências de token (USDT / ERC-20) ──────────────
$tokenTxs = $w3->wallet->getTokenTransfers(
    address:         $minhaCarteira,
    explorerApiKey:  $explorerApiKey,
    contractAddress: $usdtContract
);

// Filtrar apenas depósitos recebidos
$depositos = array_filter($tokenTxs, fn($tx) =>
    strtolower($tx['to'] ?? '') === strtolower($minhaCarteira)
);

foreach ($depositos as $tx) {
    echo "[{$tx['datetime']}] ← {$tx['token_amount']} USDT de {$tx['from']}" . PHP_EOL;
}

// ── Verificar confirmações de uma TX ────────────────────
$MIN_CONFIRMACOES = 12;
$tx               = $w3->getTransaction('0xSUA_TX_HASH');
$confirmacoes     = $blocoAtual - (int)$tx['block'];

if ($confirmacoes >= $MIN_CONFIRMACOES) {
    echo "✅ Depósito confirmado ({$confirmacoes} confirmações)" . PHP_EOL;
} else {
    echo "⏳ Aguardando... {$confirmacoes}/{$MIN_CONFIRMACOES}" . PHP_EOL;
}

// ── Monitor de depósitos (loop / cron) ──────────────────
// Persiste $ultimoBlocoChecado entre execuções (banco/cache)
$ultimoBlocoChecado = $blocoAtual - 50;

$txsRecentes = $w3->wallet->getTokenTransfers($minhaCarteira, $explorerApiKey, $usdtContract);

foreach ($txsRecentes as $tx) {
    $txBloco      = (int)($tx['block'] ?? 0);
    $confirmacoes = $blocoAtual - $txBloco;
    $ehDeposito   = strtolower($tx['to'] ?? '') === strtolower($minhaCarteira);
    $ehNovo       = $txBloco > $ultimoBlocoChecado;
    $confirmado   = $confirmacoes >= $MIN_CONFIRMACOES;

    if ($ehDeposito && $ehNovo && $confirmado) {
        // creditarUsuario($tx['from'], $tx['token_amount']);
        echo "💰 Novo depósito: {$tx['token_amount']} USDT | {$tx['hash']}" . PHP_EOL;
    }
}
$ultimoBlocoChecado = $blocoAtual;
```

---

## ⛽ Transferências com Gestão de Gas

Fluxo completo: verificar gas → top-up se necessário → transferir token ou nativo.

```php
use Web3PHP\Web3PHP;
use Web3PHP\Math;
use kornrunner\Ethereum\Transaction;

$w3 = new Web3PHP(['network' => 'polygon', 'provider' => 'alchemy', 'api_key' => 'KEY']);
$chainId       = 137; // Polygon
$hotWallet     = ['address' => '0xHOT',     'private_key' => 'HOT_PK'];
$depositWallet = ['address' => '0xDEPOSIT', 'private_key' => 'DEPOSIT_PK'];
$destino       = '0xDESTINO';
$usdtContract  = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';

// ── 1. Verificar saldos ──────────────────────────────────
$saldoUsdt  = $w3->wallet->getTokenBalance($depositWallet['address'], $usdtContract, 6);
$saldoMatic = $w3->wallet->getBalance($depositWallet['address']);

// ── 2. Calcular gas necessário ───────────────────────────
$gasInfo     = $w3->block->getGasInfo();
$gasEstimado = (int)$w3->block->estimateGas([
    'from' => $depositWallet['address'],
    'to'   => $usdtContract,
    'data' => '0xa9059cbb', // selector transfer(address,uint256)
]);
$gasLimit     = (int)ceil($gasEstimado * 1.25); // +25% buffer
$gasCostMatic = bcdiv(
    bcmul((string)$gasLimit, $gasInfo['gas_price_wei']),
    bcpow('10', '18'), 8
);

echo "Gas price:  {$gasInfo['gas_price_gwei']} Gwei" . PHP_EOL;
echo "Gas limit:  {$gasLimit} units" . PHP_EOL;
echo "Custo:      {$gasCostMatic} MATIC" . PHP_EOL;

// ── 3. Top-up de gas se necessário ──────────────────────
if (bccomp($saldoMatic, $gasCostMatic, 8) < 0) {

    $falta     = bcsub($gasCostMatic, $saldoMatic, 8);
    $topUp     = bcadd($falta, '0.002', 8); // +margem

    $topUpTx   = $w3->transfer->buildNativeTransfer(
        from: $hotWallet['address'], to: $depositWallet['address'], amount: (float)$topUp
    );
    $signed    = new Transaction(
        $topUpTx['nonce'], $topUpTx['gasPrice'], $topUpTx['gas'],
        $topUpTx['to'], $topUpTx['value'], $topUpTx['data']
    );
    $hash = $w3->transfer->sendRaw($signed->getRaw($hotWallet['private_key'], $chainId));
    $w3->transfer->waitForConfirmation($hash, timeout: 120);
    echo "Top-up confirmado: {$hash}" . PHP_EOL;
}

// ── 4A. Transferir TOKEN (USDT / ERC-20) ────────────────
$tokenTx = $w3->transfer->buildTokenTransfer(
    from: $depositWallet['address'], contractAddress: $usdtContract,
    to: $destino, amount: (float)$saldoUsdt, decimals: 6, customGas: $gasLimit
);
$tokenSigned = new Transaction(
    $tokenTx['nonce'], $tokenTx['gasPrice'], $tokenTx['gas'],
    $tokenTx['to'], '0x0', $tokenTx['data']
);
$tokenHash   = $w3->transfer->sendRaw($tokenSigned->getRaw($depositWallet['private_key'], $chainId));
$receipt     = $w3->transfer->waitForConfirmation($tokenHash, timeout: 180);

echo "TX:     {$tokenHash}"         . PHP_EOL;
echo "Status: {$receipt['status']}" . PHP_EOL;
echo "Bloco:  {$receipt['block']}"  . PHP_EOL;

// ── 4B. Transferir NATIVO (MATIC/ETH/BNB) ───────────────
$nativeTx = $w3->transfer->buildNativeTransfer(
    from: $depositWallet['address'], to: $destino, amount: 1.5
);
$nativeSigned = new Transaction(
    $nativeTx['nonce'], $nativeTx['gasPrice'], $nativeTx['gas'],
    $nativeTx['to'], $nativeTx['value'], $nativeTx['data']
);
$nativeHash = $w3->transfer->sendRaw($nativeSigned->getRaw($depositWallet['private_key'], $chainId));
$w3->transfer->waitForConfirmation($nativeHash);

// ── 5. Devolver troco de gas após sweep ──────────────────
// Após o sweep, o MATIC restante na deposit wallet pode voltar pra hot wallet
$sobra = $w3->wallet->getBalance($depositWallet['address']);
// buildNativeTransfer + sendRaw com o valor líquido (descontando o custo da própria TX de devolução)
```

---

## 🧊 Tron: Freeze / Unfreeze / Delegate

No Tron você **congela TRX** e recebe **Energia** ou **Bandwidth** — o equivalente ao gas. O TRX nunca é queimado: fica travado e volta integralmente após o período de unstaking (14 dias). Você ainda pode **delegar** os recursos para outra wallet, que passa a fazer transfers USDT sem precisar de TRX.

```
freeze(TRX) → ganha energia
delegateresource() → empresta energia para outra wallet
[usuário faz transfers TRC-20 consumindo SUA energia]
undelegateresource() → revoga delegação
unfreeze(TRX) → inicia unstaking (14 dias)
withdrawexpireunfreeze() → TRX volta para você
```

**Referência de custo (aproximado):**

| Ação | Energia | TRX necessário |
|------|---------|----------------|
| Transfer TRC-20 (USDT) | ~30.000 | — |
| 1 TRX frozen gera | ~420/dia | — |
| 50 transfers/dia | 1.500.000 | ~3.572 TRX frozen |

```php
use Web3PHP\Web3PHP;
use Web3PHP\Math;

$tron = new Web3PHP(['network' => 'tron', 'provider' => 'public', 'api_key' => 'TRONGRID_KEY']);

$minhaWallet = ['address' => 'TSUA_CARTEIRA', 'private_key' => 'SUA_PK'];
$outraWallet = 'TCARTEIRA_DO_USUARIO';

// ── Ver recursos atuais ──────────────────────────────────
$recursos = $tron->network->getTronBandwidth($minhaWallet['address']);
echo "Energia disponível: " . (($recursos['EnergyLimit'] ?? 0) - ($recursos['EnergyUsed'] ?? 0)) . PHP_EOL;
echo "Bandwidth livre:    " . (($recursos['freeNetLimit'] ?? 0) - ($recursos['freeNetUsed'] ?? 0)) . PHP_EOL;

// ── Freeze TRX por ENERGIA ───────────────────────────────
// resource: 'ENERGY'    → TRC-20 / smart contracts
// resource: 'BANDWIDTH' → transfers TRX simples
$freezeTx = $tron->rpc('wallet/freezebalancev2', [
    'owner_address'  => base58_to_hex($minhaWallet['address']),
    'frozen_balance' => 3572 * 1_000_000, // em SUN (1 TRX = 1.000.000 SUN)
    'resource'       => 'ENERGY',
    'visible'        => true,
]);
$freezeHash = $tron->transfer->broadcastTron(assinarTronTx($freezeTx, $minhaWallet['private_key']));

// ── Delegar energia para outra wallet ────────────────────
// A partir daqui $outraWallet faz transfers USDT sem gastar TRX
$delegarTx = $tron->rpc('wallet/delegateresource', [
    'owner_address'    => base58_to_hex($minhaWallet['address']),
    'receiver_address' => base58_to_hex($outraWallet),
    'balance'          => 100_000_000_000,
    'resource'         => 'ENERGY',
    'lock'             => false, // false = pode revogar a qualquer hora
    'visible'          => true,
]);
$tron->transfer->broadcastTron(assinarTronTx($delegarTx, $minhaWallet['private_key']));

// ── Revogar delegação ────────────────────────────────────
$revogarTx = $tron->rpc('wallet/undelegateresource', [
    'owner_address'    => base58_to_hex($minhaWallet['address']),
    'receiver_address' => base58_to_hex($outraWallet),
    'balance'          => 100_000_000_000,
    'resource'         => 'ENERGY',
    'visible'          => true,
]);
$tron->transfer->broadcastTron(assinarTronTx($revogarTx, $minhaWallet['private_key']));

// ── Unfreeze TRX (após 14 dias) ──────────────────────────
$unfreezeTx = $tron->rpc('wallet/unfreezebalancev2', [
    'owner_address'    => base58_to_hex($minhaWallet['address']),
    'unfreeze_balance' => 3572 * 1_000_000,
    'resource'         => 'ENERGY',
    'visible'          => true,
]);
$tron->transfer->broadcastTron(assinarTronTx($unfreezeTx, $minhaWallet['private_key']));
// ⚠️  Após unfreeze inicia período de unstaking de 14 dias antes do TRX ficar disponível

// ── Withdraw após unstaking ──────────────────────────────
$withdrawTx = $tron->rpc('wallet/withdrawexpireunfreeze', [
    'owner_address' => base58_to_hex($minhaWallet['address']),
    'visible'       => true,
]);
$tron->transfer->broadcastTron(assinarTronTx($withdrawTx, $minhaWallet['private_key']));

// ── Helper: verificar se tem energia antes de transferir ─
function temEnergia(Web3PHP $tron, string $address, int $min = 30_000): bool
{
    $r = $tron->network->getTronBandwidth($address);
    return (($r['EnergyLimit'] ?? 0) - ($r['EnergyUsed'] ?? 0)) >= $min;
}

if (temEnergia($tron, $outraWallet)) {
    echo "✅ Transfer USDT sem custo" . PHP_EOL;
} else {
    echo "⚠️  Sem energia — vai consumir TRX ou precisar de top-up" . PHP_EOL;
}
```

---

## 📚 Módulos da API

### `$w3->wallet` — Carteiras

| Método | Descrição |
|--------|-----------|
| `getBalance(address)` | Saldo nativo da rede |
| `getTokenBalance(wallet, contract, decimals)` | Saldo ERC-20/BEP-20/TRC-20 |
| `getNonce(address)` | Próximo nonce (EVM) |
| `getAllowance(contract, owner, spender, decimals)` | Allowance de token |
| `getTransactionHistory(address, explorerKey, page, offset)` | Histórico de TXs |
| `getTokenTransfers(address, explorerKey, contract?)` | Histórico de tokens |

### `$w3->block` — Blocos e Transações

| Método | Descrição |
|--------|-----------|
| `getLatestBlockNumber()` | Número/slot/height atual |
| `getBlock(number\|hash\|'latest', fullTx?)` | Detalhes do bloco |
| `getBlockRange(from, to)` | Range de blocos |
| `getTransaction(hash)` | Detalhes de uma TX |
| `getGasInfo()` | Gas price e base fee (EVM) |
| `estimateGas(txObject)` | Estimativa de gas (EVM) |

### `$w3->transfer` — Transferências

| Método | Descrição |
|--------|-----------|
| `buildNativeTransfer(from, to, amount, customGas?)` | Monta TX ETH/MATIC/BNB... |
| `buildTokenTransfer(from, contract, to, amount, decimals, customGas?)` | Monta TX ERC-20 |
| `sendRaw(rawHex)` | Envia TX assinada (EVM) |
| `signAndSend(privateKey, unsignedTx)` | Assina e envia (EVM) |
| `waitForConfirmation(hash, timeout)` | Aguarda mineração |
| `getBitcoinUTXOs(address)` | UTXOs disponíveis |
| `broadcastBitcoin(rawHex)` | Publica TX Bitcoin |
| `sendSolanaTransaction(base64)` | Envia TX Solana |
| `broadcastTron(signedTx)` | Envia TX Tron |

### `$w3->network` — Rede e Nó

| Método | Descrição |
|--------|-----------|
| `getChainId()` | ID da rede |
| `getNodeInfo()` | Versão, peers, sync (EVM) |
| `getMempoolSize()` | Transações pendentes (EVM) |
| `getBitcoinFeeRecommendations()` | Taxas BTC recomendadas |
| `getBitcoinMempoolStats()` | Mempool Bitcoin |
| `getSolanaEpoch()` | Info do epoch Solana |
| `getTronBandwidth(address)` | Bandwidth/energia Tron |

### `$w3->contract(address)` — Smart Contracts (EVM)

| Método | Descrição |
|--------|-----------|
| `call(signature, types, values)` | Lê função view |
| `callUint256(signature, ...)` | Lê e decodifica uint256 |
| `callAddress(signature, ...)` | Lê e decodifica address |
| `erc20Info(holderAddress)` | name, symbol, decimals, supply, balance |
| `erc721OwnerOf(tokenId)` | Dono de um NFT |
| `erc721TokenURI(tokenId)` | URI de metadata do NFT |
| `buildTransaction(from, sig, types, values)` | Monta TX não-assinada |
| `getLogs(eventSignature, fromBlock, toBlock)` | Busca eventos |

### `$w3->rpc(method, params)` — JSON-RPC direto

```php
$accounts  = $local->rpc('eth_accounts');
$txPool    = $local->rpc('txpool_content');
$proof     = $eth->rpc('eth_getProof', ['0xADDRESS', [], 'latest']);
```

---

## 🔧 Utilitários

```php
use Web3PHP\Math;
use Web3PHP\Address;

// Conversões ETH ↔ Wei
Math::etherToWei(1.5);                      // "1500000000000000000"
Math::weiToEther('1500000000000000000');     // "1.5"

// Conversões SOL ↔ Lamports
Math::solToLamports(1.5);                   // 1500000000
Math::lamportsToSol(1500000000);            // "1.5"

// Conversões BTC ↔ Satoshi
Math::satoshiToBtc(100000000);              // "1.0"

// Conversões TRX ↔ Sun
Math::sunToTrx(1000000);                    // "1.0"

// Unidades genéricas (tokens ERC-20)
Math::parseUnits('100', 6);                 // "100000000"  (100 USDT)
Math::formatUnits('100000000', 6);          // "100.000000"

// Validação de endereços
Address::isValidEVM('0x...');               // true/false
Address::isValidBitcoin('bc1...');          // true/false
Address::isValidSolana('abc...');           // true/false
Address::isValidTron('T...');               // true/false
Address::validate($addr, 'ethereum');       // valida pela rede

// ABI Encoding manual
use Web3PHP\AbiEncoder;
$selector = AbiEncoder::selector('transfer(address,uint256)');
$calldata = AbiEncoder::encodeCall('transfer(address,uint256)', ['address','uint256'], [$to, $amount]);
```

---

## 🧪 FakeChain — Testes Locais

Blockchain em memória — mesma interface do Web3PHP, zero rede, zero custo.

```php
use FakeChain\FakeChain;
use FakeChain\FakeChainHD;

// Configuração
$chain = new FakeChain([
    'network'   => 'fakechain',
    'chain_id'  => 1337,
    'symbol'    => 'ETH',
    'auto_mine' => true,
]);

// Com persistência entre execuções
$chain = new FakeChain(['storage_path' => __DIR__ . '/fakechain.json']);

// Criar carteiras e transferir
$alice = $chain->createWallet('Alice', 100.0);
$bob   = $chain->createWallet('Bob',   0.0);
$chain->faucet($bob['address'], 10.0);

$txHash = $chain->sendTransfer($alice['address'], $bob['address'], 10.0, $alice['private_key']);

// Deploy de ERC-20 fake
$usdt    = $chain->deployERC20('USDT', 'USDT', 6, 100_000.0, $alice['address']);
$txToken = $chain->sendTokenTransfer($alice['address'], $usdt, $bob['address'], 500.0, $alice['private_key'], 6);

// Snapshot / Rollback (PHPUnit)
$snap = $chain->snapshot('before_test');
// ... faz operações ...
$chain->rollback($snap); // volta ao estado anterior

// HD Wallets no FakeChain
$chainHD = new FakeChainHD(['auto_mine' => true]);
$alice   = $chainHD->createHDWallet('Alice', 100.0, wordCount: 12);
$carol   = $chainHD->importMnemonic('word1 word2 ... word12', 'Carol', 75.0);

// Mesma interface do Web3PHP — funciona nos seus testes sem mudar código
$balance = $chain->wallet->getBalance($alice['address']);
$block   = $chain->block->getLatestBlockNumber();
$tx      = $chain->block->getTransaction($txHash);
```

---

## ⚠️ Segurança

- A biblioteca **não armazena nem deriva chaves privadas** — você é responsável por isso
- Use `random_bytes()` (CSPRNG do SO) para qualquer geração de entropia
- Chaves privadas devem existir **somente em memória** durante a assinatura e ser descartadas logo após
- Nunca logue, serialize ou persista chaves privadas em texto puro
- Para operações de alto valor, considere HSM ou KMS (AWS, GCP, HashiCorp Vault)

---

## Tratamento de Erros

```php
use Web3PHP\WalletException;
use Web3PHP\NetworkException;
use Web3PHP\BlockException;
use Web3PHP\TransferException;
use Web3PHP\ContractException;
use Web3PHP\Web3Exception;

try {
    $balance = $eth->wallet->getBalance('endereco_invalido');
} catch (WalletException $e) {
    echo "Carteira: "  . $e->getMessage() . PHP_EOL;
} catch (NetworkException $e) {
    echo "Rede: "      . $e->getMessage() . PHP_EOL;
} catch (TransferException $e) {
    echo "Transferência: " . $e->getMessage() . PHP_EOL;
} catch (ContractException $e) {
    echo "Contrato: "  . $e->getMessage() . PHP_EOL;
} catch (Web3Exception $e) {
    echo "Genérico: "  . $e->getMessage() . PHP_EOL;
}
```

---

*Web3PHP — PHP 8.1+ | MIT License | [github.com/web3php/web3php](https://github.com/web3php/web3php)*