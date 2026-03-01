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
 * @version 1.0.2
 * @license MIT
 *
 * CHANGELOG v1.0.2:
 *   [F-01] WalletModule: Address::validate() added to getTokenBalance(),
 *          getNonce(), getAllowance() — prevents invalid addresses reaching RPC.
 *   [F-02] Provider: api_key is masked in getRpcUrl() output (safe for logs).
 *          Internal $rpcUrl remains unmasked for actual HTTP calls.
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
 *   $tx      = $w3->transfer->buildNativeTransfer('0xFROM...', '0xTO...', 0.01);
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
        'bitcoin'   => 'https://mempool.space/api',
        'solana'    => 'https://api.mainnet-beta.solana.com',
        'solana_dev'=> 'https://api.devnet.solana.com',
        'tron'      => 'https://api.trongrid.io',
        'tron_test' => 'https://api.shasta.trongrid.io',
    ];

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

    const NATIVE_SYMBOL = [
        'ethereum'  => 'ETH',  'goerli'    => 'ETH',  'sepolia'   => 'ETH',
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

    const EXPLORER_API = [
        'ethereum'  => 'https://api.etherscan.io/api',
        'goerli'    => 'https://api-goerli.etherscan.io/api',
        'sepolia'   => 'https://api-sepolia.etherscan.io/api',
        'polygon'   => 'https://api.polygonscan.com/api',
        'mumbai'    => 'https://api-testnet.polygonscan.com/api',
        'bsc'       => 'https://api.bscscan.com/api',
        'bsc_test'  => 'https://api-testnet.bscscan.com/api',
        'avalanche' => 'https://api.snowtrace.io/api',
        'fuji'      => 'https://api-testnet.snowtrace.io/api',
        'arbitrum'  => 'https://api.arbiscan.io/api',
        'optimism'  => 'https://api-optimistic.etherscan.io/api',
        'base'      => 'https://api.basescan.org/api',
        'fantom'    => 'https://api.ftmscan.com/api',
        'cronos'    => 'https://api.cronoscan.com/api',
    ];

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
// HTTP CLIENT
// ─────────────────────────────────────────────────────────────────────────────

class HttpClient
{
    private int   $timeout;
    private array $defaultHeaders;

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

    public function postRaw(string $url, string $body, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => array_merge($this->defaultHeaders, $headers, ['Content-Type: text/plain']),
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new NetworkException('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return (string)$response;
    }

    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        $allHeaders = array_merge($this->defaultHeaders, $headers);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new NetworkException('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        $decoded = json_decode((string)$response, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new NetworkException("Invalid JSON response. HTTP {$httpCode}. Raw: " . substr((string)$response, 0, 200));
        }

        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new NetworkException("Request failed [{$httpCode}]: {$msg}");
        }

        return $decoded;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PROVIDER
// ─────────────────────────────────────────────────────────────────────────────

class Provider
{
    public readonly string $network;
    public readonly string $rpcUrl;        // unmasked — used internally for HTTP calls
    public readonly string $providerName;
    private string         $maskedRpcUrl;  // api_key masked — safe for logs and info()
    private HttpClient     $http;
    private int            $rpcId = 1;

    public function __construct(array $config, HttpClient $http)
    {
        $this->network      = strtolower($config['network'] ?? 'ethereum');
        $this->providerName = strtolower($config['provider'] ?? 'public');
        $this->http         = $http;
        $this->rpcUrl       = $this->resolveRpcUrl($config);
        $this->maskedRpcUrl = $this->maskApiKey($this->rpcUrl, $config['api_key'] ?? '');
    }

    /**
     * [F-02] Masks all but the first 4 characters of the api_key in the URL.
     * Safe for logging, info() output, and debug messages.
     */
    private function maskApiKey(string $url, string $key): string
    {
        if ($key === '' || strlen($key) <= 4) {
            return $url;
        }
        $masked = substr($key, 0, 4) . str_repeat('*', strlen($key) - 4);
        return str_replace($key, $masked, $url);
    }

    private function resolveRpcUrl(array $config): string
    {
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

    public function restGet(string $path, array $params = [], array $headers = []): mixed
    {
        return $this->http->get($this->rpcUrl . $path, $params, $headers);
    }

    public function restPost(string $path, array $payload = [], array $headers = []): array
    {
        return $this->http->post($this->rpcUrl . $path, $payload, $headers);
    }

    public function restPostRaw(string $path, string $body, array $headers = []): string
    {
        return $this->http->postRaw($this->rpcUrl . $path, $body, $headers);
    }

    public function getNetwork(): string { return $this->network; }

    /**
     * [F-02] Returns the RPC URL with api_key masked — safe for logs and public info().
     * The raw $rpcUrl property is used internally for HTTP calls only.
     */
    public function getRpcUrl(): string  { return $this->maskedRpcUrl; }
}

// ─────────────────────────────────────────────────────────────────────────────
// MATH HELPERS
// ─────────────────────────────────────────────────────────────────────────────

class Math
{
    public static function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        if ($hex === '' || $hex === '0') return '0';
        if (extension_loaded('bcmath')) {
            return self::bcHexToDec($hex);
        }
        return (string)hexdec($hex);
    }

    public static function bcHexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        if ($hex === '' || $hex === '0') return '0';
        $result = '0';
        $len    = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $result = bcadd(bcmul($result, '16'), (string)hexdec($hex[$i]));
        }
        return $result;
    }

    public static function decToHex(string|int $dec): string
    {
        if (extension_loaded('bcmath') && is_string($dec) && strlen($dec) > 15) {
            $result = '';
            $n = $dec;
            while (bccomp($n, '0') > 0) {
                $remainder = (int)bcmod($n, '16');
                $result = dechex($remainder) . $result;
                $n = bcdiv($n, '16', 0);
            }
            return '0x' . ($result ?: '0');
        }
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
        $parts = explode('.', (string)$ether);
        $whole = $parts[0];
        $frac  = isset($parts[1]) ? str_pad(substr($parts[1], 0, 18), 18, '0') : str_repeat('0', 18);
        return bcadd(bcmul($whole, bcpow('10', '18', 0), 0), ltrim($frac, '0') ?: '0', 0);
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
        $parts = explode('.', (string)$value);
        $whole = $parts[0];
        $frac  = isset($parts[1]) ? str_pad(substr($parts[1], 0, $decimals), $decimals, '0') : str_repeat('0', $decimals);
        return bcadd(bcmul($whole, bcpow('10', (string)$decimals, 0), 0), ltrim($frac, '0') ?: '0', 0);
    }

    public static function keccak256(string $data): string
    {
        if (class_exists('\kornrunner\Keccak')) {
            return '0x' . \kornrunner\Keccak::hash($data, 256);
        }
        if (in_array('sha3-256', hash_algos(), true)) {
            return '0x' . hash('sha3-256', $data);
        }
        return '0x' . hash('sha256', $data . 'keccak_compat');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ADDRESS UTILITIES
// ─────────────────────────────────────────────────────────────────────────────

class Address
{
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
        if (Networks::isEVM($network))          return self::isValidEVM($address);
        if ($network === 'bitcoin')              return self::isValidBitcoin($address);
        if (str_starts_with($network, 'solana')) return self::isValidSolana($address);
        if (str_starts_with($network, 'tron'))  return self::isValidTron($address);
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ABI ENCODER
// ─────────────────────────────────────────────────────────────────────────────

class AbiEncoder
{
    public static function selector(string $signature): string
    {
        $hash = Math::keccak256($signature);
        return substr($hash, 0, 10);
    }

    public static function encodeUint256(string|int $value): string
    {
        if (extension_loaded('bcmath') && is_string($value)) {
            $n = $value;
            $hex = '';
            while (bccomp($n, '0') > 0) {
                $rem = (int)bcmod($n, '16');
                $hex = dechex($rem) . $hex;
                $n = bcdiv($n, '16', 0);
            }
            return str_pad($hex ?: '0', 64, '0', STR_PAD_LEFT);
        }
        return str_pad(dechex((int)$value), 64, '0', STR_PAD_LEFT);
    }

    public static function encodeAddress(string $address): string
    {
        $addr = strtolower(ltrim($address, '0x'));
        return str_pad($addr, 64, '0', STR_PAD_LEFT);
    }

    public static function encodeCall(string $signature, array $types, array $values): string
    {
        $selector = self::selector($signature);
        $encoded  = '';
        foreach ($types as $i => $type) {
            $val = $values[$i];
            $encoded .= match (true) {
                $type === 'address'                                => self::encodeAddress((string)$val),
                str_starts_with($type, 'uint') || $type === 'int' => self::encodeUint256((string)$val),
                $type === 'bool'                                   => str_pad((string)(int)(bool)$val, 64, '0', STR_PAD_LEFT),
                default                                            => str_pad(ltrim((string)$val, '0x'), 64, '0', STR_PAD_LEFT),
            };
        }
        return $selector . $encoded;
    }

    public static function decodeUint256(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        return Math::bcHexToDec($hex);
    }

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
        $wei    = Math::bcHexToDec(ltrim((string)$result, '0x'));
        return Math::weiToEther($wei);
    }

    private function bitcoinBalance(string $address): string
    {
        $data = $this->provider->restGet("/address/{$address}");
        $sat  = ($data['chain_stats']['funded_txo_sum'] ?? 0)
              - ($data['chain_stats']['spent_txo_sum'] ?? 0);
        return (string)Math::satoshiToBtc((int)$sat);
    }

    private function solanaBalance(string $address): string
    {
        $result   = $this->provider->jsonRpc('getBalance', [$address]);
        $lamports = is_array($result) ? ($result['value'] ?? 0) : ($result ?? 0);
        return (string)Math::lamportsToSol($lamports);
    }

    private function tronBalance(string $address): string
    {
        $data = $this->provider->restGet("/v1/accounts/{$address}");
        $sun  = $data['data'][0]['balance'] ?? 0;
        return (string)Math::sunToTrx($sun);
    }

    /**
     * Get ERC-20 / BEP-20 token balance (EVM only).
     * [F-01] Added address validation for both walletAddress and contractAddress.
     */
    public function getTokenBalance(string $walletAddress, string $contractAddress, int $decimals = 18): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('Token balances only supported on EVM networks');
        }
        if (!Address::validate($walletAddress, $this->network)) {
            throw new WalletException("Invalid wallet address for network [{$this->network}]: {$walletAddress}");
        }
        if (!Address::validate($contractAddress, $this->network)) {
            throw new WalletException("Invalid contract address for network [{$this->network}]: {$contractAddress}");
        }

        $data = AbiEncoder::encodeCall('balanceOf(address)', ['address'], [$walletAddress]);

        $result = $this->provider->jsonRpc('eth_call', [
            ['to' => $contractAddress, 'data' => $data],
            'latest',
        ]);

        $raw = Math::bcHexToDec(ltrim((string)$result, '0x'));
        return Math::formatUnits($raw, $decimals);
    }

    /**
     * Get transaction count / nonce (EVM).
     * [F-01] Added address validation.
     */
    public function getNonce(string $address): int
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('getNonce is only supported on EVM networks');
        }
        if (!Address::validate($address, $this->network)) {
            throw new WalletException("Invalid address for network [{$this->network}]: {$address}");
        }
        $result = $this->provider->jsonRpc('eth_getTransactionCount', [$address, 'latest']);
        return (int)Math::hexToDec((string)$result);
    }

    /**
     * Get token allowance (EVM).
     * [F-01] Added address validation for contractAddress, owner, and spender.
     */
    public function getAllowance(string $contractAddress, string $owner, string $spender, int $decimals = 18): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('getAllowance is only supported on EVM networks');
        }
        if (!Address::validate($contractAddress, $this->network)) {
            throw new WalletException("Invalid contract address for network [{$this->network}]: {$contractAddress}");
        }
        if (!Address::validate($owner, $this->network)) {
            throw new WalletException("Invalid owner address for network [{$this->network}]: {$owner}");
        }
        if (!Address::validate($spender, $this->network)) {
            throw new WalletException("Invalid spender address for network [{$this->network}]: {$spender}");
        }

        $data = AbiEncoder::encodeCall(
            'allowance(address,address)',
            ['address', 'address'],
            [$owner, $spender]
        );

        $result = $this->provider->jsonRpc('eth_call', [
            ['to' => $contractAddress, 'data' => $data],
            'latest',
        ]);

        $raw = Math::bcHexToDec(ltrim((string)$result, '0x'));
        return Math::formatUnits($raw, $decimals);
    }

    /**
     * Get ETH / EVM transaction history via Etherscan-compatible API.
     */
    public function getTransactionHistory(
        string $address,
        string $explorerApiKey,
        int    $page   = 1,
        int    $offset = 50,
        string $sort   = 'desc'
    ): array {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('Transaction history is only supported on EVM networks');
        }

        $apiUrl = Networks::EXPLORER_API[$this->network]
            ?? throw new WalletException("No explorer API configured for: {$this->network}");

        $http   = new HttpClient();
        $result = $http->get($apiUrl, [
            'module'  => 'account',
            'action'  => 'txlist',
            'address' => $address,
            'page'    => $page,
            'offset'  => $offset,
            'sort'    => $sort,
            'apikey'  => $explorerApiKey,
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Get ERC-20 token transfer history (EVM).
     */
    public function getTokenTransfers(
        string  $address,
        string  $explorerApiKey,
        ?string $contractAddress = null
    ): array {
        if (!Networks::isEVM($this->network)) {
            throw new WalletException('Token transfers are only supported on EVM networks');
        }

        $apiUrl = Networks::EXPLORER_API[$this->network]
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

    public function getLatestBlockNumber(): int|string
    {
        return match (true) {
            Networks::isEVM($this->network)              => (int)Math::hexToDec((string)$this->provider->jsonRpc('eth_blockNumber')),
            $this->network === 'bitcoin'                 => (int)($this->provider->restGet('/blocks/tip/height')),
            str_starts_with($this->network, 'solana')   => $this->provider->jsonRpc('getSlot') ?? 0,
            str_starts_with($this->network, 'tron')     => (int)($this->provider->restGet('/wallet/getnowblock')['block_header']['raw_data']['number'] ?? 0),
            default => throw new BlockException("Unsupported network: {$this->network}"),
        };
    }

    public function getBlock(int|string $blockId = 'latest', bool $fullTx = false): array
    {
        return match (true) {
            Networks::isEVM($this->network)              => $this->evmBlock($blockId, $fullTx),
            $this->network === 'bitcoin'                 => $this->btcBlock($blockId),
            str_starts_with($this->network, 'solana')   => $this->solanaBlock($blockId),
            str_starts_with($this->network, 'tron')     => $this->tronBlock($blockId),
            default => throw new BlockException("Unsupported network: {$this->network}"),
        };
    }

    private function evmBlock(int|string $blockId, bool $fullTx): array
    {
        $param = $blockId === 'latest' ? 'latest' : '0x' . dechex((int)$blockId);
        $block = $this->provider->jsonRpc('eth_getBlockByNumber', [$param, $fullTx]);
        if (!$block) throw new BlockException("Block not found: {$blockId}");

        return [
            'number'        => (int)Math::hexToDec($block['number'] ?? '0x0'),
            'hash'          => $block['hash'] ?? null,
            'parent_hash'   => $block['parentHash'] ?? null,
            'timestamp'     => (int)Math::hexToDec($block['timestamp'] ?? '0x0'),
            'datetime'      => date('Y-m-d H:i:s', (int)Math::hexToDec($block['timestamp'] ?? '0x0')),
            'miner'         => $block['miner'] ?? null,
            'gas_limit'     => (int)Math::hexToDec($block['gasLimit'] ?? '0x0'),
            'gas_used'      => (int)Math::hexToDec($block['gasUsed'] ?? '0x0'),
            'base_fee_gwei' => isset($block['baseFeePerGas'])
                ? number_format(Math::bcHexToDec(ltrim($block['baseFeePerGas'], '0x')) / 1e9, 9)
                : null,
            'tx_count'      => count($block['transactions'] ?? []),
            'transactions'  => $block['transactions'] ?? [],
        ];
    }

    private function btcBlock(int|string $blockId): array
    {
        $hash = $blockId === 'latest'
            ? trim((string)$this->provider->restGet('/blocks/tip/hash'))
            : (is_int($blockId) ? trim((string)$this->provider->restGet("/block-height/{$blockId}")) : $blockId);
        $data = $this->provider->restGet("/block/{$hash}");
        return [
            'hash'      => $data['id'] ?? $hash,
            'height'    => $data['height'] ?? null,
            'timestamp' => $data['timestamp'] ?? null,
            'datetime'  => isset($data['timestamp']) ? date('Y-m-d H:i:s', $data['timestamp']) : null,
            'tx_count'  => $data['tx_count'] ?? null,
            'size'      => $data['size'] ?? null,
            'weight'    => $data['weight'] ?? null,
        ];
    }

    private function solanaBlock(int|string $slot): array
    {
        $slotNum = $slot === 'latest' ? $this->provider->jsonRpc('getSlot') : (int)$slot;
        $block   = $this->provider->jsonRpc('getBlock', [$slotNum, ['encoding' => 'json', 'maxSupportedTransactionVersion' => 0]]);
        if (!$block) throw new BlockException("Block not found for slot: {$slotNum}");
        return [
            'slot'       => $slotNum,
            'hash'       => $block['blockhash'] ?? null,
            'timestamp'  => $block['blockTime'] ?? null,
            'datetime'   => isset($block['blockTime']) ? date('Y-m-d H:i:s', $block['blockTime']) : null,
            'tx_count'   => count($block['transactions'] ?? []),
            'parent_slot'=> $block['parentSlot'] ?? null,
        ];
    }

    private function tronBlock(int|string $blockId): array
    {
        $data = $blockId === 'latest'
            ? $this->provider->restGet('/wallet/getnowblock')
            : (array)$this->provider->restPost('/wallet/getblockbynum', ['num' => (int)$blockId]);
        return [
            'number'    => $data['block_header']['raw_data']['number'] ?? null,
            'hash'      => $data['blockID'] ?? null,
            'timestamp' => ($data['block_header']['raw_data']['timestamp'] ?? 0) / 1000,
            'tx_count'  => count($data['transactions'] ?? []),
        ];
    }

    public function getTransaction(string $hash): array
    {
        return match (true) {
            Networks::isEVM($this->network)              => $this->evmTransaction($hash),
            $this->network === 'bitcoin'                 => $this->btcTransaction($hash),
            str_starts_with($this->network, 'solana')   => $this->solanaTransaction($hash),
            str_starts_with($this->network, 'tron')     => $this->tronTransaction($hash),
            default => throw new BlockException("Unsupported network: {$this->network}"),
        };
    }

    private function evmTransaction(string $hash): array
    {
        $tx = $this->provider->jsonRpc('eth_getTransactionByHash', [$hash]);
        if (!$tx) throw new BlockException("Transaction not found: {$hash}");

        $receipt = null;
        try {
            $receipt = $this->provider->jsonRpc('eth_getTransactionReceipt', [$hash]);
        } catch (\Throwable) {}

        return [
            'hash'      => $tx['hash'] ?? $hash,
            'from'      => $tx['from'] ?? null,
            'to'        => $tx['to'] ?? null,
            'value_eth' => Math::weiToEther(Math::bcHexToDec(ltrim($tx['value'] ?? '0x0', '0x'))),
            'value_wei' => Math::bcHexToDec(ltrim($tx['value'] ?? '0x0', '0x')),
            'gas'       => (int)Math::hexToDec($tx['gas'] ?? '0x0'),
            'gas_price' => Math::bcHexToDec(ltrim($tx['gasPrice'] ?? '0x0', '0x')),
            'nonce'     => (int)Math::hexToDec($tx['nonce'] ?? '0x0'),
            'input'     => $tx['input'] ?? '0x',
            'block'     => isset($tx['blockNumber']) ? (int)Math::hexToDec($tx['blockNumber']) : null,
            'status'    => $receipt
                ? ((int)Math::hexToDec($receipt['status'] ?? '0x0') === 1 ? 'success' : 'failed')
                : 'pending',
            'gas_used'  => $receipt ? (int)Math::hexToDec($receipt['gasUsed'] ?? '0x0') : null,
            'logs'      => $receipt['logs'] ?? [],
        ];
    }

    private function btcTransaction(string $hash): array
    {
        $data = $this->provider->restGet("/tx/{$hash}");
        return [
            'txid'      => $data['txid'] ?? $data['tx_hash'] ?? '',
            'confirmed' => $data['status']['confirmed'] ?? false,
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
            ['encoding' => 'json', 'maxSupportedTransactionVersion' => 0],
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

    public function getGasInfo(): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new BlockException('getGasInfo is only supported on EVM networks');
        }
        $gasPriceHex = $this->provider->jsonRpc('eth_gasPrice');
        $gasPriceWei = Math::bcHexToDec(ltrim((string)$gasPriceHex, '0x'));
        $result = [
            'gas_price_wei'  => $gasPriceWei,
            'gas_price_gwei' => Math::formatUnits($gasPriceWei, 9),
        ];
        try {
            $block = $this->provider->jsonRpc('eth_getBlockByNumber', ['latest', false]);
            if (isset($block['baseFeePerGas'])) {
                $baseFeeWei = Math::bcHexToDec(ltrim($block['baseFeePerGas'], '0x'));
                $result['base_fee_wei']  = $baseFeeWei;
                $result['base_fee_gwei'] = Math::formatUnits($baseFeeWei, 9);
            }
        } catch (\Throwable) {}
        return $result;
    }

    public function estimateGas(array $tx): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new BlockException('estimateGas is only supported on EVM networks');
        }
        $result = $this->provider->jsonRpc('eth_estimateGas', [$tx]);
        return (string)(int)Math::hexToDec((string)$result);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTRACT MODULE
// ─────────────────────────────────────────────────────────────────────────────

class ContractModule
{
    public function __construct(
        private Provider $provider,
        private string   $network,
        private string   $contractAddress,
        private array    $abi = []
    ) {
        if (!Networks::isEVM($network)) {
            throw new ContractException("ContractModule only supports EVM networks. Got: {$network}");
        }
    }

    public function call(string $functionSignature, array $types = [], array $values = []): string
    {
        $data   = AbiEncoder::encodeCall($functionSignature, $types, $values);
        $result = $this->provider->jsonRpc('eth_call', [
            ['to' => $this->contractAddress, 'data' => $data],
            'latest',
        ]);
        return (string)($result ?? '0x');
    }

    public function callUint256(string $functionSignature, array $types = [], array $values = []): string
    {
        return AbiEncoder::decodeUint256($this->call($functionSignature, $types, $values));
    }

    public function callAddress(string $functionSignature, array $types = [], array $values = []): string
    {
        return AbiEncoder::decodeAddress($this->call($functionSignature, $types, $values));
    }

    public function buildTransaction(
        string $fromAddress,
        string $functionSignature,
        array  $types   = [],
        array  $values  = [],
        string $value   = '0x0',
        ?int   $gasLimit = null
    ): array {
        $data    = AbiEncoder::encodeCall($functionSignature, $types, $values);
        $nonce   = $this->provider->jsonRpc('eth_getTransactionCount', [$fromAddress, 'pending']);
        $chainId = $this->provider->jsonRpc('eth_chainId');
        $gas     = $gasLimit
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

    public function erc20Info(string $holderAddress): array
    {
        return [
            'name'         => $this->decodeString($this->call('name()')),
            'symbol'       => $this->decodeString($this->call('symbol()')),
            'decimals'     => (int)$this->callUint256('decimals()'),
            'total_supply' => $this->callUint256('totalSupply()'),
            'balance'      => $this->callUint256('balanceOf(address)', ['address'], [$holderAddress]),
        ];
    }

    public function erc721OwnerOf(int $tokenId): string
    {
        return $this->callAddress('ownerOf(uint256)', ['uint256'], [$tokenId]);
    }

    public function erc721TokenURI(int $tokenId): string
    {
        return $this->decodeString($this->call('tokenURI(uint256)', ['uint256'], [$tokenId]));
    }

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
        $lengthHex = substr($hex, 64, 64);
        $length    = (int)hexdec($lengthHex);
        if ($length === 0) return '';
        $data = substr($hex, 128, $length * 2);
        return pack('H*', $data);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TRANSFER MODULE
// ─────────────────────────────────────────────────────────────────────────────

class TransferModule
{
    public function __construct(
        private Provider $provider,
        private string   $network
    ) {}

    public function sendRaw(string $rawTxHex): string
    {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('sendRaw is only supported on EVM networks');
        }
        if (!str_starts_with($rawTxHex, '0x')) {
            $rawTxHex = '0x' . $rawTxHex;
        }
        return (string)$this->provider->jsonRpc('eth_sendRawTransaction', [$rawTxHex]);
    }

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
        $valueHex = '0x' . ltrim(Math::decToHex($wei), '0x');
        $gas = $customGas
            ? Math::decToHex($customGas)
            : $this->provider->jsonRpc('eth_estimateGas', [[
                'from'  => $from,
                'to'    => $to,
                'value' => $valueHex,
            ]]);
        $chainId = $this->provider->jsonRpc('eth_chainId');
        return [
            'from'     => $from,
            'to'       => $to,
            'nonce'    => $nonce,
            'gas'      => $gas,
            'gasPrice' => $gasPrice,
            'value'    => $valueHex,
            'data'     => '0x',
            'chainId'  => $chainId,
        ];
    }

    public function buildTokenTransfer(
        string $from,
        string $contractAddress,
        string $to,
        float  $amount,
        int    $decimals  = 18,
        ?int   $customGas = null
    ): array {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('buildTokenTransfer is only supported on EVM networks');
        }
        $value    = Math::parseUnits((string)$amount, $decimals);
        $data     = AbiEncoder::encodeCall('transfer(address,uint256)', ['address', 'uint256'], [$to, $value]);
        $nonce    = $this->provider->jsonRpc('eth_getTransactionCount', [$from, 'pending']);
        $gasPrice = $this->provider->jsonRpc('eth_gasPrice');
        $chainId  = $this->provider->jsonRpc('eth_chainId');
        $gas = $customGas
            ? Math::decToHex($customGas)
            : $this->provider->jsonRpc('eth_estimateGas', [[
                'from' => $from,
                'to'   => $contractAddress,
                'data' => $data,
            ]]);
        return [
            'from'     => $from,
            'to'       => $contractAddress,
            'nonce'    => $nonce,
            'gas'      => $gas,
            'gasPrice' => $gasPrice,
            'value'    => '0x0',
            'data'     => $data,
            'chainId'  => $chainId,
        ];
    }

    public function signAndSend(string $privateKey, array $unsignedTx): string
    {
        if (!class_exists('\Ethereum\RLP\RLP')) {
            throw new TransferException(
                'Signing requires: composer require kornrunner/ethereum-offline-raw-tx'
            );
        }
        $transaction = new \Ethereum\Transaction(
            $unsignedTx['nonce'],
            $unsignedTx['gasPrice'],
            $unsignedTx['gas'],
            $unsignedTx['to'],
            $unsignedTx['value'],
            $unsignedTx['data']
        );
        $chainId = (int)Math::hexToDec((string)($unsignedTx['chainId'] ?? '0x1'));
        $rawTx   = $transaction->getRaw($privateKey, $chainId);
        return $this->sendRaw($rawTx);
    }

    public function waitForConfirmation(string $txHash, int $timeoutSeconds = 120): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new TransferException('waitForConfirmation is only supported on EVM networks');
        }
        $start = time();
        while ((time() - $start) < $timeoutSeconds) {
            $receipt = $this->provider->jsonRpc('eth_getTransactionReceipt', [$txHash]);
            if ($receipt && isset($receipt['status'])) {
                return $receipt;
            }
            sleep(3);
        }
        throw new TransferException("Transaction not confirmed within {$timeoutSeconds}s: {$txHash}");
    }

    public function getBitcoinUTXOs(string $address): array
    {
        if ($this->network !== 'bitcoin') {
            throw new TransferException('getBitcoinUTXOs is only for Bitcoin');
        }
        $utxos = $this->provider->restGet("/address/{$address}/utxo");
        return array_map(static function (array $u) {
            return [
                'txid'          => $u['txid'],
                'vout'          => $u['vout'],
                'value_sat'     => $u['value'],
                'value_btc'     => Math::satoshiToBtc($u['value']),
                'confirmed'     => $u['status']['confirmed'] ?? false,
                'block_height'  => $u['status']['block_height'] ?? null,
            ];
        }, (array)$utxos);
    }

    public function broadcastBitcoin(string $rawTxHex): string
    {
        if ($this->network !== 'bitcoin') {
            throw new TransferException('broadcastBitcoin is only for Bitcoin');
        }
        return trim($this->provider->restPostRaw('/tx', $rawTxHex));
    }

    public function sendSolanaTransaction(string $signedTxBase64): string
    {
        if (!str_starts_with($this->network, 'solana')) {
            throw new TransferException('sendSolanaTransaction is only for Solana');
        }
        return (string)$this->provider->jsonRpc('sendTransaction', [
            $signedTxBase64,
            ['encoding' => 'base64'],
        ]);
    }

    public function broadcastTron(array $signedTx): array
    {
        if (!str_starts_with($this->network, 'tron')) {
            throw new TransferException('broadcastTron is only for Tron');
        }
        return $this->provider->restPost('/wallet/broadcasttransaction', $signedTx);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NETWORK STATS MODULE
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
        return (int)Math::hexToDec((string)$this->provider->jsonRpc('eth_chainId'));
    }

    public function getNodeInfo(): array
    {
        if (!Networks::isEVM($this->network)) {
            throw new NetworkException('getNodeInfo is only supported on EVM networks');
        }
        return [
            'version'    => $this->provider->jsonRpc('web3_clientVersion'),
            'chain_id'   => $this->getChainId(),
            'peer_count' => (int)Math::hexToDec((string)($this->provider->jsonRpc('net_peerCount') ?? '0x0')),
            'listening'  => $this->provider->jsonRpc('net_listening'),
            'syncing'    => $this->provider->jsonRpc('eth_syncing'),
            'network'    => $this->network,
            'rpc_url'    => $this->provider->getRpcUrl(), // masked — safe for logs
            'symbol'     => Networks::NATIVE_SYMBOL[$this->network] ?? '?',
        ];
    }

    public function getMempoolSize(): int
    {
        if (!Networks::isEVM($this->network)) {
            throw new NetworkException('getMempoolSize is only supported on EVM networks');
        }
        $status = $this->provider->jsonRpc('txpool_status');
        return isset($status['pending']) ? (int)Math::hexToDec((string)$status['pending']) : 0;
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
        return (array)$this->provider->restGet('/mempool');
    }

    public function getBitcoinFeeRecommendations(): array
    {
        if ($this->network !== 'bitcoin') {
            throw new NetworkException('getBitcoinFeeRecommendations is only for Bitcoin');
        }
        return (array)$this->provider->restGet('/v1/fees/recommended');
    }

    public function getTronBandwidth(string $address): array
    {
        if (!str_starts_with($this->network, 'tron')) {
            throw new NetworkException('getTronBandwidth is only for Tron');
        }
        return (array)$this->provider->restGet("/v1/accounts/{$address}/resources");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MAIN FACADE — Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

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
        $this->config = array_merge(['network' => 'ethereum', 'provider' => 'public'], $config);
        $http         = new HttpClient(
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

    public function contract(string $contractAddress, array $abi = []): ContractModule
    {
        return new ContractModule($this->provider, $this->provider->getNetwork(), $contractAddress, $abi);
    }

    public function switchNetwork(string $network): static
    {
        return new static(array_merge($this->config, ['network' => $network]));
    }

    public function balanceOf(string $address): string
    {
        return $this->wallet->getBalance($address);
    }

    public function latestBlock(): int|string
    {
        return $this->block->getLatestBlockNumber();
    }

    public function getTransaction(string $hash): array
    {
        return $this->block->getTransaction($hash);
    }

    public function rpc(string $method, array $params = []): mixed
    {
        return $this->provider->jsonRpc($method, $params);
    }

    public function info(): array
    {
        return [
            'library'  => 'Web3PHP',
            'version'  => '1.0.2',
            'network'  => $this->provider->getNetwork(),
            'provider' => $this->provider->providerName,
            'rpc_url'  => $this->provider->getRpcUrl(), // masked — safe for logs
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