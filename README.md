# MnemonicWallet 🔐

**BIP-39 + BIP-44 HD Wallet Generator para PHP**

Gera e deriva carteiras HD reais para Ethereum, Bitcoin, Solana, Tron e qualquer rede EVM — com suporte nativo ao [Web3PHP](https://github.com/web3php/web3php) e ao FakeChain para testes.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/web3php/web3php)](https://packagist.org/packages/web3php/web3php)

---

## Padrões implementados

| Padrão | Descrição |
|--------|-----------|
| **BIP-39** | Geração e validação de mnemônicas (12, 15, 18, 21, 24 palavras) |
| **BIP-32** | HD Wallet — derivação hierárquica determinística |
| **BIP-44** | Multi-Account Hierarchy — paths por moeda e conta |
| **EIP-55** | Checksum de endereços EVM |

---

## Instalação

```bash
composer require web3php/web3php
```

### Requisitos

- PHP **8.1+**
- `ext-gmp` — aritmética secp256k1 mod n
- `ext-hash` — PBKDF2-HMAC-SHA512 (BIP-39)
- [`kornrunner/keccak`](https://packagist.org/packages/kornrunner/keccak) — keccak256 para endereços EVM e Tron
- [`simplito/elliptic-php`](https://packagist.org/packages/simplito/elliptic-php) — ECDSA secp256k1 real

Todas as dependências são instaladas automaticamente via `composer require`.

---

## Quick Start

```php
use Web3PHP\MnemonicWallet;

$mn = new MnemonicWallet();

// 1. Gerar mnemônica
$mnemonic = $mn->generate(12);
// "word1 word2 word3 ... word12"

// 2. Derivar carteira Ethereum
$wallet = $mn->deriveWallet($mnemonic, index: 0, network: 'ethereum');

echo $wallet['checksum_address']; // 0xAb5801a7D398351b8bE11C439e05C5B3259aeC9B
echo $wallet['private_key'];      // a1b2c3...
echo $wallet['path'];             // m/44'/60'/0'/0/0
```

---

## Redes suportadas

| Rede | Código | Coin Type (BIP-44) | Formato de endereço |
|------|--------|--------------------|---------------------|
| Ethereum | `ethereum` | 60 | `0x...` (EIP-55) |
| Polygon | `polygon` | 60 | `0x...` (EIP-55) |
| BSC | `bsc` | 60 | `0x...` (EIP-55) |
| Avalanche | `avalanche` | 9000 | `0x...` (EIP-55) |
| Arbitrum | `arbitrum` | 60 | `0x...` (EIP-55) |
| Optimism | `optimism` | 60 | `0x...` (EIP-55) |
| Base | `base` | 60 | `0x...` (EIP-55) |
| Fantom | `fantom` | 60 | `0x...` (EIP-55) |
| Bitcoin | `bitcoin` | 0 | Base58Check (P2PKH) |
| Solana | `solana` | 501 | Base58 |
| Tron | `tron` | 195 | Base58Check (`T...`) |
| Litecoin | `litecoin` | 2 | Base58Check |

---

## API de referência

### `new MnemonicWallet()`

Instancia o wallet. Lança `RuntimeException` se qualquer dependência obrigatória não estiver instalada.

---

### `generate(int $wordCount = 12): string`

Gera uma mnemônica BIP-39 com entropia criptograficamente segura (`random_bytes`).

```php
$mnemonic12 = $mn->generate(12); // padrão MetaMask
$mnemonic24 = $mn->generate(24); // padrão Ledger
$mnemonic15 = $mn->generate(15); // também válido: 15, 18, 21
```

**Throws** `InvalidArgumentException` se `$wordCount` não for 12, 15, 18, 21 ou 24.

---

### `validate(string $mnemonic): bool`

Valida uma frase mnemônica verificando palavras na wordlist BIP-39 e checksum SHA-256.

```php
$mn->validate('abandon abandon ... about'); // true
$mn->validate('palavra invalida aqui');     // false
```

---

### `importMnemonic(string $mnemonic): array`

Valida e importa uma mnemônica externa (MetaMask, Ledger, Trust Wallet, etc.).

```php
$data = $mn->importMnemonic('abandon abandon ... about');

// Retorna:
// [
//   'mnemonic'   => 'abandon abandon ...',
//   'word_count' => 12,
//   'valid'      => true,
//   'seed_hex'   => 'c55257e3...',
//   'master_key' => 'e8f32e72...',
//   'chain_code' => 'b7d2bbbb...',
// ]
```

**Throws** `InvalidArgumentException` se a mnemônica for inválida.

---

### `deriveWallet(string $mnemonic, int $index, string $network, ...): array`

Deriva uma carteira completa via path BIP-44 `m/44'/coinType'/account'/change/index`.

```php
$wallet = $mn->deriveWallet(
    mnemonic:    $mnemonic,
    index:       0,           // índice da carteira
    network:     'ethereum',  // rede alvo
    account:     0,           // conta BIP-44 (padrão 0)
    change:      0,           // 0 = externa, 1 = interna (troco)
    passphrase:  ''           // 25ª palavra BIP-39 (padrão '')
);
```

**Retorna:**

```php
[
    'network'            => 'ethereum',
    'coin_type'          => 60,
    'path'               => "m/44'/60'/0'/0/0",
    'index'              => 0,
    'account'            => 0,
    'mnemonic'           => 'word1 word2 ...',
    'seed'               => 'c55257e3...',         // hex 512 bits
    'master_private_key' => 'e8f32e72...',
    'master_chain_code'  => 'b7d2bbbb...',
    'private_key'        => 'a1b2c3d4...',         // hex 256 bits
    'private_key_wif'    => 'KwDiBf89...',         // WIF (Bitcoin)
    'public_key'         => '02abc123...',         // compressed 33 bytes
    'address'            => '0xabc123...',         // lowercase
    'checksum_address'   => '0xAbC123...',         // EIP-55
]
```

---

### `deriveMultiple(string $mnemonic, int $count, int $startIndex, string $network, string $passphrase): array`

Deriva múltiplas carteiras em batch — o equivalente a clicar em "Add account" várias vezes no MetaMask.

```php
$wallets = $mn->deriveMultiple(
    mnemonic:    $mnemonic,
    count:       5,          // quantas carteiras
    startIndex:  0,          // a partir do índice
    network:     'ethereum',
    passphrase:  ''
);

foreach ($wallets as $w) {
    echo "[{$w['index']}] {$w['checksum_address']}\n";
}
// [0] 0xAbC...
// [1] 0xDeF...
// [2] 0x123...
// [3] 0x456...
// [4] 0x789...
```

---

### `deriveMultiChain(string $mnemonic, array $networks, int $index): array`

Deriva a mesma mnemônica para múltiplas redes simultaneamente.

```php
$chains = $mn->deriveMultiChain($mnemonic, [
    'ethereum', 'bitcoin', 'solana', 'tron', 'polygon', 'bsc'
]);

echo $chains['ethereum']['address']; // 0x...
echo $chains['bitcoin']['address'];  // 1BvBMSEYstWetq...
echo $chains['solana']['address'];   // GkSGP3...
echo $chains['tron']['address'];     // TJmV3H...
```

---

### `mnemonicToSeed(string $mnemonic, string $passphrase = ''): string`

Converte a mnemônica em seed de 512 bits via **PBKDF2-HMAC-SHA512** (especificação BIP-39). Retorna bytes raw.

```php
$seed = $mn->mnemonicToSeed($mnemonic);
// Com passphrase (25ª palavra — gera carteira completamente diferente):
$seed = $mn->mnemonicToSeed($mnemonic, 'minha_senha');

echo bin2hex($seed); // 512 bits = 128 chars hex
```

---

### `seedToMasterKey(string $seed): array`

Deriva a master key a partir da seed via **HMAC-SHA512** com chave `"Bitcoin seed"` (BIP-32).

```php
$master = $mn->seedToMasterKey($seed);
// ['private_key' => 'e8f32e72...', 'chain_code' => 'b7d2bbbb...']
```

---

### `entropyToMnemonic(string $entropyBytes): string`

Converte bytes de entropia diretamente em mnemônica BIP-39. Útil quando você quer controlar a fonte de entropia.

```php
$entropy  = random_bytes(16); // 128 bits → 12 palavras
$mnemonic = $mn->entropyToMnemonic($entropy);
```

---

### `toChecksumAddress(string $address, string $network = 'ethereum'): string`

Converte um endereço EVM para o formato **EIP-55 checksum**.

```php
$checksummed = $mn->toChecksumAddress('0xfb6916095ca1df60bb79ce92ce3ea74c37c5d359');
// '0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359'
```

Retorna o endereço sem modificação para redes não-EVM (Bitcoin, Solana, Tron).

---

### `privateKeyToWIF(string $privateKeyHex, string $network = 'bitcoin'): string`

Converte uma chave privada hex para **WIF** (Wallet Import Format), padrão importado por Bitcoin Core, Electrum, etc.

```php
$wif = $mn->privateKeyToWIF('e8f32e72...', 'bitcoin');
// 'KwDiBf89QgGbjEhKnhXJuH7LrciVrZi3qYjgd9M7rFY74NMTptX4'
```

---

### `wifToPrivateKey(string $wif): string`

Decodifica WIF de volta para chave privada hex.

```php
$privKey = $mn->wifToPrivateKey('KwDiBf89...');
// 'e8f32e72...'
```

---

### `base58Encode(string $input): string` / `base58Decode(string $input): string`

Codificação Base58 (usada em endereços Bitcoin, Solana e WIF).

```php
$encoded = $mn->base58Encode($bytes);
$decoded = $mn->base58Decode($encoded);
```

---

### `getCapabilities(): array`

Retorna informações sobre o ambiente atual.

```php
$caps = $mn->getCapabilities();
// [
//   'wordlist_size'    => 2048,
//   'wordlist_file'    => true,
//   'hmac_sha512'      => true,
//   'gmp_extension'    => true,
//   'bcmath_extension' => true,
//   'keccak_installed' => true,
//   'ecdsa_installed'  => true,
//   'normalizer'       => true,
//   'bip39_compliant'  => true,
//   'status'           => 'production_ready',
// ]
```

---

## Passphrase — 25ª palavra (BIP-39)

A passphrase é um parâmetro opcional que funciona como uma "25ª palavra" — ela altera completamente todas as carteiras derivadas. É um recurso de segurança avançado suportado por Ledger, Trezor e carteiras compatíveis com BIP-39.

```php
$sem   = $mn->deriveWallet($mnemonic, 0, 'ethereum', passphrase: '');
$com   = $mn->deriveWallet($mnemonic, 0, 'ethereum', passphrase: 'senha_secreta');

echo $sem['address']; // 0xAAA...
echo $com['address']; // 0xBBB... (completamente diferente)
```

> ⚠️ Se você perder a passphrase, perde acesso permanente às carteiras derivadas com ela — mesmo com a mnemônica correta.

---

## Integração com Web3PHP

Adicione o `MnemonicTrait` à classe `Web3PHP` para habilitar o módulo de mnemônicas diretamente na instância conectada à rede:

```php
use Web3PHP\Web3PHP;
use Web3PHP\MnemonicTrait;

// Dentro da classe Web3PHP:
// class Web3PHP {
//     use MnemonicTrait;
//     ...
// }

$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'SUA_CHAVE',
]);

// Gera mnemônica + deriva carteira para a rede configurada
$wallet = $eth->createHDWallet();

// Importa mnemônica existente
$wallet = $eth->createHDWallet('word1 word2 ... word12');

// Deriva N carteiras para a rede configurada
$wallets = $eth->deriveHDWallets($mnemonic, count: 10);

// Acesso direto ao módulo MnemonicWallet
$mnemonic = $eth->mnemonics()->generate(24);
$imported = $eth->mnemonics()->importMnemonic($phrase);
```

### Métodos do `MnemonicTrait`

| Método | Descrição |
|--------|-----------|
| `mnemonics(): MnemonicWallet` | Acesso ao módulo completo |
| `createHDWallet(string $mnemonic, int $index, string $passphrase): array` | Cria/importa carteira para a rede configurada |
| `deriveHDWallets(string $mnemonic, int $count, int $startIndex): array` | Deriva N carteiras para a rede configurada |

---

## Integração com FakeChain

Use `FakeChainHD` em vez de `FakeChain` para ter suporte a HD wallets no ambiente de testes:

```php
use FakeChain\FakeChainHD;

$chain = new FakeChainHD(['auto_mine' => true]);

// Gera mnemônica + registra no chain com saldo inicial
$alice = $chain->createHDWallet('Alice', 100.0, wordCount: 12);
$bob   = $chain->createHDWallet('Bob',   50.0);

echo $alice['mnemonic'];  // "word1 word2 ... word12"
echo $alice['address'];   // 0x...
echo $alice['path'];      // m/44'/60'/0'/0/0

// Transferência normal usando as chaves HD
$chain->sendTransfer(
    from:       $alice['address'],
    to:         $bob['address'],
    amount:     10.0,
    privateKey: $alice['private_key']
);

// Importar mnemônica externa no FakeChain
$carol = $chain->importMnemonic('word1 word2 ... word12', 'Carol', 75.0);
```

### Módulo `hd()` — `HDWalletSupport`

Acesso via `$chain->hd()`:

| Método | Descrição |
|--------|-----------|
| `createHDWallet(string $label, float $balance, int $wordCount): array` | Gera mnemônica + registra no chain |
| `importHDWallet(string $mnemonic, string $label, float $balance, int $index): array` | Importa mnemônica e registra |
| `deriveAndRegister(string $mnemonic, int $count, float $balance, int $startIndex): array` | Batch: deriva N carteiras e registra todas |
| `mnemonics(): MnemonicWallet` | Acesso direto ao `MnemonicWallet` |

```php
// Batch: deriva 5 carteiras de uma mnemônica e registra todas no chain
$wallets = $chain->hd()->deriveAndRegister(
    mnemonic:       $mnemonic,
    count:          5,
    initialBalance: 10.0,
    startIndex:     0
);
```

---

## Exemplo de output — `deriveWallet()`

```
🔷 ETHEREUM (m/44'/60'/0'/0/0)
   Address:     0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48
   Checksum:    0xA0b86991c6218b36c1D19D4a2e9Eb0cE3606eB48
   Private Key: 4c0883a69102937d6231471b5dbb6e538eba2ef2d48a5dc3b2e1...
   Public Key:  02f9308a019258c31049344f85f89d5229b531c845836f99b086...

🟠 BITCOIN  (m/44'/0'/0'/0/0)
   Address:     1LoVGDgRs9hTfTNJNuXKSpywcbdvwRXpmK
   WIF:         KwDiBf89QgGbjEhKnhXJuH7LrciVrZi3qYjgd9M7rFY74NMTptX4

🟣 SOLANA   (m/44'/501'/0'/0/0)
   Address:     GkSGP3V4qBMnKWBXHa...

🔴 TRON     (m/44'/195'/0'/0/0)
   Address:     TJmV3Hg5tKdVs9bQZm...
```

---

## Segurança

- Toda entropia é gerada via [`random_bytes()`](https://www.php.net/manual/en/function.random-bytes.php) — CSPRNG do sistema operacional
- PBKDF2 usa **2048 iterações** conforme especificado no BIP-39
- ECDSA via `simplito/elliptic-php` — secp256k1 real, sem fallbacks ou aproximações
- Keccak256 via `kornrunner/keccak` — implementação conforme Ethereum Yellow Paper
- Nenhuma chave privada é logada, armazenada ou transmitida pela biblioteca

> ⚠️ **Nunca** use mnemônicas geradas em servidor para carteiras com fundos reais sem medidas adicionais de segurança. A chave privada fica em memória durante a derivação.

---

## Tratamento de erros

```php
use Web3PHP\MnemonicWallet;

try {
    $mn = new MnemonicWallet();
} catch (\RuntimeException $e) {
    // Dependência obrigatória não encontrada
    echo $e->getMessage();
    // "Dependência obrigatória não encontrada: kornrunner/keccak
    //  Execute: composer require kornrunner/keccak"
}

try {
    $mn->deriveWallet('frase inválida', 0, 'ethereum');
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();
    // "Mnemônica inválida ou checksum incorreto."
}

try {
    $mn->importMnemonic('palavras erradas aqui');
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();
    // "Mnemônica inválida. Verifique as palavras e o checksum."
}
```

---

## Testes

```bash
composer install
./vendor/bin/phpunit tests/
```

---

## Licença

MIT — livre para uso pessoal e comercial.
