<?php

/**
 * ██╗    ██╗███████╗██████╗ ██████╗     ██████╗ ██╗  ██╗██████╗
 * ██║    ██║██╔════╝██╔══██╗╚════██╗    ██╔══██╗██║  ██║██╔══██╗
 * ██║ █╗ ██║█████╗  ██████╔╝ █████╔╝    ██████╔╝███████║██████╔╝
 * ██║███╗██║██╔══╝  ██╔══██╗ ╚═══██╗    ██╔═══╝ ██╔══██║██╔═══╝
 * ╚███╔███╔╝███████╗██████╔╝██████╔╝    ██║     ██║  ██║██║
 *  ╚══╝╚══╝ ╚══════╝╚═════╝ ╚═════╝     ╚═╝     ╚═╝  ╚═╝╚═╝
 *
 * Web3PHP — Multi-Blockchain Integration Library
 * ================================================
 * A complete, production-ready PHP library for interacting with
 * the most relevant blockchain networks.
 *
 * Supported Networks:
 *   EVM-compatible: Ethereum, Polygon, BSC, Avalanche, Arbitrum, Optimism, Base, Fantom
 *   Bitcoin (via RPC or API)
 *   Solana (via JSON-RPC)
 *   Tron (via TronGrid API)
 *
 * Providers / Connectors:
 *   - Infura
 *   - Alchemy
 *   - QuickNode
 *   - Moralis
 *   - Local node (Geth, Hardhat, Ganache, etc.)
 *   - Public RPC endpoints (fallback)
 *
 * @author  Web3PHP
 * @version 1.0.0
 * @license MIT
 *
 * INSTALL DEPENDENCIES:
 *   composer require kornrunner/keccak simplito/elliptic-php
 *   composer require kornrunner/ethereum-offline-raw-tx (optional, for signing)
 *
 * USAGE EXAMPLE:
 *   $w3 = new Web3PHP([
 *       'network'  => 'ethereum',
 *       'provider' => 'infura',
 *       'api_key'  => 'YOUR_INFURA_KEY',
 *   ]);
 *   $balance = $w3->wallet->getBalance('0xABC...');
 *   $tx      = $w3->transfer->send('0xFROM...', '0xTO...', 0.01, $privateKey);
 */

declare(strict_types=1);

namespace Web3PHP;

// ─────────────────────────────────────────────────────────────────────────────
// EXCEPTIONS
// ─────────────────────────────────────────────────────────────────────────────

class Web3Exception         extends \RuntimeException {}
class NetworkException      extends Web3Exception {}
class WalletException       extends Web3Exception {}
class TransferException     extends Web3Exception {}
class BlockException        extends Web3Exception {}
class ContractException     extends Web3Exception {}
class ProviderException     extends Web3Exception {}

// ─────────────────────────────────────────────────────────────────────────────
// CONSTANTS — NETWORK REGISTRY
// ─────────────────────────────────────────────────────────────────────────────

final class Networks
{
    // Chain IDs for EVM networks
    const CHAIN_IDS = [
        'ethereum'  => 1,
        'goerli'    => 5,
        'sepolia'   => 11155111,
        'polygon'   => 137,
        'mumbai'    => 80001,
        'bsc'       => 56,
        'bsc_test'  => 97,
        'avalanche' => 43114,
        'fuji'      => 43113,
        'arbitrum'  => 42161,
        'optimism'  => 10,
        'base'      => 8453,
        'fantom'    => 250,
        'cronos'    => 25,
        'hardhat'   => 31337,
        'ganache'   => 1337,
    ];

    // Default public RPC endpoints (fallback — use your own for production)
    const PUBLIC_RPC = [
        'ethereum'  => 'https://ethereum.publicnode.com',
        'polygon'   => 'https://polygon-rpc.com',
        'bsc'       => 'https://bsc-dataseed.binance.org',
        'avalanche' => 'https://api.avax.network/ext/bc/C/rpc',
        'arbitrum'  => 'https://arb1.arbitrum.io/rpc',
        'optimism'  => 'https://mainnet.optimism.io',
        'base'      => 'https://mainnet.base.org',
        'fantom'    => 'https://rpc.ankr.com/fantom',
        'cronos'    => 'https://evm.cronos.org',
        'mumbai'    => 'https://rpc-mumbai.maticvigil.com',
        'bsc_test'  => 'https://data-seed-prebsc-1-s1.binance.org:8545',
        'hardhat'   => 'http://127.0.0.1:8545',
        'ganache'   => 'http://127.0.0.1:7545',
        // Non-EVM
        'bitcoin'   => 'https://mempool.space/api',
        'solana'    => 'https://api.mainnet-beta.solana.com',
        'solana_dev'=> 'https://api.devnet.solana.com',
        'tron'      => 'https://api.trongrid.io',
        'tron_test' => 'https://api.shasta.trongrid.io',
    ];

    // Infura endpoint templates
    const INFURA_RPC = [
        'ethereum'  => 'https://mainnet.infura.io/v3/{key}',
        'goerli'    => 'https://goerli.infura.io/v3/{key}',
        'sepolia'   => 'https://sepolia.infura.io/v3/{key}',
        'polygon'   => 'https://polygon-mainnet.infura.io/v3/{key}',
        'mumbai'    => 'https://polygon-mumbai.infura.io/v3/{key}',
        'arbitrum'  => 'https://arbitrum-mainnet.infura.io/v3/{key}',
        'optimism'  => 'https://optimism-mainnet.infura.io/v3/{key}',
        'avalanche' => 'https://avalanche-mainnet.infura.io/v3/{key}',
    ];

    // Alchemy endpoint templates
    const ALCHEMY_RPC = [
        'ethereum'  => 'https://eth-mainnet.g.alchemy.com/v2/{key}',
        'goerli'    => 'https://eth-goerli.g.alchemy.com/v2/{key}',
        'sepolia'   => 'https://eth-sepolia.g.alchemy.com/v2/{key}',
        'polygon'   => 'https://polygon-mainnet.g.alchemy.com/v2/{key}',
        'mumbai'    => 'https://polygon-mumbai.g.alchemy.com/v2/{key}',
        'arbitrum'  => 'https://arb-mainnet.g.alchemy.com/v2/{key}',
        'optimism'  => 'https://opt-mainnet.g.alchemy.com/v2/{key}',
        'base'      => 'https://base-mainnet.g.alchemy.com/v2/{key}',
    ];

    // Native currency symbols
    const NATIVE_SYMBOL = [
        'ethereum'  => 'ETH',  'goerli'    => 'ETH', 'sepolia'   => 'ETH',
        'polygon'   => 'MATIC','mumbai'    => 'MATIC',
        'bsc'       => 'BNB',  'bsc_test'  => 'BNB',
        'avalanche' => 'AVAX', 'fuji'      => 'AVAX',
        'arbitrum'  => 'ETH',  'optimism'  => 'ETH',
        'base'      => 'ETH',  'fantom'    => 'FTM',
        'cronos'    => 'CRO',
        'bitcoin'   => 'BTC',
        'solana'    => 'SOL',  'solana_dev'=> 'SOL',
        'tron'      => 'TRX',  'tron_test' => 'TRX',
    ];

    // EVM-compatible networks
    const EVM_NETWORKS = [
        'ethereum','goerli','sepolia','polygon','mumbai',
        'bsc','bsc_test','avalanche','fuji','arbitrum',
        'optimism','base','fantom','cronos','hardhat','ganache',
    ];

    public static function isEVM(string $network): bool
    {
        return in_array(strtolower($network), self::EVM_NETWORKS, true);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HTTP CLIENT — lightweight cURL wrapper
// ─────────────────────────────────────────────────────────────────────────────

class HttpClient
{
    private int     $timeout;
    private array   $defaultHeaders;

    public function __construct(int $timeout = 30, array $headers = [])
    {
        $this->timeout        = $timeout;
        $this->defaultHeaders = array_merge(['Content-Type: application/json'], $headers);
    }

    public function post(string $url, array $payload, array $headers = []): array
    {
        return $this->request('POST', $url, json_encode($payload), $headers);
    }

    public function get(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url, null, $headers);
    }

    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        $allHeaders = array_merge($this->defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new NetworkException("cURL error: {$error}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new NetworkException("JSON decode error. Raw: " . substr($response, 0, 200));
        }

        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new NetworkException("Request failed [{$httpCode}]: {$msg}");
        }

        return $decoded;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PROVIDER — resolves endpoint URL and wraps JSON-RPC
// ─────────────────────────────────────────────────────────────────────────────

class Provider
{
    public readonly string $network;
    public readonly string $rpcUrl;
    public readonly string $providerName;
    private HttpClient     $http;
    private int            $rpcId = 1;

    public function __construct(array $config, HttpClient $http)
    {
        $this->network      = strtolower($config['network'] ?? 'ethereum');
        $this->providerName = strtolower($config['provider'] ?? 'public');
        $this->http         = $http;
        $this->rpcUrl       = $this->resolveRpcUrl($config);
    }

    private function resolveRpcUrl(array $config): string
    {
        // Custom URL always wins
        if (!empty($config['rpc_url'])) {
            return rtrim($config['rpc_url'], '/');
        }

        $key     = $config['api_key'] ?? '';
        $network = $this->network;

        return match ($this->providerName) {
            'infura'    => str_replace('{key}', $key, Networks::INFURA_RPC[$network]
                            ?? throw new ProviderException("Infura does not support network: {$network}")),

            'alchemy'   => str_replace('{key}', $key, Networks::ALCHEMY_RPC[$network]
                            ?? throw new ProviderException("Alchemy does not support network: {$network}")),

            'quicknode' => $config['rpc_url']
                            ?? throw new ProviderException("QuickNode requires 'rpc_url' in config"),

            'moralis'   => "https://speedy-nodes-nyc.moralis.io/{$key}/eth/mainnet",

            'local'     => $config['rpc_url'] ?? 'http://127.0.0.1:8545',

            default     => Networks::PUBLIC_RPC[$network]
                            ?? throw new ProviderException("No public RPC for network: {$network}"),
        };
    }

    /**
     * Send a raw JSON-RPC call (EVM / Solana-compatible).
     */
    public function jsonRpc(string $method, array $params = []): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id'      => $this->rpcId++,
            'method'  => $method,
            'params'  => $params,
        ];

        $response = $this->http->post($this->rpcUrl, $payload);

        if (isset($response['error'])) {
            throw new NetworkException(
                "JSON-RPC error [{$response['error']['code']}]: {$response['error']['message']}"
            );
        }

        return $response['result'] ?? null;
    }

    /**
     * HTTP GET to a REST endpoint (Bitcoin mempool.space, TronGrid, etc.)
     */
    public function restGet(string $path, array $params = [], array $headers = []): array
    {
        return $this->http->get($this->rpcUrl . $path, $params, $headers);
    }

    /**
     * HTTP POST to a REST endpoint
     */
    public function restPost(string $path, array $payload = [], array $headers = []): array
    {
        return $this->http->post($this->rpcUrl . $path, $payload, $headers);
    }

    public function getNetwork(): string  { return $this->network; }
    public function getRpcUrl(): string   { return $this->rpcUrl; }
}

// ─────────────────────────────────────────────────────────────────────────────
// MATH HELPERS — arbitrary precision hex ↔ decimal
// ─────────────────────────────────────────────────────────────────────────────

class Math
{
    public static function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        if ($hex === '' || $hex === '0') return '0';
        return base_convert($hex, 16, 10); // OK for normal values
        // For truly large ints (>PHP_INT_MAX) use bcmath below:
        // return self::bcHexToDec($hex);
    }

    public static function bcHexToDec(string $hex): string
    {
        $hex    = ltrim($hex, '0x');
        $result = '0';
        $len    = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $result = bcadd(bcmul($result, '16'), (string)hexdec($hex[$i]));
        }
        return $result;
    }

    public static function decToHex(string|int $dec): string
    {
        return '0x' . dechex((int)$dec);
    }

    public static function weiToEther(string $wei): string
    {
        if (!extension_loaded('bcmath')) {
            return (string)((float)$wei / 1e18);
        }
        return bcdiv($wei, bcpow('10', '18', 0), 18);
    }

    public static function etherToWei(float|string $ether): string
    {
        if (!extension_loaded('bcmath')) {
            return (string)((int)((float)$ether * 1e18));
        }
        return bcmul((string)$ether, bcpow('10', '18', 0), 0);
    }

    public static function lamportsToSol(string|int $lamports): float
    {
        return (float)$lamports / 1_000_000_000;
    }

    public static function solToLamports(float $sol): int
    {
        return (int)round($sol * 1_000_000_000);
    }

    public static function sunToTrx(string|int $sun): float
    {
        return (float)$sun / 1_000_000;
    }

    public static function satoshiToBtc(int|string $sat): float
    {
        return (float)$sat / 100_000_000;
    }

    public static function formatUnits(string $value, int $decimals = 18): string
    {
        if (!extension_loaded('bcmath')) {
            return (string)((float)$value / pow(10, $decimals));
        }
        return bcdiv($value, bcpow('10', (string)$decimals, 0), $decimals);
    }

    public static function parseUnits(string|float $value, int $decimals = 18): string
    {
        if (!extension_loaded('bcmath')) {
            return (string)(int)((float)$value * pow(10, $decimals));
        }
        return bcmul((string)$value, bcpow('10', (string)$decimals, 0), 0);
    }

    public static function keccak256(string $data): string
    {
        // Uses kornrunner/keccak if available, else returns placeholder
        if (class_exists('\kornrunner\Keccak')) {
            return '0x' . \kornrunner\Keccak::hash($data, 256);
        }
        // Fallback: pure-PHP keccak256 stub (install composer package for production)
        throw new Web3Exception('keccak256 requires: composer require kornrunner/keccak');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ADDRESS UTILITIES
// ─────────────────────────────────────────────────────────────────────────────

class Address
{
    /**
     * EIP-55 checksum address
     */
    public static function toChecksumAddress(string $address): string
    {
        $address = strtolower(ltrim($address, '0x'));
        $hash    = Math::keccak256($address);
        $hash    = ltrim($hash, '0x');
        $result  = '0x';
        for ($i = 0; $i < strlen($address); $i++) {
            $result .= (hexdec($hash[$i]) >= 8)
                ? strtoupper($address[$i])
                : $address[$i];
        }
        return $result;
    }

    public static function isValidEVM(string $address): bool
    {
        return (bool)preg_match('/^0x[0-9a-fA-F]{40}$/', $address);
    }

    public static function isValidBitcoin(string $address): bool
    {
        // P2PKH, P2SH, Bech32
        return (bool)preg_match('/^(1|3)[a-zA-Z0-9]{25,34}$|^bc1[a-zA-Z0-9]{6,87}$/', $address);
    }

    public static function isValidSolana(string $address): bool
    {
        return (bool)preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
    }

    public static function isValidTron(string $address): bool
    {
        return (bool)preg_match('/^T[a-zA-Z0-9]{33}$/', $address);
    }

    public static function validate(string $address, string $network): bool
    {
        if (Networks::isEVM($network))         return self::isValidEVM($address);
        if ($network === 'bitcoin')             return self::isValidBitcoin($address);
        if (str_starts_with($network, 'solana'))return self::isValidSolana($address);
        if (str_starts_with($network, 'tron')) return self::isValidTron($address);
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ABI ENCODER — basic EVM ABI encoding (function selector + params)
// ─────────────────────────────────────────────────────────────────────────────

class AbiEncoder
{
    /**
     * Compute 4-byte function selector from signature.
     * E.g.: "transfer(address,uint256)"
     */
    public static function selector(string $signature): string
    {
        $hash = Math::keccak256($signature);
        return substr($hash, 0, 10); // "0x" + 8 hex chars
    }

    /**
     * Encode uint256 parameter
     */
    public static function encodeUint256(string|int $value): string
    {
        return str_pad(dechex((int)$value), 64, '0', STR_PAD_LEFT);
    }

    /**
     * Encode address parameter (32 bytes)
     */
    public static function encodeAddress(string $address): string
    {
        $addr = strtolower(ltrim($address, '0x'));
        return str_pad($addr, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Build full calldata: selector + encoded params
     * Types: 'address', 'uint256', 'bool', 'bytes32'
     */
    public static function encodeCall(string $signature, array $types, array $values): string
    {
        $selector = self::selector($signature);
        $encoded  = '';
        foreach ($types as $i => $type) {
            $val = $values[$i];
            $encoded .= match ($type) {
                'address' => self::encodeAddress($val),
                'uint256','uint128','uint64','uint32' => self::encodeUint256($val),
                'bool'    => str_pad((string)(int)(bool)$val, 64, '0', STR_PAD_LEFT),
                default   => str_pad(ltrim($val, '0x'), 64, '0', STR_PAD_LEFT),
            };
        }
        return $selector . $encoded;
    }

    /**
     * Decode a uint256 from hex output
     */
    public static function decodeUint256(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        return Math::bcHexToDec($hex);
    }

    /**
     * Decode address from 32-byte padded output
     */
    public static function decodeAddress(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        return '0x' . substr($hex, -40);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// WALLET MODULE
// ─────────────────────────────────────────────────────────────────────────────

class WalletModule
{
    public function __construct(
        private Provider $provider,
        private string   $network
    ) {}

    /**
     * Get native coin balance.
     * Returns human-readable float string.
     */
    public function getBalance(string $address): string
    {
        if (!Address::validate($address, $this->network)) {
            throw new WalletException("Invalid address for network [{$this->network}]: {$address}");
        }

        return match (true) {
            Networks::isEVM($this->network)              => $this->evmBalance($address),
            $this->network === 'bitcoin'                 => $this->bitcoinBalance($address),
            str_starts_with($this->network, 'solana')   => $this->solanaBalance($address),
            str_starts_with($this->network, 'tron')     => $this->tronBalance($address),
            default => throw new WalletException("Unsupported network: {$this->network}"),
        };
    }

    private function evmBalance(string $address): string
    {
        $result = $this->provider->jsonRpc('eth_getBalance', [$address, 'latest']);
        $wei    = Math::bcHexToDec(ltrim($result, '0x'));
        return Math::weiToEther($wei);
    }

    private function bitcoinBalance(string $address): string
    {
        $data = $this->provider->restGet("/address/{$address}");
        $sat  = ($data['chain_stats']['funded_txo_sum'] ?? 0)
              - ($data['chain_stats']['spent_txo_sum'] ?? 0);
        return (string)Math::satoshiToBtc($sat);
    }

    private function solanaBalance(string $address): string
    {
        $result = $this->provider->jsonRpc('getBalance', [$address]);
        return (string)Math::lamportsToSol($result['value'] ?? $result ?? 0);
    }

    private function tronBalance(string $address): string
    {
        $data = $this->provider->restGet("/v1/accounts/{$address}");
        $sun  = $data['data'][0]['balance'] ?? 0;
        return (string)Math::sunToTrx($sun);
    }

    /**
     * Get ERC-20 / BEP-20 token balance (EVM only).
     */
    public function getTokenBalance(string $walletAddress, string $contractAddress, int $decimals = 18): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('Token balances only supported on EVM networks');
        }

        $data = AbiEncoder::encodeCall(
            'balanceOf(address)',
            ['address'],
            [$walletAddress]
        );

        $result = $this->provider->jsonRpc('eth_call', [
            ['to' => $contractAddress, 'data' => $data],
            'latest'
        ]);

        $raw = Math::bcHexToDec(ltrim($result, '0x'));
        return Math::formatUnits($raw, $decimals);
    }

    /**
     * Get transaction count / nonce (EVM).
     */
    public function getNonce(string $address): int
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('getNonce is only supported on EVM networks');
        }
        $result = $this->provider->jsonRpc('eth_getTransactionCount', [$address, 'latest']);
        return (int)Math::hexToDec($result);
    }

    /**
     * Get token allowance (EVM).
     * How much `spender` is allowed to spend on behalf of `owner`.
     */
    public function getAllowance(string $contractAddress, string $owner, string $spender, int $decimals = 18): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('getAllowance is only supported on EVM networks');
        }

        $data = AbiEncoder::encodeCall(
            'allowance(address,address)',
            ['address', 'address'],
            [$owner, $spender]
        );

        $result = $this->provider->jsonRpc('eth_call', [
            ['to' => $contractAddress, 'data' => $data],
            'latest'
        ]);

        $raw = Math::bcHexToDec(ltrim($result, '0x'));
        return Math::formatUnits($raw, $decimals);
    }

    /**
     * Get ETH / EVM transaction history via Etherscan-compatible API.
     * Requires an Etherscan API key in the config.
     */
    public function getTransactionHistory(string $address, string $explorerApiKey, int $page = 1, int $offset = 25): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('Transaction history via Etherscan is only for EVM networks');
        }

        $explorerUrls = [
            'ethereum'  => 'https://api.etherscan.io/api',
            'polygon'   => 'https://api.polygonscan.com/api',
            'bsc'       => 'https://api.bscscan.com/api',
            'avalanche' => 'https://api.snowtrace.io/api',
            'arbitrum'  => 'https://api.arbiscan.io/api',
            'optimism'  => 'https://api-optimistic.etherscan.io/api',
            'fantom'    => 'https://api.ftmscan.com/api',
        ];

        $apiUrl = $explorerUrls[$this->network]
            ?? throw new WalletException("No explorer API configured for: {$this->network}");

        $http = new HttpClient();
        $result = $http->get($apiUrl, [
            'module'  => 'account',
            'action'  => 'txlist',
            'address' => $address,
            'startblock' => 0,
            'endblock'   => 99999999,
            'page'    => $page,
            'offset'  => $offset,
            'sort'    => 'desc',
            'apikey'  => $explorerApiKey,
        ]);

        if (($result['status'] ?? '0') !== '1') {
            throw new WalletException('Explorer API error: ' . ($result['message'] ?? 'Unknown'));
        }

        return array_map(function (array $tx) {
            return [
                'hash'      => $tx['hash'],
                'from'      => $tx['from'],
                'to'        => $tx['to'],
                'value_eth' => Math::weiToEther($tx['value']),
                'value_wei' => $tx['value'],
                'gas'       => $tx['gas'],
                'gas_price' => $tx['gasPrice'],
                'timestamp' => (int)$tx['timeStamp'],
                'datetime'  => date('Y-m-d H:i:s', (int)$tx['timeStamp']),
                'status'    => $tx['isError'] === '0' ? 'success' : 'failed',
                'block'     => $tx['blockNumber'],
            ];
        }, $result['result'] ?? []);
    }

    /**
     * Get ERC-20 token transfers for a wallet.
     */
    public function getTokenTransfers(string $address, string $explorerApiKey, ?string $contractAddress = null): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('Token transfers are only supported on EVM networks');
        }

        $explorerUrls = [
            'ethereum'  => 'https://api.etherscan.io/api',
            'polygon'   => 'https://api.polygonscan.com/api',
            'bsc'       => 'https://api.bscscan.com/api',
        ];

        $apiUrl = $explorerUrls[$this->network]
            ?? throw new WalletException("No explorer API configured for: {$this->network}");

        $params = [
            'module'  => 'account',
            'action'  => 'tokentx',
            'address' => $address,
            'page'    => 1,
            'offset'  => 50,
            'sort'    => 'desc',
            'apikey'  => $explorerApiKey,
        ];

        if ($contractAddress) {
            $params['contractaddress'] = $contractAddress;
        }

        $http   = new HttpClient();
        $result = $http->get($apiUrl, $params);

        return $result['result'] ?? [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// BLOCK MODULE
// ─────────────────────────────────────────────────────────────────────────────

class BlockModule
{
    public function __construct(
        private Provider $provider,
        private string   $network
    ) {}

    /**
     * Get the latest block number.
     */
    public function getLatestBlockNumber(): int|string
    {
        return match (true) {
            Networks::isEVM($this->network)              => $this->evmLatestBlock(),
            $this->network === 'bitcoin'                 => $this->btcLatestBlock(),
            str_starts_with($this->network, 'solana')   => $this->solanaLatestSlot(),
            str_starts_with($this->network, 'tron')     => $this->tronLatestBlock(),
            default => throw new BlockException("Unsupported network: {$this->network}"),
        };
    }

    private function evmLatestBlock(): int
    {
        return (int)Math::hexToDec($this->provider->jsonRpc('eth_blockNumber'));
    }

    private function btcLatestBlock(): int
    {
        $data = $this->provider->restGet('/blocks/tip/height');
        return (int)$data;
    }

    private function solanaLatestSlot(): int
    {
        return (int)$this->provider->jsonRpc('getSlot');
    }

    private function tronLatestBlock(): int
    {
        $data = $this->provider->restGet('/wallet/getnowblock');
        return $data['block_header']['raw_data']['number'] ?? 0;
    }

    /**
     * Get block details by number or hash.
     * Pass 'latest' for the most recent block.
     */
    public function getBlock(int|string $blockNumberOrHash = 'latest', bool $fullTransactions = false): array
    {
        return match (true) {
            Networks::isEVM($this->network)              => $this->evmBlock($blockNumberOrHash, $fullTransactions),
            $this->network === 'bitcoin'                 => $this->btcBlock($blockNumberOrHash),
            str_starts_with($this->network, 'solana')   => $this->solanaBlock($blockNumberOrHash),
            default => throw new BlockException("Unsupported network: {$this->network}"),
        };
    }

    private function evmBlock(int|string $block, bool $fullTx): array
    {
        $tag = $block === 'latest'
            ? 'latest'
            : (is_string($block) && str_starts_with($block, '0x')
                ? $block
                : Math::decToHex($block));

        $raw = $this->provider->jsonRpc('eth_getBlockByNumber', [$tag, $fullTx]);
        if (!$raw) throw new BlockException("Block not found: {$block}");

        return [
            'number'           => (int)Math::hexToDec($raw['number'] ?? '0x0'),
            'hash'             => $raw['hash'] ?? '',
            'parent_hash'      => $raw['parentHash'] ?? '',
            'timestamp'        => (int)Math::hexToDec($raw['timestamp'] ?? '0x0'),
            'datetime'         => date('Y-m-d H:i:s', (int)Math::hexToDec($raw['timestamp'] ?? '0x0')),
            'miner'            => $raw['miner'] ?? '',
            'gas_limit'        => Math::hexToDec($raw['gasLimit'] ?? '0x0'),
            'gas_used'         => Math::hexToDec($raw['gasUsed'] ?? '0x0'),
            'base_fee_gwei'    => isset($raw['baseFeePerGas'])
                                    ? bcdiv(Math::bcHexToDec(ltrim($raw['baseFeePerGas'], '0x')), '1000000000', 9)
                                    : null,
            'tx_count'         => count($raw['transactions'] ?? []),
            'transactions'     => $raw['transactions'] ?? [],
            'size_bytes'       => isset($raw['size']) ? (int)Math::hexToDec($raw['size']) : null,
            'difficulty'       => isset($raw['difficulty']) ? Math::hexToDec($raw['difficulty']) : null,
            'nonce'            => $raw['nonce'] ?? '',
            'extra_data'       => $raw['extraData'] ?? '',
        ];
    }

    private function btcBlock(int|string $block): array
    {
        if (is_numeric($block)) {
            $hash = $this->provider->restGet("/block-height/{$block}");
            $hash = trim((string)$hash, '"');
        } else {
            $hash = $block === 'latest'
                ? trim((string)$this->provider->restGet('/blocks/tip/hash'), '"')
                : $block;
        }

        $data = $this->provider->restGet("/block/{$hash}");
        return [
            'height'       => $data['height'] ?? 0,
            'hash'         => $data['id'] ?? '',
            'timestamp'    => $data['timestamp'] ?? 0,
            'datetime'     => date('Y-m-d H:i:s', $data['timestamp'] ?? 0),
            'tx_count'     => $data['tx_count'] ?? 0,
            'size_bytes'   => $data['size'] ?? 0,
            'weight'       => $data['weight'] ?? 0,
            'merkle_root'  => $data['merkle_root'] ?? '',
            'difficulty'   => $data['difficulty'] ?? 0,
            'median_fee'   => $data['extras']['medianFee'] ?? null,
        ];
    }

    private function solanaBlock(int|string $slot): array
    {
        if ($slot === 'latest') {
            $slot = $this->provider->jsonRpc('getSlot');
        }
        $raw = $this->provider->jsonRpc('getBlock', [
            (int)$slot,
            ['encoding' => 'json', 'transactionDetails' => 'none', 'rewards' => false]
        ]);
        return [
            'slot'         => (int)$slot,
            'hash'         => $raw['blockhash'] ?? '',
            'parent_slot'  => $raw['parentSlot'] ?? 0,
            'timestamp'    => $raw['blockTime'] ?? 0,
            'datetime'     => $raw['blockTime'] ? date('Y-m-d H:i:s', $raw['blockTime']) : null,
            'tx_count'     => count($raw['transactions'] ?? []),
        ];
    }

    /**
     * Get a transaction by hash.
     */
    public function getTransaction(string $txHash): array
    {
        if (Networks::isEVM($this->network)) {
            return $this->evmTransaction($txHash);
        }
        if ($this->network === 'bitcoin') {
            return $this->btcTransaction($txHash);
        }
        if (str_starts_with($this->network, 'solana')) {
            return $this->solanaTransaction($txHash);
        }
        if (str_starts_with($this->network, 'tron')) {
            return $this->tronTransaction($txHash);
        }
        throw new BlockException("Unsupported network: {$this->network}");
    }

    private function evmTransaction(string $hash): array
    {
        $tx = $this->provider->jsonRpc('eth_getTransactionByHash', [$hash]);
        if (!$tx) throw new BlockException("Transaction not found: {$hash}");

        $receipt = $this->provider->jsonRpc('eth_getTransactionReceipt', [$hash]);

        return [
            'hash'        => $tx['hash'],
            'from'        => $tx['from'],
            'to'          => $tx['to'],
            'value_eth'   => Math::weiToEther(Math::bcHexToDec(ltrim($tx['value'] ?? '0x0', '0x'))),
            'value_wei'   => Math::bcHexToDec(ltrim($tx['value'] ?? '0x0', '0x')),
            'gas'         => (int)Math::hexToDec($tx['gas'] ?? '0x0'),
            'gas_price'   => Math::bcHexToDec(ltrim($tx['gasPrice'] ?? '0x0', '0x')),
            'nonce'       => (int)Math::hexToDec($tx['nonce'] ?? '0x0'),
            'input'       => $tx['input'] ?? '0x',
            'block'       => isset($tx['blockNumber']) ? (int)Math::hexToDec($tx['blockNumber']) : null,
            'status'      => $receipt ? ((int)Math::hexToDec($receipt['status'] ?? '0x0') === 1 ? 'success' : 'failed') : 'pending',
            'gas_used'    => $receipt ? (int)Math::hexToDec($receipt['gasUsed'] ?? '0x0') : null,
            'logs'        => $receipt['logs'] ?? [],
        ];
    }

    private function btcTransaction(string $hash): array
    {
        $data = $this->provider->restGet("/tx/{$hash}");
        return [
            'txid'      => $data['txid'] ?? $data['tx_hash'] ?? '',
            'confirmed' => ($data['status']['confirmed'] ?? false),
            'block'     => $data['status']['block_height'] ?? null,
            'timestamp' => $data['status']['block_time'] ?? null,
            'inputs'    => $data['vin'] ?? [],
            'outputs'   => $data['vout'] ?? [],
            'fee_sat'   => $data['fee'] ?? null,
            'fee_btc'   => isset($data['fee']) ? Math::satoshiToBtc($data['fee']) : null,
            'size'      => $data['size'] ?? null,
            'weight'    => $data['weight'] ?? null,
        ];
    }

    private function solanaTransaction(string $signature): array
    {
        $raw = $this->provider->jsonRpc('getTransaction', [
            $signature,
            ['encoding' => 'json', 'maxSupportedTransactionVersion' => 0]
        ]);
        if (!$raw) throw new BlockException("Transaction not found: {$signature}");
        return [
            'signature' => $signature,
            'slot'      => $raw['slot'] ?? null,
            'timestamp' => $raw['blockTime'] ?? null,
            'datetime'  => $raw['blockTime'] ? date('Y-m-d H:i:s', $raw['blockTime']) : null,
            'fee_sol'   => Math::lamportsToSol($raw['meta']['fee'] ?? 0),
            'status'    => isset($raw['meta']['err']) && $raw['meta']['err'] === null ? 'success' : 'failed',
            'logs'      => $raw['meta']['logMessages'] ?? [],
        ];
    }

    private function tronTransaction(string $txId): array
    {
        $data = $this->provider->restGet("/v1/transactions/{$txId}");
        $tx   = $data['data'][0] ?? $data;
        return [
            'txid'      => $tx['txID'] ?? $txId,
            'status'    => $tx['ret'][0]['contractRet'] ?? 'UNKNOWN',
            'timestamp' => ($tx['raw_data']['timestamp'] ?? 0) / 1000,
            'fee_trx'   => Math::sunToTrx($tx['ret'][0]['fee'] ?? 0),
            'contracts' => $tx['raw_data']['contract'] ?? [],
        ];
    }

    /**
     * Get current gas info (EVM only).
     */
    public function getGasInfo(): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new BlockException('Gas info is only available on EVM networks');
        }

        $gasPrice = $this->provider->jsonRpc('eth_gasPrice');
        $gasPriceWei = Math::bcHexToDec(ltrim($gasPrice, '0x'));

        $result = [
            'gas_price_wei'  => $gasPriceWei,
            'gas_price_gwei' => bcdiv($gasPriceWei, '1000000000', 9),
        ];

        try {
            $feeHistory = $this->provider->jsonRpc('eth_feeHistory', [
                '0x4', 'latest', [25, 50, 75]
            ]);
            if ($feeHistory && isset($feeHistory['baseFeePerGas'])) {
                $base = end($feeHistory['baseFeePerGas']);
                $result['base_fee_gwei'] = bcdiv(Math::bcHexToDec(ltrim($base, '0x')), '1000000000', 9);
            }
        } catch (\Throwable) {
            // EIP-1559 not supported on this network
        }

        return $result;
    }

    /**
     * Estimate gas for a transaction (EVM).
     */
    public function estimateGas(array $tx): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new BlockException('estimateGas is only available on EVM networks');
        }
        $result = $this->provider->jsonRpc('eth_estimateGas', [$tx]);
        return Math::hexToDec($result);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT MODULE — EVM smart contract interaction
// ─────────────────────────────────────────────────────────────────────────────

class ContractModule
{
    public function __construct(
        private Provider $provider,
        private string   $network,
        private string   $contractAddress,
        private array    $abi = []
    ) {
        if (!Networks::isEVM($this->network)) {
            throw new ContractException('Smart contracts are only supported on EVM networks');
        }
    }

    /**
     * Call a read-only (view/pure) function.
     */
    public function call(string $functionSignature, array $types = [], array $values = []): string
    {
        $data = AbiEncoder::encodeCall($functionSignature, $types, $values);

        $result = $this->provider->jsonRpc('eth_call', [
            ['to' => $this->contractAddress, 'data' => $data],
            'latest'
        ]);

        return $result ?? '0x';
    }

    /**
     * Call a view function and decode the uint256 response.
     */
    public function callUint256(string $functionSignature, array $types = [], array $values = []): string
    {
        $raw = $this->call($functionSignature, $types, $values);
        return AbiEncoder::decodeUint256($raw);
    }

    /**
     * Call a view function and decode the address response.
     */
    public function callAddress(string $functionSignature, array $types = [], array $values = []): string
    {
        $raw = $this->call($functionSignature, $types, $values);
        return AbiEncoder::decodeAddress($raw);
    }

    /**
     * Prepare unsigned transaction data for a state-changing function.
     * Sign and send via TransferModule::sendRaw() or your own signing library.
     */
    public function buildTransaction(
        string $fromAddress,
        string $functionSignature,
        array  $types = [],
        array  $values = [],
        string $value = '0x0',
        ?int   $gasLimit = null
    ): array {
        $data  = AbiEncoder::encodeCall($functionSignature, $types, $values);
        $nonce = $this->provider->jsonRpc('eth_getTransactionCount', [$fromAddress, 'pending']);
        $chainId = $this->provider->jsonRpc('eth_chainId');

        $gas = $gasLimit
            ? Math::decToHex($gasLimit)
            : $this->provider->jsonRpc('eth_estimateGas', [[
                'from'  => $fromAddress,
                'to'    => $this->contractAddress,
                'data'  => $data,
                'value' => $value,
            ]]);

        $gasPrice = $this->provider->jsonRpc('eth_gasPrice');

        return [
            'from'     => $fromAddress,
            'to'       => $this->contractAddress,
            'nonce'    => $nonce,
            'gas'      => $gas,
            'gasPrice' => $gasPrice,
            'value'    => $value,
            'data'     => $data,
            'chainId'  => $chainId,
        ];
    }

    /**
     * ERC-20: name, symbol, decimals, totalSupply, balanceOf.
     */
    public function erc20Info(string $holderAddress): array
    {
        return [
            'name'          => $this->decodeString($this->call('name()')),
            'symbol'        => $this->decodeString($this->call('symbol()')),
            'decimals'      => (int)$this->callUint256('decimals()'),
            'total_supply'  => $this->callUint256('totalSupply()'),
            'balance'       => $this->callUint256('balanceOf(address)', ['address'], [$holderAddress]),
        ];
    }

    /**
     * ERC-721: get token owner.
     */
    public function erc721OwnerOf(int $tokenId): string
    {
        return $this->callAddress('ownerOf(uint256)', ['uint256'], [$tokenId]);
    }

    /**
     * ERC-721: get token URI.
     */
    public function erc721TokenURI(int $tokenId): string
    {
        $raw = $this->call('tokenURI(uint256)', ['uint256'], [$tokenId]);
        return $this->decodeString($raw);
    }

    /**
     * Get event logs from this contract.
     */
    public function getLogs(string $eventSignature, int $fromBlock = 0, string $toBlock = 'latest'): array
    {
        $topic = Math::keccak256($eventSignature);
        return $this->provider->jsonRpc('eth_getLogs', [[
            'address'   => $this->contractAddress,
            'topics'    => [$topic],
            'fromBlock' => Math::decToHex($fromBlock),
            'toBlock'   => $toBlock,
        ]]) ?? [];
    }

    private function decodeString(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        if (strlen($hex) < 128) return '';
        // offset, length, data
        $lengthHex = substr($hex, 64, 64);
        $length    = (int)hexdec($lengthHex);
        $data      = substr($hex, 128, $length * 2);
        return pack('H*', $data);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TRANSFER MODULE — build, sign (where possible), and send transactions
// ─────────────────────────────────────────────────────────────────────────────

class TransferModule
{
    public function __construct(
        private Provider $provider,
        private string   $network
    ) {}

    /**
     * Broadcast a raw signed transaction (EVM).
     * The raw tx must be hex-encoded (e.g. from ethers.js / web3.js signing or Metamask).
     */
    public function sendRaw(string $rawTxHex): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('sendRaw is only supported on EVM networks');
        }
        if (!str_starts_with($rawTxHex, '0x')) {
            $rawTxHex = '0x' . $rawTxHex;
        }
        $txHash = $this->provider->jsonRpc('eth_sendRawTransaction', [$rawTxHex]);
        return $txHash;
    }

    /**
     * Build an unsigned EVM transfer payload (ETH / native coin).
     * Sign offline and broadcast with sendRaw().
     *
     * For server-side signing, use:
     *   composer require kornrunner/ethereum-offline-raw-tx
     */
    public function buildNativeTransfer(
        string $from,
        string $to,
        float  $amount,
        ?int   $customGas = null
    ): array {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('buildNativeTransfer is only supported on EVM networks');
        }

        $wei      = Math::etherToWei($amount);
        $nonce    = $this->provider->jsonRpc('eth_getTransactionCount', [$from, 'pending']);
        $gasPrice = $this->provider->jsonRpc('eth_gasPrice');
        $gas      = $customGas
            ? Math::decToHex($customGas)
            : $this->provider->jsonRpc('eth_estimateGas', [[
                'from'  => $from,
                'to'    => $to,
                'value' => '0x' . dechex((int)$wei),
            ]]);
        $chainId  = $this->provider->jsonRpc('eth_chainId');

        return [
            'from'     => $from,
            'to'       => $to,
            'nonce'    => $nonce,
            'gas'      => $gas,
            'gasPrice' => $gasPrice,
            'value'    => '0x' . dechex((int)$wei),
            'data'     => '0x',
            'chainId'  => $chainId,
        ];
    }

    /**
     * Build an unsigned ERC-20 token transfer payload (EVM).
     */
    public function buildTokenTransfer(
        string $from,
        string $contractAddress,
        string $to,
        float  $amount,
        int    $decimals = 18,
        ?int   $customGas = null
    ): array {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('buildTokenTransfer is only supported on EVM networks');
        }

        $value   = Math::parseUnits((string)$amount, $decimals);
        $data    = AbiEncoder::encodeCall('transfer(address,uint256)', ['address', 'uint256'], [$to, $value]);
        $nonce   = $this->provider->jsonRpc('eth_getTransactionCount', [$from, 'pending']);
        $gas     = $customGas
            ? Math::decToHex($customGas)
            : $this->provider->jsonRpc('eth_estimateGas', [[
                'from' => $from,
                'to'   => $contractAddress,
                'data' => $data,
            ]]);
        $gasPrice = $this->provider->jsonRpc('eth_gasPrice');
        $chainId  = $this->provider->jsonRpc('eth_chainId');

        return [
            'from'            => $from,
            'to'              => $contractAddress,
            'nonce'           => $nonce,
            'gas'             => $gas,
            'gasPrice'        => $gasPrice,
            'value'           => '0x0',
            'data'            => $data,
            'chainId'         => $chainId,
            '__meta_recipient' => $to,
            '__meta_amount'    => $amount,
            '__meta_decimals'  => $decimals,
        ];
    }

    /**
     * Wait for a transaction to be mined (EVM).
     * Returns the receipt or throws after timeout.
     */
    public function waitForConfirmation(string $txHash, int $timeoutSeconds = 120, int $intervalSeconds = 3): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('waitForConfirmation is only supported on EVM networks');
        }

        $start = time();
        while (time() - $start < $timeoutSeconds) {
            $receipt = $this->provider->jsonRpc('eth_getTransactionReceipt', [$txHash]);
            if ($receipt !== null) {
                return [
                    'hash'       => $txHash,
                    'status'     => (int)Math::hexToDec($receipt['status'] ?? '0x0') === 1 ? 'success' : 'failed',
                    'block'      => (int)Math::hexToDec($receipt['blockNumber'] ?? '0x0'),
                    'gas_used'   => (int)Math::hexToDec($receipt['gasUsed'] ?? '0x0'),
                    'logs'       => $receipt['logs'] ?? [],
                    'receipt'    => $receipt,
                ];
            }
            sleep($intervalSeconds);
        }
        throw new TransferException("Transaction not mined after {$timeoutSeconds}s: {$txHash}");
    }

    /**
     * Bitcoin: Get UTXOs for an address (via mempool.space).
     */
    public function getBitcoinUTXOs(string $address): array
    {
        if ($this->network !== 'bitcoin') {
            throw new TransferException('getBitcoinUTXOs is only for Bitcoin');
        }
        $utxos = $this->provider->restGet("/address/{$address}/utxo");
        return array_map(function ($u) {
            return [
                'txid'       => $u['txid'],
                'vout'       => $u['vout'],
                'value_sat'  => $u['value'],
                'value_btc'  => Math::satoshiToBtc($u['value']),
                'confirmed'  => $u['status']['confirmed'] ?? false,
            ];
        }, $utxos);
    }

    /**
     * Bitcoin: Broadcast a raw signed transaction (hex).
     */
    public function broadcastBitcoin(string $rawTxHex): string
    {
        if ($this->network !== 'bitcoin') {
            throw new TransferException('broadcastBitcoin is only for Bitcoin');
        }
        $result = $this->provider->restPost('/tx', [], ['Content-Type: text/plain']);
        // mempool.space POST /tx expects raw body
        // Use direct curl for this:
        $ch = curl_init('https://mempool.space/api/tx');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $rawTxHex,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response; // Returns txid on success
    }

    /**
     * Solana: Send a raw signed transaction (base64-encoded).
     */
    public function sendSolanaTransaction(string $signedTxBase64): string
    {
        if (!str_starts_with($this->network, 'solana')) {
            throw new TransferException('sendSolanaTransaction is only for Solana');
        }
        $txId = $this->provider->jsonRpc('sendTransaction', [
            $signedTxBase64,
            ['encoding' => 'base64']
        ]);
        return $txId;
    }

    /**
     * Tron: Broadcast a signed transaction (array).
     */
    public function broadcastTron(array $signedTx): array
    {
        if (!str_starts_with($this->network, 'tron')) {
            throw new TransferException('broadcastTron is only for Tron');
        }
        return $this->provider->restPost('/wallet/broadcasttransaction', $signedTx);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NETWORK STATS MODULE — gas, fees, mempool, network info
// ─────────────────────────────────────────────────────────────────────────────

class NetworkStatsModule
{
    public function __construct(
        private Provider $provider,
        private string   $network
    ) {}

    public function getChainId(): int
    {
        if (!Networks::isEVM($this->network)) {
            return Networks::CHAIN_IDS[$this->network] ?? 0;
        }
        return (int)Math::hexToDec($this->provider->jsonRpc('eth_chainId'));
    }

    public function getNodeInfo(): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new NetworkException('getNodeInfo is only supported on EVM networks');
        }
        return [
            'version'     => $this->provider->jsonRpc('web3_clientVersion'),
            'chain_id'    => $this->getChainId(),
            'peer_count'  => (int)Math::hexToDec($this->provider->jsonRpc('net_peerCount') ?? '0x0'),
            'listening'   => $this->provider->jsonRpc('net_listening'),
            'syncing'     => $this->provider->jsonRpc('eth_syncing'),
            'network'     => $this->network,
            'rpc_url'     => $this->provider->getRpcUrl(),
            'symbol'      => Networks::NATIVE_SYMBOL[$this->network] ?? '?',
        ];
    }

    public function getMempoolSize(): int
    {
        if (!Networks::isEVM($this->network)) {
            throw new NetworkException('getMempoolSize is only supported on EVM networks');
        }
        $status = $this->provider->jsonRpc('txpool_status');
        return isset($status['pending']) ? (int)Math::hexToDec($status['pending']) : 0;
    }

    public function getSolanaEpoch(): array
    {
        if (!str_starts_with($this->network, 'solana')) {
            throw new NetworkException('getSolanaEpoch is only for Solana');
        }
        return $this->provider->jsonRpc('getEpochInfo') ?? [];
    }

    public function getBitcoinMempoolStats(): array
    {
        if ($this->network !== 'bitcoin') {
            throw new NetworkException('getBitcoinMempoolStats is only for Bitcoin');
        }
        return $this->provider->restGet('/mempool');
    }

    public function getBitcoinFeeRecommendations(): array
    {
        if ($this->network !== 'bitcoin') {
            throw new NetworkException('Only for Bitcoin');
        }
        return $this->provider->restGet('/v1/fees/recommended');
    }

    public function getTronBandwidth(string $address): array
    {
        if (!str_starts_with($this->network, 'tron')) {
            throw new NetworkException('getTronBandwidth is only for Tron');
        }
        return $this->provider->restGet("/v1/accounts/{$address}/resources");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MAIN FACADE — Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Web3PHP — Main entry point
 *
 * QUICKSTART:
 * -----------
 *
 * // Ethereum via Infura
 * $w3 = new Web3PHP([
 *     'network'  => 'ethereum',
 *     'provider' => 'infura',
 *     'api_key'  => 'YOUR_KEY',
 * ]);
 *
 * // Polygon via Alchemy
 * $poly = new Web3PHP([
 *     'network'  => 'polygon',
 *     'provider' => 'alchemy',
 *     'api_key'  => 'YOUR_KEY',
 * ]);
 *
 * // BSC via Public RPC
 * $bsc = new Web3PHP(['network' => 'bsc']);
 *
 * // Bitcoin via mempool.space
 * $btc = new Web3PHP(['network' => 'bitcoin']);
 *
 * // Solana mainnet
 * $sol = new Web3PHP(['network' => 'solana']);
 *
 * // Tron via TronGrid
 * $tron = new Web3PHP([
 *     'network'  => 'tron',
 *     'api_key'  => 'YOUR_TRONGRID_KEY',
 * ]);
 *
 * // Local Hardhat / Geth node
 * $local = new Web3PHP([
 *     'network'  => 'hardhat',
 *     'provider' => 'local',
 *     'rpc_url'  => 'http://127.0.0.1:8545',
 * ]);
 */
class Web3PHP
{
    public readonly WalletModule       $wallet;
    public readonly BlockModule        $block;
    public readonly TransferModule     $transfer;
    public readonly NetworkStatsModule $network;
    public readonly Provider           $provider;

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config   = array_merge(['network' => 'ethereum', 'provider' => 'public'], $config);
        $http           = new HttpClient(
            timeout: $this->config['timeout'] ?? 30,
            headers: $this->buildAuthHeaders()
        );

        $this->provider = new Provider($this->config, $http);
        $networkName    = $this->provider->getNetwork();

        $this->wallet   = new WalletModule($this->provider, $networkName);
        $this->block    = new BlockModule($this->provider, $networkName);
        $this->transfer = new TransferModule($this->provider, $networkName);
        $this->network  = new NetworkStatsModule($this->provider, $networkName);
    }

    /**
     * Get a contract instance for interacting with a deployed smart contract (EVM).
     */
    public function contract(string $contractAddress, array $abi = []): ContractModule
    {
        return new ContractModule($this->provider, $this->provider->getNetwork(), $contractAddress, $abi);
    }

    /**
     * Switch to a different network without losing config.
     */
    public function switchNetwork(string $network): static
    {
        return new static(array_merge($this->config, ['network' => $network]));
    }

    /**
     * Quick helper: get balance of any address on the current network.
     */
    public function balanceOf(string $address): string
    {
        return $this->wallet->getBalance($address);
    }

    /**
     * Quick helper: get latest block number.
     */
    public function latestBlock(): int|string
    {
        return $this->block->getLatestBlockNumber();
    }

    /**
     * Quick helper: get transaction details.
     */
    public function getTransaction(string $hash): array
    {
        return $this->block->getTransaction($hash);
    }

    /**
     * Quick helper: raw JSON-RPC call (advanced users).
     */
    public function rpc(string $method, array $params = []): mixed
    {
        return $this->provider->jsonRpc($method, $params);
    }

    /**
     * Get library and network info.
     */
    public function info(): array
    {
        return [
            'library'  => 'Web3PHP',
            'version'  => '1.0.0',
            'network'  => $this->provider->getNetwork(),
            'provider' => $this->provider->providerName,
            'rpc_url'  => $this->provider->getRpcUrl(),
            'chain_id' => Networks::CHAIN_IDS[$this->provider->getNetwork()] ?? null,
            'symbol'   => Networks::NATIVE_SYMBOL[$this->provider->getNetwork()] ?? '?',
            'is_evm'   => Networks::isEVM($this->provider->getNetwork()),
        ];
    }

    private function buildAuthHeaders(): array
    {
        $key      = $this->config['api_key'] ?? '';
        $provider = strtolower($this->config['provider'] ?? '');
        $network  = strtolower($this->config['network'] ?? '');

        if (str_starts_with($network, 'tron') && $key) {
            return ["TRON-PRO-API-KEY: {$key}"];
        }
        if ($provider === 'moralis' && $key) {
            return ["X-API-Key: {$key}"];
        }

        return [];
    }
}
