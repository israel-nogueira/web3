# Web3PHP 🔗

**Multi-Blockchain PHP Integration Library**

Uma biblioteca PHP completa, pronta para produção, para integrar com as principais blockchains do mercado.

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
| **QuickNode** | `provider: 'quicknode'`, `rpc_url: 'URL'` |
| **Moralis** | `provider: 'moralis'`, `api_key: 'KEY'` |
| **Node local** | `provider: 'local'`, `rpc_url: 'http://...'` |
| **RPC Público** | (padrão, sem chave) |

---

## 📦 Instalação

```bash
# Apenas o arquivo principal (zero deps para leitura de dados)
# Copie Web3PHP.php para seu projeto e use diretamente.

# Dependências opcionais para assinatura e endereços checksum:
composer require kornrunner/keccak
composer require kornrunner/ethereum-offline-raw-tx
```

**Requisitos PHP:** 8.1+ | `ext-curl` | `ext-json` | `ext-bcmath` (recomendado)

---

## 🚀 Quickstart

```php
require 'Web3PHP.php';
use Web3PHP\Web3PHP;

// Ethereum via Infura
$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'YOUR_KEY',
]);

// Saldo
echo $eth->wallet->getBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');

// Último bloco
echo $eth->latestBlock();

// Detalhes de transação
$tx = $eth->getTransaction('0x...');

// Bitcoin
$btc = new Web3PHP(['network' => 'bitcoin']);
echo $btc->wallet->getBalance('bc1q...');

// Solana
$sol = new Web3PHP(['network' => 'solana']);
echo $sol->wallet->getBalance('9WzD...');
```

---

## 📚 Módulos

### `$w3->wallet` — Carteiras

| Método | Descrição |
|--------|-----------|
| `getBalance(address)` | Saldo nativo da rede |
| `getTokenBalance(wallet, contract, decimals)` | Saldo ERC-20/BEP-20 |
| `getNonce(address)` | Próximo nonce (EVM) |
| `getAllowance(contract, owner, spender, decimals)` | Allowance de token |
| `getTransactionHistory(address, explorerKey, ...)` | Histórico de TXs |
| `getTokenTransfers(address, explorerKey, contract?)` | Histórico de tokens |

### `$w3->block` — Blocos e Transações

| Método | Descrição |
|--------|-----------|
| `getLatestBlockNumber()` | Número/slot/height atual |
| `getBlock(number\|hash\|'latest', fullTx?)` | Detalhes do bloco |
| `getTransaction(hash)` | Detalhes de uma TX |
| `getGasInfo()` | Gas price e base fee (EVM) |
| `estimateGas(txObject)` | Estimativa de gas (EVM) |

### `$w3->transfer` — Transferências

| Método | Descrição |
|--------|-----------|
| `buildNativeTransfer(from, to, amount)` | Monta TX ETH/MATIC/BNB... |
| `buildTokenTransfer(from, contract, to, amount, decimals)` | Monta TX ERC-20 |
| `sendRaw(rawHex)` | Envia TX assinada (EVM) |
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

---

## 🔧 Utilitários

```php
use Web3PHP\Math;
use Web3PHP\Address;

// Conversões
Math::etherToWei(1.5);              // "1500000000000000000"
Math::weiToEther("1500000000000000000"); // "1.5"
Math::solToLamports(1.5);           // 1500000000
Math::satoshiToBtc(100000000);      // 1.0
Math::parseUnits('100', 6);         // "100000000"
Math::formatUnits('100000000', 6);  // "100.000000"

// Validação de endereços
Address::isValidEVM('0x...');        // true/false
Address::isValidBitcoin('bc1...');   // true/false
Address::isValidSolana('abc...');    // true/false
Address::isValidTron('T...');        // true/false
Address::validate($addr, 'ethereum'); // valida pela rede

// ABI Encoding (manual)
use Web3PHP\AbiEncoder;
$selector = AbiEncoder::selector('transfer(address,uint256)');
$calldata = AbiEncoder::encodeCall('transfer(address,uint256)', ['address','uint256'], [$to, $amount]);
```

---

## ⚠️ Sobre Assinatura de Transações

Por questões de segurança, a Web3PHP **não armazena nem deriva chaves privadas**.

Fluxo recomendado:
1. Use `buildNativeTransfer()` ou `buildTokenTransfer()` para montar a TX
2. Assine **offline** com `kornrunner/ethereum-offline-raw-tx` ou equivalente
3. Envie o raw hex com `sendRaw()`

```bash
composer require kornrunner/ethereum-offline-raw-tx
```

---

## 🛡️ Tratamento de Erros

```php
use Web3PHP\WalletException;
use Web3PHP\NetworkException;
use Web3PHP\BlockException;
use Web3PHP\TransferException;
use Web3PHP\ContractException;
use Web3PHP\Web3Exception; // base

try {
    $balance = $eth->wallet->getBalance($address);
} catch (WalletException $e) {
    // endereço inválido, rede não suportada
} catch (NetworkException $e) {
    // falha de conexão, JSON-RPC error
} catch (Web3Exception $e) {
    // qualquer erro da biblioteca
}
```

---

## 📄 Licença

MIT — Livre para uso comercial e pessoal.
