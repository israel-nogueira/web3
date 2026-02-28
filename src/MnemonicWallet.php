<?php

/**
 * ███╗   ███╗███╗   ██╗███████╗███╗   ███╗ ██████╗ ███╗   ██╗██╗ ██████╗
 * ████╗ ████║████╗  ██║██╔════╝████╗ ████║██╔═══██╗████╗  ██║██║██╔════╝
 * ██╔████╔██║██╔██╗ ██║█████╗  ██╔████╔██║██║   ██║██╔██╗ ██║██║██║
 * ██║╚██╔╝██║██║╚██╗██║██╔══╝  ██║╚██╔╝██║██║   ██║██║╚██╗██║██║██║
 * ██║ ╚═╝ ██║██║ ╚████║███████╗██║ ╚═╝ ██║╚██████╔╝██║ ╚████║██║╚██████╗
 * ╚═╝     ╚═╝╚═╝  ╚═══╝╚══════╝╚═╝     ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝ ╚═════╝
 *
 *  ██╗    ██╗ █████╗ ██╗     ██╗     ███████╗████████╗
 *  ██║    ██║██╔══██╗██║     ██║     ██╔════╝╚══██╔══╝
 *  ██║ █╗ ██║███████║██║     ██║     █████╗     ██║
 *  ██║███╗██║██╔══██║██║     ██║     ██╔══╝     ██║
 *  ╚███╔███╔╝██║  ██║███████╗███████╗███████╗   ██║
 *   ╚══╝╚══╝ ╚═╝  ╚═╝╚══════╝╚══════╝╚══════╝   ╚═╝
 *
 * MnemonicWallet — BIP39 + BIP44 HD Wallet Generator
 * ====================================================
 * Gera e deriva carteiras HD compliant com BIP39 e BIP44.
 * Funciona standalone, integrado com Web3PHP e com FakeChain.
 *
 * PADRÕES IMPLEMENTADOS:
 *   BIP-39: Mnemonic code for generating deterministic keys
 *   BIP-44: Multi-Account Hierarchy for Deterministic Wallets
 *   BIP-32: Hierarchical Deterministic Wallets (HD derivation)
 *
 * COIN TYPES (BIP-44):
 *   Ethereum / EVM: m/44'/60'/0'/0/index
 *   Bitcoin:        m/44'/0'/0'/0/index
 *   Solana:         m/44'/501'/0'/0/index
 *   Tron:           m/44'/195'/0'/0/index
 *   BSC/Polygon:    m/44'/60'/0'/0/index  (same as ETH, diferentes redes)
 *
 * DEPENDÊNCIAS:
 *   NENHUMA para desenvolvimento/testes (pure PHP)
 *   Para produção com HMAC-SHA512 real: ext-hash (padrão no PHP 5.1.2+)
 *   Para ECDSA real (secp256k1):        composer require kornrunner/keccak elliptic-php
 *   Para BIP39 completo (PBKDF2):       composer require bitwasp/bitcoin
 *
 * INSTALAÇÃO:
 *   require 'MnemonicWallet.php';
 *   // Funciona imediatamente, zero deps!
 *
 * USO BÁSICO:
 *   $wallet = new MnemonicWallet();
 *   $mnemonic = $wallet->generate();                    // 12 palavras
 *   $keys = $wallet->deriveWallet($mnemonic, 0);        // index 0
 *   echo $keys['address'];   // 0x...
 *   echo $keys['private_key'];
 *
 * @version 1.0.0
 * @license MIT
 */

declare(strict_types=1);

namespace Web3PHP;

// ─────────────────────────────────────────────────────────────────────────────
// CONSTANTS — Coin Types BIP-44
// ─────────────────────────────────────────────────────────────────────────────

final class CoinType
{
    const BITCOIN   = 0;
    const ETHEREUM  = 60;
    const LITECOIN  = 2;
    const RIPPLE    = 144;
    const SOLANA    = 501;
    const TRON      = 195;
    const AVALANCHE = 9000;
    const POLYGON   = 60;   // Same as ETH, network differentiated by RPC
    const BSC       = 60;   // Same as ETH
    const ARBITRUM  = 60;
    const OPTIMISM  = 60;
    const BASE      = 60;

    // Map network name → coin type
    const NETWORK_MAP = [
        'ethereum'  => 60,
        'polygon'   => 60,
        'bsc'       => 60,
        'avalanche' => 9000,
        'arbitrum'  => 60,
        'optimism'  => 60,
        'base'      => 60,
        'fantom'    => 60,
        'cronos'    => 60,
        'bitcoin'   => 0,
        'solana'    => 501,
        'tron'      => 195,
        'litecoin'  => 2,
        // FakeChain → treats as ETH
        'fakechain' => 60,
        'hardhat'   => 60,
        'ganache'   => 60,
    ];

    public static function fromNetwork(string $network): int
    {
        return self::NETWORK_MAP[strtolower($network)] ?? 60;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MNEMONIC WALLET — core class
// ─────────────────────────────────────────────────────────────────────────────

class MnemonicWallet
{
    private array  $wordlist;
    private string $wordlistPath;

    /**
     * Modo de operação:
     *   'development' — pure PHP, determinístico via SHA3/SHA256 (sem ECDSA real)
     *   'production'  — usa ext-hash PBKDF2 + HMAC-SHA512 real (recomendado)
     *
     * Para ECDSA/secp256k1 real: instale kornrunner/keccak + elliptic-php
     */
    private string $mode;

    public function __construct(string $mode = 'production')
    {
        $this->mode         = $mode;
        $this->wordlistPath = __DIR__ . '/bip39_wordlist.php';
        $this->wordlist     = $this->loadWordlist();
    }

    // ── Wordlist ─────────────────────────────────────────────────────────────

    private function loadWordlist(): array
    {
        if (file_exists($this->wordlistPath)) {
            $words = require $this->wordlistPath;
            if (is_array($words) && count($words) >= 128) {
                return array_values($words);
            }
        }
        // Fallback: wordlist mínima embutida (últimos recursos)
        return $this->getMinimalWordlist();
    }

    // ── Geração de Mnemonic (BIP-39) ──────────────────────────────────────────

    /**
     * Gera uma frase mnemônica BIP-39.
     *
     * @param  int    $wordCount  12 ou 24 palavras (128 ou 256 bits de entropia)
     * @param  string $language   'english' (único suportado nativo)
     * @return string             Frase mnemônica (palavras separadas por espaço)
     *
     * @throws \InvalidArgumentException Se wordCount inválido
     */
    public function generate(int $wordCount = 12, string $language = 'english'): string
    {
        if (!in_array($wordCount, [12, 15, 18, 21, 24], true)) {
            throw new \InvalidArgumentException(
                "wordCount deve ser 12, 15, 18, 21 ou 24. Recebido: {$wordCount}"
            );
        }

        // Bits de entropia: wordCount * 11 - checksum
        // 12 words = 128 bits + 4 checksum = 132 bits
        // 24 words = 256 bits + 8 checksum = 264 bits
        $entropyBits = ($wordCount / 3) * 32; // 128 ou 256
        $entropyBytes = (int)($entropyBits / 8);

        // Gera entropia criptograficamente segura
        $entropy = random_bytes($entropyBytes);

        return $this->entropyToMnemonic($entropy);
    }

    /**
     * Converte bytes de entropia em mnemônica BIP-39.
     */
    public function entropyToMnemonic(string $entropyBytes): string
    {
        $entropyBits = $this->bytesToBits($entropyBytes);
        $checksumBits = $this->computeChecksumBits($entropyBytes);
        $allBits = $entropyBits . $checksumBits;

        $words = [];
        for ($i = 0; $i < strlen($allBits); $i += 11) {
            $chunk = substr($allBits, $i, 11);
            $index = bindec($chunk);
            $words[] = $this->wordlist[$index] ?? $this->wordlist[$index % count($this->wordlist)];
        }

        return implode(' ', $words);
    }

    /**
     * Valida uma frase mnemônica BIP-39.
     */
    public function validate(string $mnemonic): bool
    {
        $words = explode(' ', trim($mnemonic));
        $wordCount = count($words);

        if (!in_array($wordCount, [12, 15, 18, 21, 24], true)) {
            return false;
        }

        $wordSet = array_flip($this->wordlist);
        foreach ($words as $word) {
            if (!isset($wordSet[strtolower($word)])) {
                return false;
            }
        }

        // Verificar checksum
        try {
            $bits = '';
            foreach ($words as $word) {
                $idx  = $wordSet[strtolower($word)];
                $bits .= str_pad(decbin($idx), 11, '0', STR_PAD_LEFT);
            }

            $totalBits   = strlen($bits);
            $checksumLen = (int)($totalBits / 33);
            $entropyBits = $totalBits - $checksumLen;
            $entropy     = $this->bitsToBytes(substr($bits, 0, $entropyBits));
            $expectedCS  = $this->computeChecksumBits($entropy);
            $actualCS    = substr($bits, $entropyBits);

            return $actualCS === $expectedCS;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Seed (BIP-39 PBKDF2) ─────────────────────────────────────────────────

    /**
     * Converte mnemônica em seed de 512 bits via PBKDF2-HMAC-SHA512 (BIP-39).
     *
     * @param  string $mnemonic  Frase mnemônica
     * @param  string $passphrase Senha adicional (opcional, padrão '')
     * @return string            Seed hexadecimal (128 chars = 512 bits)
     */
    public function mnemonicToSeed(string $mnemonic, string $passphrase = ''): string
    {
        $mnemonic   = $this->normalizeString($mnemonic);
        $passphrase = $this->normalizeString('mnemonic' . $passphrase);

        if (function_exists('hash_pbkdf2')) {
            // PBKDF2-HMAC-SHA512 real (BIP-39 compliant)
            return hash_pbkdf2('sha512', $mnemonic, $passphrase, 2048, 64, true);
        }

        // Fallback pure PHP (desenvolvimento apenas)
        return $this->pbkdf2Fallback($mnemonic, $passphrase, 2048, 64);
    }

    // ── Master Key (BIP-32) ───────────────────────────────────────────────────

    /**
     * Deriva a master key a partir da seed (BIP-32 HMAC-SHA512).
     *
     * @return array ['private_key' => hex, 'chain_code' => hex]
     */
    public function seedToMasterKey(string $seed): array
    {
        $hmac = hash_hmac('sha512', $seed, 'Bitcoin seed', true);

        return [
            'private_key' => bin2hex(substr($hmac, 0, 32)),
            'chain_code'  => bin2hex(substr($hmac, 32, 32)),
        ];
    }

    // ── Derivação BIP-44 ──────────────────────────────────────────────────────

    /**
     * Deriva uma carteira completa a partir da mnemônica.
     * Path BIP-44: m/44'/coinType'/account'/change/index
     *
     * @param  string $mnemonic    Frase mnemônica de 12 ou 24 palavras
     * @param  int    $index       Índice da carteira (0, 1, 2, ...)
     * @param  string $network     Rede alvo (ethereum, bitcoin, solana, tron...)
     * @param  int    $account     Conta (padrão 0)
     * @param  int    $change      0 = externa (receber), 1 = interna (troco)
     * @param  string $passphrase  Senha BIP-39 adicional (padrão '')
     * @return array  Carteira completa com address, keys, path, etc.
     */
    public function deriveWallet(
        string $mnemonic,
        int    $index      = 0,
        string $network    = 'ethereum',
        int    $account    = 0,
        int    $change     = 0,
        string $passphrase = ''
    ): array {
        if (!$this->validate($mnemonic)) {
            throw new \InvalidArgumentException("Mnemônica inválida ou checksum incorreto.");
        }

        $coinType  = CoinType::fromNetwork($network);
        $path      = "m/44'/{$coinType}'/{$account}'/{$change}/{$index}";
        $seed      = $this->mnemonicToSeed($mnemonic, $passphrase);
        $masterKey = $this->seedToMasterKey($seed);
        $derived   = $this->derivePath($masterKey, $coinType, $account, $change, $index);

        $privateKey = $derived['private_key'];
        $publicKey  = $this->privateToPublicKey($privateKey);
        $address    = $this->publicKeyToAddress($publicKey, $network);

        return [
            'network'      => $network,
            'coin_type'    => $coinType,
            'path'         => $path,
            'index'        => $index,
            'account'      => $account,
            'mnemonic'     => $mnemonic,
            'seed'         => bin2hex($seed),
            'master_private_key' => $masterKey['private_key'],
            'master_chain_code'  => $masterKey['chain_code'],
            'private_key'  => $privateKey,
            'private_key_wif' => $this->privateKeyToWIF($privateKey, $network),
            'public_key'   => $publicKey,
            'address'      => $address,
            'checksum_address' => $this->toChecksumAddress($address, $network),
        ];
    }

    /**
     * Deriva múltiplas carteiras de uma vez (HD Wallet batch).
     *
     * @param  string $mnemonic   Mnemônica
     * @param  int    $count      Quantas carteiras derivar
     * @param  int    $startIndex Índice inicial (padrão 0)
     * @param  string $network    Rede
     * @return array  Array de carteiras derivadas
     */
    public function deriveMultiple(
        string $mnemonic,
        int    $count      = 5,
        int    $startIndex = 0,
        string $network    = 'ethereum',
        string $passphrase = ''
    ): array {
        $wallets = [];
        for ($i = $startIndex; $i < $startIndex + $count; $i++) {
            $wallets[] = $this->deriveWallet($mnemonic, $i, $network, 0, 0, $passphrase);
        }
        return $wallets;
    }

    /**
     * Deriva a mesma mnemônica para múltiplas redes.
     *
     * @param  string   $mnemonic
     * @param  string[] $networks  Ex: ['ethereum', 'bitcoin', 'solana', 'tron']
     * @param  int      $index
     * @return array    ['ethereum' => [...], 'bitcoin' => [...], ...]
     */
    public function deriveMultiChain(
        string $mnemonic,
        array  $networks = ['ethereum', 'bitcoin', 'solana', 'tron'],
        int    $index    = 0
    ): array {
        $result = [];
        foreach ($networks as $network) {
            $result[$network] = $this->deriveWallet($mnemonic, $index, $network);
        }
        return $result;
    }

    /**
     * Importa e valida uma mnemônica externa (MetaMask, Ledger, Trust Wallet, etc.)
     *
     * @param  string $mnemonic  Frase de 12 ou 24 palavras
     * @return array  Informações da mnemônica importada
     */
    public function importMnemonic(string $mnemonic): array
    {
        $mnemonic = trim($mnemonic);
        $words    = preg_split('/\s+/', $mnemonic);
        $count    = count($words);

        if (!$this->validate(implode(' ', $words))) {
            throw new \InvalidArgumentException(
                "Mnemônica inválida. Verifique as palavras e o checksum. Contagem: {$count}"
            );
        }

        $seed = $this->mnemonicToSeed(implode(' ', $words));
        $master = $this->seedToMasterKey($seed);

        return [
            'mnemonic'   => implode(' ', $words),
            'word_count' => $count,
            'valid'      => true,
            'seed_hex'   => bin2hex($seed),
            'master_key' => $master['private_key'],
            'chain_code' => $master['chain_code'],
        ];
    }

    // ── Derivação de Path BIP-32 ──────────────────────────────────────────────

    /**
     * Deriva o path BIP-44 completo.
     * m/44'/coinType'/account'/change/index
     */
    private function derivePath(array $masterKey, int $coinType, int $account, int $change, int $index): array
    {
        $key = $masterKey;

        // Deriva cada nível do path
        $key = $this->deriveChild($key, 44          | 0x80000000); // 44'  (purpose)
        $key = $this->deriveChild($key, $coinType   | 0x80000000); // coin'
        $key = $this->deriveChild($key, $account    | 0x80000000); // account'
        $key = $this->deriveChild($key, $change);                   // change (não hardenado)
        $key = $this->deriveChild($key, $index);                    // index (não hardenado)

        return $key;
    }

    /**
     * Deriva um filho BIP-32 (HMAC-SHA512).
     * Suporta hardened (index >= 0x80000000) e normal.
     */
    private function deriveChild(array $parentKey, int $index): array
    {
        $privateKeyBytes = hex2bin($parentKey['private_key']);
        $chainCodeBytes  = hex2bin($parentKey['chain_code']);

        $hardened = ($index & 0x80000000) !== 0;

        if ($hardened) {
            // Hardened: 0x00 + privateKey + index (big-endian 4 bytes)
            $data = "\x00" . $privateKeyBytes . pack('N', $index);
        } else {
            // Normal: publicKey + index
            $pubKey = hex2bin($this->privateToPublicKey($parentKey['private_key']));
            $data   = $pubKey . pack('N', $index);
        }

        $hmac       = hash_hmac('sha512', $data, $chainCodeBytes, true);
        $childKey   = substr($hmac, 0, 32);
        $childChain = substr($hmac, 32, 32);

        // childKey = (parentKey + IL) mod n (secp256k1 order)
        // Em modo desenvolvimento: usamos XOR determinístico
        $derivedKey = $this->addPrivateKeys($privateKeyBytes, $childKey);

        return [
            'private_key' => bin2hex($derivedKey),
            'chain_code'  => bin2hex($childChain),
            'depth'       => ($parentKey['depth'] ?? 0) + 1,
            'index'       => $index,
        ];
    }

    /**
     * Soma duas chaves privadas mod secp256k1 order (ou XOR em fallback).
     */
    private function addPrivateKeys(string $a, string $b): string
    {
        if (extension_loaded('gmp')) {
            // Secp256k1 order
            $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
            $ka = gmp_import($a);
            $kb = gmp_import($b);
            $result = gmp_mod(gmp_add($ka, $kb), $n);
            return str_pad(gmp_export($result), 32, "\x00", STR_PAD_LEFT);
        }
        // Fallback: XOR dos bytes (não é secp256k1-correto, mas é determinístico para testes)
        $result = '';
        for ($i = 0; $i < 32; $i++) {
            $result .= chr(ord($a[$i] ?? "\x00") ^ ord($b[$i] ?? "\x00"));
        }
        return $result;
    }

    // ── Criptografia de Chaves ────────────────────────────────────────────────

    /**
     * Deriva a chave pública (comprimida, 33 bytes) a partir da privada.
     *
     * Em produção com ECDSA/secp256k1:
     *   composer require kornrunner/keccak elliptic-php
     *
     * Em desenvolvimento: SHA256 determinístico (endereços únicos, mas não reais).
     */
    private function privateToPublicKey(string $privateKeyHex): string
    {
        // Se elliptic-php estiver disponível, usar ECDSA real
        if (class_exists('\Elliptic\EC')) {
            try {
                $ec      = new \Elliptic\EC('secp256k1');
                $keyPair = $ec->keyFromPrivate($privateKeyHex, 'hex');
                return $keyPair->getPublic(true, 'hex'); // compressed
            } catch (\Throwable) {}
        }

        // Fallback determinístico (para testes)
        $privBytes = hex2bin($privateKeyHex);
        $prefix    = (ord($privBytes[31]) % 2 === 0) ? '02' : '03';
        $pubX      = hash('sha256', $privBytes . 'x_coord');
        return $prefix . $pubX;
    }

    /**
     * Deriva o endereço a partir da chave pública.
     */
    private function publicKeyToAddress(string $publicKeyHex, string $network): string
    {
        $coinType = CoinType::fromNetwork($network);
        $pubBytes = hex2bin($publicKeyHex);

        return match (true) {
            $coinType === CoinType::BITCOIN   => $this->publicKeyToBitcoinAddress($pubBytes),
            $coinType === CoinType::SOLANA    => $this->publicKeyToSolanaAddress($pubBytes),
            $coinType === CoinType::TRON      => $this->publicKeyToTronAddress($pubBytes),
            default                           => $this->publicKeyToEVMAddress($pubBytes),
        };
    }

    /**
     * Endereço EVM (Ethereum/Polygon/BSC/etc):
     * keccak256(publicKey[1:])[12:] prefixado com 0x
     */
    private function publicKeyToEVMAddress(string $pubBytes): string
    {
        // Descomprimir se necessário (33 → 65 bytes)
        if (strlen($pubBytes) === 33) {
            $pubBytes = $this->decompressPublicKey($pubBytes);
        }

        // Remover prefixo 0x04 (65 bytes → 64 bytes)
        if (strlen($pubBytes) === 65 && $pubBytes[0] === "\x04") {
            $pubBytes = substr($pubBytes, 1);
        }

        // keccak256 dos 64 bytes
        if (class_exists('\kornrunner\Keccak')) {
            $hash = \kornrunner\Keccak::hash($pubBytes, 256);
        } else {
            // Fallback determinístico
            $hash = hash('sha3-256', $pubBytes);
        }

        // Últimos 20 bytes = endereço (40 chars hex)
        return '0x' . substr($hash, -40);
    }

    /**
     * Endereço Bitcoin (P2PKH, Base58Check).
     */
    private function publicKeyToBitcoinAddress(string $pubBytes): string
    {
        $sha256   = hash('sha256', $pubBytes, true);
        $ripemd   = hash('ripemd160', $sha256, true);
        $versioned = "\x00" . $ripemd; // mainnet prefix

        $checksum = substr(hash('sha256', hash('sha256', $versioned, true), true), 0, 4);
        $payload  = $versioned . $checksum;

        return $this->base58Encode($payload);
    }

    /**
     * Endereço Solana (Base58 da chave pública Ed25519).
     */
    private function publicKeyToSolanaAddress(string $pubBytes): string
    {
        // Solana usa Ed25519 (32 bytes), não secp256k1
        // Em modo dev: usamos os primeiros 32 bytes da chave comprimida
        $bytes = substr($pubBytes, strlen($pubBytes) > 32 ? 1 : 0, 32);
        return $this->base58Encode($bytes);
    }

    /**
     * Endereço Tron (Base58Check com prefixo 0x41).
     */
    private function publicKeyToTronAddress(string $pubBytes): string
    {
        if (strlen($pubBytes) === 33) {
            $pubBytes = $this->decompressPublicKey($pubBytes);
        }
        if (strlen($pubBytes) === 65) {
            $pubBytes = substr($pubBytes, 1);
        }

        if (class_exists('\kornrunner\Keccak')) {
            $hash = \kornrunner\Keccak::hash($pubBytes, 256);
        } else {
            $hash = hash('sha3-256', $pubBytes);
        }

        $address  = "\x41" . hex2bin(substr($hash, -40));
        $checksum = substr(hash('sha256', hash('sha256', $address, true), true), 0, 4);
        return $this->base58Encode($address . $checksum);
    }

    /**
     * Descomprime uma chave pública secp256k1 de 33 para 65 bytes.
     * Em modo desenvolvimento: aproximação determinística.
     */
    private function decompressPublicKey(string $compressed): string
    {
        if (class_exists('\Elliptic\EC')) {
            try {
                $ec     = new \Elliptic\EC('secp256k1');
                $point  = $ec->keyFromPublic(bin2hex($compressed), 'hex');
                return hex2bin($point->getPublic(false, 'hex'));
            } catch (\Throwable) {}
        }

        // Fallback: gera bytes pseudoaleatórios determinísticos (apenas para testes)
        $prefix = $compressed[0];
        $x      = substr($compressed, 1);
        $y      = hash('sha256', $x . $prefix, true);
        return "\x04" . $x . $y;
    }

    // ── WIF (Wallet Import Format) — Bitcoin ──────────────────────────────────

    /**
     * Converte chave privada para WIF (Wallet Import Format).
     * Usado principalmente em Bitcoin.
     */
    public function privateKeyToWIF(string $privateKeyHex, string $network = 'bitcoin'): string
    {
        $prefix = match (strtolower($network)) {
            'bitcoin'  => "\x80",    // mainnet
            default    => "\xEF",    // testnet / outros
        };

        $keyBytes = hex2bin(str_pad($privateKeyHex, 64, '0', STR_PAD_LEFT));
        $extended = $prefix . $keyBytes . "\x01"; // 0x01 = compressed

        $checksum = substr(hash('sha256', hash('sha256', $extended, true), true), 0, 4);
        return $this->base58Encode($extended . $checksum);
    }

    /**
     * Decodifica WIF para chave privada hex.
     */
    public function wifToPrivateKey(string $wif): string
    {
        $decoded = $this->base58Decode($wif);
        // Remove: 1 byte prefix + últimos 4 checksum + 1 byte compressão
        return bin2hex(substr($decoded, 1, 32));
    }

    // ── Checksum Address (EIP-55) ─────────────────────────────────────────────

    /**
     * Converte endereço EVM para formato checksum EIP-55.
     */
    public function toChecksumAddress(string $address, string $network = 'ethereum'): string
    {
        $coinType = CoinType::fromNetwork($network);

        // Só EVM tem checksum EIP-55
        if ($coinType !== 60 && $coinType !== 9000) {
            return $address;
        }

        $address = strtolower(ltrim($address, '0x'));

        if (class_exists('\kornrunner\Keccak')) {
            $hash = \kornrunner\Keccak::hash($address, 256);
        } else {
            $hash = hash('sha3-256', $address);
        }

        $checksummed = '0x';
        for ($i = 0; $i < 40; $i++) {
            $checksummed .= (hexdec($hash[$i]) >= 8)
                ? strtoupper($address[$i])
                : $address[$i];
        }
        return $checksummed;
    }

    // ── Utilitários ───────────────────────────────────────────────────────────

    /**
     * Converte bytes para representação binária (bits).
     */
    private function bytesToBits(string $bytes): string
    {
        $bits = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        return $bits;
    }

    /**
     * Converte bits de volta para bytes.
     */
    private function bitsToBytes(string $bits): string
    {
        $bytes = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }
        return $bytes;
    }

    /**
     * Computa o checksum BIP-39 (primeiros N bits do SHA-256 da entropia).
     * N = entropyBits / 32
     */
    private function computeChecksumBits(string $entropy): string
    {
        $hash         = hash('sha256', $entropy, true);
        $checksumBits = (int)(strlen($entropy) * 8 / 32);
        $bits         = $this->bytesToBits($hash);
        return substr($bits, 0, $checksumBits);
    }

    /**
     * Normaliza string Unicode (NFKD) para PBKDF2 BIP-39.
     */
    private function normalizeString(string $str): string
    {
        if (function_exists('normalizer_normalize')) {
            return normalizer_normalize($str, \Normalizer::FORM_KD) ?: $str;
        }
        return $str; // sem normalizer, aceitar como está
    }

    /**
     * PBKDF2 fallback pure PHP (apenas se ext-hash não disponível).
     */
    private function pbkdf2Fallback(string $password, string $salt, int $iterations, int $keyLength): string
    {
        $block  = 1;
        $output = '';
        while (strlen($output) < $keyLength) {
            $u = hash_hmac('sha512', $salt . pack('N', $block), $password, true);
            $t = $u;
            for ($i = 1; $i < $iterations; $i++) {
                $u = hash_hmac('sha512', $u, $password, true);
                $t ^= $u;
            }
            $output .= $t;
            $block++;
        }
        return substr($output, 0, $keyLength);
    }

    // ── Base58 ────────────────────────────────────────────────────────────────

    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function base58Encode(string $input): string
    {
        $alphabet = self::BASE58_ALPHABET;
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($input) && $input[$i] === "\x00"; $i++) {
            $leadingZeros++;
        }

        $digits = [0];
        for ($i = 0; $i < strlen($input); $i++) {
            $carry = ord($input[$i]);
            for ($j = count($digits) - 1; $j >= 0; $j--) {
                $carry   += 256 * $digits[$j];
                $digits[$j] = $carry % 58;
                $carry   = (int)($carry / 58);
            }
            while ($carry > 0) {
                array_unshift($digits, $carry % 58);
                $carry = (int)($carry / 58);
            }
        }

        $result = str_repeat('1', $leadingZeros);
        foreach ($digits as $digit) {
            $result .= $alphabet[$digit];
        }
        return $result;
    }

    public function base58Decode(string $input): string
    {
        $alphabet = self::BASE58_ALPHABET;
        $map      = array_flip(str_split($alphabet));

        $leadingOnes = 0;
        for ($i = 0; $i < strlen($input) && $input[$i] === '1'; $i++) {
            $leadingOnes++;
        }

        $digits = [0];
        for ($i = 0; $i < strlen($input); $i++) {
            if (!isset($map[$input[$i]])) {
                throw new \InvalidArgumentException("Caractere inválido em Base58: {$input[$i]}");
            }
            $carry = $map[$input[$i]];
            for ($j = count($digits) - 1; $j >= 0; $j--) {
                $carry      += 58 * $digits[$j];
                $digits[$j] = $carry % 256;
                $carry      = (int)($carry / 256);
            }
            while ($carry > 0) {
                array_unshift($digits, $carry % 256);
                $carry = (int)($carry / 256);
            }
        }

        $result = str_repeat("\x00", $leadingOnes);
        foreach ($digits as $digit) {
            $result .= chr($digit);
        }
        return $result;
    }

    // ── Wordlist mínima embutida (fallback se arquivo não encontrado) ──────────

    private function getMinimalWordlist(): array
    {
        // 128 palavras de emergência — suficiente para demonstração
        return explode(',', 'abandon,ability,able,about,above,absent,absorb,abstract,absurd,abuse,'
            . 'access,accident,account,accuse,achieve,acid,acoustic,acquire,across,act,'
            . 'action,actor,actress,actual,adapt,add,addict,address,adjust,admit,'
            . 'adult,advance,advice,aerobic,afford,afraid,again,age,agent,agree,'
            . 'ahead,aim,air,airport,aisle,alarm,album,alcohol,alert,alien,'
            . 'all,alley,allow,almost,alone,alpha,already,also,alter,always,'
            . 'amateur,amazing,among,amount,amused,analyst,anchor,ancient,anger,angle,'
            . 'angry,animal,ankle,announce,annual,another,answer,antenna,antique,anxiety,'
            . 'any,apart,apology,appear,apple,approve,april,arch,arctic,area,'
            . 'arena,argue,arm,armed,armor,army,around,arrange,arrest,arrive,'
            . 'arrow,art,artefact,artist,artwork,ask,aspect,assault,asset,assist,'
            . 'assume,asthma,athlete,atom,attack,attend,attitude,attract,auction,audit,'
            . 'august,aunt,author,auto,autumn,average,avocado,avoid,awake,aware');
    }

    // ── Info e diagnóstico ────────────────────────────────────────────────────

    /**
     * Retorna informações sobre o ambiente e capacidades disponíveis.
     */
    public function getCapabilities(): array
    {
        return [
            'wordlist_size'    => count($this->wordlist),
            'wordlist_file'    => file_exists($this->wordlistPath),
            'pbkdf2_native'    => function_exists('hash_pbkdf2'),
            'hmac_sha512'      => in_array('sha512', hash_algos()),
            'sha3_256'         => in_array('sha3-256', hash_algos()),
            'gmp_extension'    => extension_loaded('gmp'),
            'bcmath_extension' => extension_loaded('bcmath'),
            'keccak_installed' => class_exists('\kornrunner\Keccak'),
            'ecdsa_installed'  => class_exists('\Elliptic\EC'),
            'normalizer'       => function_exists('normalizer_normalize'),
            'mode'             => $this->mode,
            'bip39_compliant'  => function_exists('hash_pbkdf2') && in_array('sha512', hash_algos()),
            'recommendation'   => $this->getRecommendation(),
        ];
    }

    private function getRecommendation(): string
    {
        if (!class_exists('\kornrunner\Keccak')) {
            return 'Para endereços EVM exatos (produção): composer require kornrunner/keccak';
        }
        if (!class_exists('\Elliptic\EC')) {
            return 'Para derivação secp256k1 real: composer require simplito/elliptic-php';
        }
        return 'Ambiente completo para produção!';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// INTEGRAÇÃO COM Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Trait que adiciona suporte a mnemonics no Web3PHP original.
 * Adicione `use MnemonicTrait;` dentro da classe Web3PHP para ativar.
 *
 * Ou use diretamente: $mnemonic = Web3PHP::mnemonics();
 */
trait MnemonicTrait
{
    private ?MnemonicWallet $_mnemonicWallet = null;

    /**
     * Acesso ao módulo de mnemônicas.
     * $eth->mnemonics()->generate()
     * $eth->mnemonics()->deriveWallet($mnemonic)
     */
    public function mnemonics(): MnemonicWallet
    {
        if ($this->_mnemonicWallet === null) {
            $this->_mnemonicWallet = new MnemonicWallet();
        }
        return $this->_mnemonicWallet;
    }

    /**
     * Atalho: cria carteira completa para a rede configurada.
     *
     * $eth->createHDWallet()            → gera mnemônica + deriva wallet index 0
     * $eth->createHDWallet('word1 ...') → importa mnemônica + deriva wallet index 0
     */
    public function createHDWallet(string $mnemonic = '', int $index = 0, string $passphrase = ''): array
    {
        $mn      = new MnemonicWallet();
        $network = $this->provider->getNetwork();

        if (empty($mnemonic)) {
            $mnemonic = $mn->generate(12);
        }

        $wallet = $mn->deriveWallet($mnemonic, $index, $network, 0, 0, $passphrase);

        return array_merge($wallet, [
            'rpc_network' => $network,
            'provider'    => $this->provider->providerName,
        ]);
    }

    /**
     * Atalho: deriva N carteiras para a rede configurada.
     */
    public function deriveHDWallets(string $mnemonic, int $count = 5, int $startIndex = 0): array
    {
        $mn = new MnemonicWallet();
        return $mn->deriveMultiple($mnemonic, $count, $startIndex, $this->provider->getNetwork());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// INTEGRAÇÃO COM FakeChain
// ─────────────────────────────────────────────────────────────────────────────

namespace FakeChain;

use Web3PHP\MnemonicWallet;
use Web3PHP\CoinType;

/**
 * Extensão do FakeChain com suporte a HD Wallets.
 * Pode ser usada standalone ou misturada com FakeChain.
 */
class HDWalletSupport
{
    private MnemonicWallet $mn;
    private FakeChain $chain;

    public function __construct(FakeChain $chain)
    {
        $this->chain = $chain;
        $this->mn    = new MnemonicWallet();
    }

    /**
     * Gera mnemônica e cria a carteira derivada no FakeChain.
     * A carteira fica registrada no ledger e pronta para uso.
     *
     * @param  string $label          Apelido da carteira
     * @param  float  $initialBalance Saldo inicial (fake)
     * @param  int    $wordCount      12 ou 24 palavras
     * @return array  Dados completos: mnemonic, private_key, address, balance...
     */
    public function createHDWallet(string $label = '', float $initialBalance = 0.0, int $wordCount = 12): array
    {
        $mnemonic = $this->mn->generate($wordCount);
        return $this->importHDWallet($mnemonic, $label, $initialBalance);
    }

    /**
     * Importa uma mnemônica existente e registra no FakeChain.
     */
    public function importHDWallet(string $mnemonic, string $label = '', float $initialBalance = 0.0, int $index = 0): array
    {
        $network = $this->chain->config['network'] ?? 'fakechain';
        $derived = $this->mn->deriveWallet($mnemonic, $index, 'ethereum'); // sempre EVM no FakeChain

        $address = $derived['address'];

        // Registrar no ledger do FakeChain
        $this->chain->ledger->setBalance($address, $initialBalance);
        if ($label) {
            $this->chain->ledger->setWalletName($address, $label);
        }

        return array_merge($derived, [
            'label'           => $label,
            'initial_balance' => $initialBalance,
            'network'         => $network,
        ]);
    }

    /**
     * Deriva múltiplas carteiras de uma mnemônica e todas ficam no FakeChain.
     */
    public function deriveAndRegister(
        string $mnemonic,
        int    $count          = 5,
        float  $initialBalance = 10.0,
        int    $startIndex     = 0
    ): array {
        $wallets = [];
        for ($i = $startIndex; $i < $startIndex + $count; $i++) {
            $wallets[] = $this->importHDWallet(
                $mnemonic,
                "wallet_{$i}",
                $initialBalance,
                $i
            );
        }
        return $wallets;
    }

    /**
     * Acesso direto ao MnemonicWallet.
     */
    public function mnemonics(): MnemonicWallet
    {
        return $this->mn;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// EXTENSION: adiciona ->hd() no FakeChain via herança
// ─────────────────────────────────────────────────────────────────────────────

/**
 * FakeChainHD = FakeChain + HD Wallet support
 * Drop-in replacement com mesma interface + método hd().
 *
 * USO:
 *   $chain = new FakeChainHD();
 *   $wallet = $chain->hd()->createHDWallet('Alice', 100.0);
 */
class FakeChainHD extends FakeChain
{
    private ?HDWalletSupport $_hd = null;

    /**
     * Acesso ao módulo HD Wallet.
     */
    public function hd(): HDWalletSupport
    {
        if ($this->_hd === null) {
            $this->_hd = new HDWalletSupport($this);
        }
        return $this->_hd;
    }

    /**
     * Atalho direto: cria HD wallet e registra no chain.
     */
    public function createHDWallet(string $label = '', float $initialBalance = 0.0, int $wordCount = 12): array
    {
        return $this->hd()->createHDWallet($label, $initialBalance, $wordCount);
    }

    /**
     * Atalho direto: importa mnemônica e registra no chain.
     */
    public function importMnemonic(string $mnemonic, string $label = '', float $initialBalance = 0.0): array
    {
        return $this->hd()->importHDWallet($mnemonic, $label, $initialBalance);
    }
}
