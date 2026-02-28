# Web3PHP 🔗

**Multi-Blockchain PHP Integration Library + FakeChain Local Simulator**

---

## 📦 Estrutura do Projeto

```
web3php/
├── src/
│   ├── Web3PHP.php          ← Biblioteca principal (Ethereum, Bitcoin, Solana, Tron...)
│   └── FakeChain.php        ← Blockchain local para testes (zero API)
├── examples/
│   ├── Web3PHP_examples.php ← Exemplos completos da biblioteca real
│   └── FakeChain_examples.php ← Exemplos completos do FakeChain
├── tests/
│   └── FakeChainTest.php    ← 50+ testes unitários (PHPUnit)
├── docs/
│   └── README.md            ← Documentação detalhada
├── composer.json
├── phpunit.xml
└── README.md
```

---

## 🚀 Quick Start

### Web3PHP — Blockchain real (requer API)

```php
require 'src/Web3PHP.php';
use Web3PHP\Web3PHP;

// Ethereum via Infura
$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'SUA_CHAVE_INFURA',
]);

echo $eth->wallet->getBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
echo $eth->latestBlock();
```

### FakeChain — Local, sem API, ideal para testes

```php
require 'src/FakeChain.php';
use FakeChain\FakeChain;

$chain = new FakeChain();

$alice = $chain->createWallet('Alice', 100.0); // cria carteira com 100 ETH fake
$bob   = $chain->createWallet('Bob',   50.0);

// Transferência em 1 linha
$txHash = $chain->sendTransfer(
    $alice['address'],
    $bob['address'],
    10.0,
    $alice['private_key']
);

echo $chain->wallet->getBalance($alice['address']); // ~89.99 (descontou gas)
echo $chain->wallet->getBalance($bob['address']);   // 60.0

// Deploy e uso de token ERC-20
$usdt = $chain->deployERC20('Fake USDT', 'USDT', 6, 1_000_000.0, $alice['address']);
$chain->sendTokenTransfer($alice['address'], $usdt, $bob['address'], 500.0, $alice['private_key'], 6);

// NFT
$nft   = $chain->deployERC721('FakeApe', 'FAPE', $alice['address']);
$token = $chain->mintNFT($nft, $alice['address'], 'https://ipfs.io/meta/1.json');
echo $chain->contract($nft)->erc721OwnerOf($token);
```

---

## 🛠️ Instalação

### Requisitos
- PHP **8.1+**
- Extensões: `curl`, `json`, `bcmath` (recomendado)

### Dependências opcionais (produção)
```bash
# Para endereços checksum EIP-55 e ABI encoding correto
composer require kornrunner/keccak

# Para assinar transações EVM no servidor
composer require kornrunner/ethereum-offline-raw-tx
```

### Rodar os testes
```bash
composer require --dev phpunit/phpunit
./vendor/bin/phpunit tests/
```

---

## 🔌 Providers (Web3PHP)

| Provider   | Config                                      |
|------------|---------------------------------------------|
| Infura     | `provider: 'infura'`, `api_key: 'KEY'`      |
| Alchemy    | `provider: 'alchemy'`, `api_key: 'KEY'`     |
| QuickNode  | `provider: 'quicknode'`, `rpc_url: 'URL'`   |
| Moralis    | `provider: 'moralis'`, `api_key: 'KEY'`     |
| Local node | `provider: 'local'`, `rpc_url: 'http://...'`|
| Público    | (padrão, sem chave)                         |

---

## 🌐 Redes (Web3PHP)

| Rede       | Código       | Tipo  |
|------------|--------------|-------|
| Ethereum   | `ethereum`   | EVM   |
| Polygon    | `polygon`    | EVM   |
| BSC        | `bsc`        | EVM   |
| Avalanche  | `avalanche`  | EVM   |
| Arbitrum   | `arbitrum`   | EVM   |
| Optimism   | `optimism`   | EVM   |
| Base       | `base`       | EVM   |
| Fantom     | `fantom`     | EVM   |
| Hardhat    | `hardhat`    | EVM local |
| Bitcoin    | `bitcoin`    | UTXO  |
| Solana     | `solana`     | SVM   |
| Tron       | `tron`       | TVM   |

---

## 🧪 FakeChain — Funcionalidades Exclusivas

```php
$chain = new FakeChain([
    'network'      => 'fakechain',
    'chain_id'     => 1337,
    'symbol'       => 'ETH',
    'gas_price'    => 0.000000021,
    'auto_mine'    => true,         // mina automaticamente após cada TX
    'storage_path' => 'chain.json', // persistência (opcional)
]);

// Ferramentas de teste
$snap = $chain->snapshot('antes dos testes');
// ... executa operações ...
$chain->rollback($snap);            // volta ao estado anterior

$chain->faucet($address, 100.0);   // airdrop de saldo
$chain->mineBlock();                // minera manualmente
$chain->inspect();                  // debug visual no terminal
$chain->reset();                    // limpa tudo
$dump = $chain->dump();             // estado completo como array
```

---

## 📚 Interface de Módulos (idêntica em ambos)

### `->wallet`
| Método | Descrição |
|--------|-----------|
| `getBalance(address)` | Saldo nativo |
| `getTokenBalance(wallet, contract, decimals)` | Saldo ERC-20 |
| `getNonce(address)` | Nonce |
| `getAllowance(contract, owner, spender)` | Allowance |
| `getTransactionHistory(address, ...)` | Histórico |
| `getTokenTransfers(address, ...)` | Transfers de token |

### `->block`
| Método | Descrição |
|--------|-----------|
| `getLatestBlockNumber()` | Último bloco |
| `getBlock(number\|hash\|'latest')` | Detalhes do bloco |
| `getTransaction(hash)` | Detalhes da TX |
| `getGasInfo()` | Gas price/base fee |
| `estimateGas(txObject)` | Estimativa de gas |

### `->transfer`
| Método | Descrição |
|--------|-----------|
| `buildNativeTransfer(from, to, amount)` | Monta TX nativa |
| `buildTokenTransfer(from, contract, to, amount)` | Monta TX de token |
| `sendRaw(rawHex)` | Envia TX assinada |
| `waitForConfirmation(hash)` | Aguarda confirmação |

### `->network`
| Método | Descrição |
|--------|-----------|
| `getChainId()` | Chain ID |
| `getNodeInfo()` | Info do nó |
| `getMempoolSize()` | TXs pendentes |

### `->contract(address)`
| Método | Descrição |
|--------|-----------|
| `call(signature, types, values)` | Lê função view |
| `callUint256(...)` | Lê uint256 |
| `erc20Info(holder)` | Info completa ERC-20 |
| `erc721OwnerOf(tokenId)` | Dono do NFT |
| `erc721TokenURI(tokenId)` | URI do NFT |
| `buildTransaction(...)` | Monta TX de contrato |
| `getLogs(event, fromBlock)` | Busca eventos |

---

## 🛡️ Tratamento de Erros

```php
use Web3PHP\WalletException;      // ou FakeChain\WalletException
use Web3PHP\TransferException;
use Web3PHP\BlockException;
use Web3PHP\ContractException;
use Web3PHP\NetworkException;
use Web3PHP\Web3Exception;        // base

try {
    $balance = $eth->wallet->getBalance($address);
} catch (WalletException $e) {
    echo "Endereço inválido: " . $e->getMessage();
} catch (NetworkException $e) {
    echo "Falha de rede: " . $e->getMessage();
} catch (Web3Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

---

## 📄 Licença

MIT — Livre para uso pessoal e comercial.
