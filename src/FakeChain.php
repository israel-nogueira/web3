<?php

/**
 * ███████╗ █████╗ ██╗  ██╗███████╗ ██████╗██╗  ██╗ █████╗ ██╗███╗   ██╗
 * ██╔════╝██╔══██╗██║ ██╔╝██╔════╝██╔════╝██║  ██║██╔══██╗██║████╗  ██║
 * █████╗  ███████║█████╔╝ █████╗  ██║     ███████║███████║██║██╔██╗ ██║
 * ██╔══╝  ██╔══██║██╔═██╗ ██╔══╝  ██║     ██╔══██║██╔══██║██║██║╚██╗██║
 * ██║     ██║  ██║██║  ██╗███████╗╚██████╗██║  ██║██║  ██║██║██║ ╚████║
 * ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝ ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝
 *
 * FakeChain — Local Blockchain Simulator for Web3PHP
 * ====================================================
 * Blockchain local completa para desenvolvimento e testes.
 * 100% offline. Zero APIs. Zero dependências externas.
 * Interface IDÊNTICA ao Web3PHP original.
 *
 * FEATURES:
 *  ✅ Cria carteiras com saldo inicial configurável
 *  ✅ Minera blocos reais com hash e merkle root
 *  ✅ Transferências nativas e de tokens ERC-20 fake
 *  ✅ Deploy e interação com contratos simulados
 *  ✅ Histórico completo de transações por carteira
 *  ✅ Persistência em arquivo JSON (opcional)
 *  ✅ Snapshot e rollback do estado
 *  ✅ Mesma interface do Web3PHP original
 *
 * USO BÁSICO:
 *   $chain = new FakeChain();
 *   $alice = $chain->createWallet('Alice', 100.0);
 *   $bob   = $chain->createWallet('Bob', 50.0);
 *   $chain->transfer($alice['address'], $bob['address'], 10.0, $alice['private_key']);
 *   echo $chain->wallet->getBalance($alice['address']); // 89.979...
 *
 * @version 1.0.0
 * @license MIT
 */

declare(strict_types=1);

namespace FakeChain;

// ─────────────────────────────────────────────────────────────────────────────
// EXCEPTIONS (mesmas do Web3PHP)
// ─────────────────────────────────────────────────────────────────────────────

class Web3Exception     extends \RuntimeException {}
class NetworkException  extends Web3Exception {}
class WalletException   extends Web3Exception {}
class TransferException extends Web3Exception {}
class BlockException    extends Web3Exception {}
class ContractException extends Web3Exception {}

// ─────────────────────────────────────────────────────────────────────────────
// CRYPTO HELPERS — hash e endereços sem extensões externas
// ─────────────────────────────────────────────────────────────────────────────

class Crypto
{
    /**
     * Simula keccak256 usando sha3-256 disponível no PHP nativo.
     * Para testes é perfeito — mesma estrutura, apenas hash diferente.
     */
    public static function hash(string $data): string
    {
        return '0x' . hash('sha3-256', $data);
    }

    /**
     * Gera um endereço EVM-like determinístico a partir de uma semente.
     */
    public static function generateAddress(string $seed): string
    {
        $hash = hash('sha3-256', $seed . microtime(true) . random_int(0, PHP_INT_MAX));
        return '0x' . substr($hash, 0, 40);
    }

    /**
     * Gera uma chave privada fake (hex 64 chars).
     */
    public static function generatePrivateKey(string $seed = ''): string
    {
        return hash('sha3-256', $seed . random_bytes(32));
    }

    /**
     * Deriva endereço a partir de uma chave privada (fake determinístico).
     */
    public static function privateKeyToAddress(string $privateKey): string
    {
        $hash = hash('sha3-256', $privateKey . '_address_derivation');
        return '0x' . substr($hash, 0, 40);
    }

    /**
     * Gera hash de bloco a partir de seus dados.
     */
    public static function blockHash(array $blockData): string
    {
        return '0x' . hash('sha3-256', json_encode($blockData));
    }

    /**
     * Gera transaction hash.
     */
    public static function txHash(array $tx): string
    {
        return '0x' . hash('sha3-256', json_encode($tx) . microtime(true) . random_int(0, PHP_INT_MAX));
    }

    /**
     * Merkle root simples das transações.
     */
    public static function merkleRoot(array $txHashes): string
    {
        if (empty($txHashes)) return '0x' . str_repeat('0', 64);
        if (count($txHashes) === 1) return $txHashes[0];

        $hashes = $txHashes;
        while (count($hashes) > 1) {
            $next = [];
            for ($i = 0; $i < count($hashes); $i += 2) {
                $a      = $hashes[$i];
                $b      = $hashes[$i + 1] ?? $hashes[$i];
                $next[] = '0x' . hash('sha3-256', $a . $b);
            }
            $hashes = $next;
        }
        return $hashes[0];
    }

    /**
     * Valida "assinatura" fake — verifica se a chave corresponde ao endereço.
     */
    public static function verifySignature(string $address, string $privateKey): bool
    {
        return self::privateKeyToAddress($privateKey) === strtolower($address);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LEDGER — estado global da blockchain (fonte da verdade)
// ─────────────────────────────────────────────────────────────────────────────

class Ledger
{
    private array  $balances    = [];   // address => float
    private array  $tokens      = [];   // contractAddr => [symbol, decimals, balances[]]
    private array  $contracts   = [];   // contractAddr => ContractState
    private array  $txPool      = [];   // transações pendentes
    private array  $blocks      = [];   // todos os blocos minados
    private array  $txIndex     = [];   // txHash => bloco index + posição
    private array  $walletTxs   = [];   // address => [txHash, ...]
    private array  $walletNames = [];   // address => apelido
    private int    $chainId;
    private string $network;
    private float  $gasPrice    = 0.000000021; // 21 Gwei em ETH
    private float  $blockTime   = 12.0;        // segundos por bloco
    private string $storagePath;
    private bool   $persistent;

    public function __construct(array $config = [])
    {
        $this->chainId     = $config['chain_id']   ?? 1337;
        $this->network     = $config['network']    ?? 'fakechain';
        $this->gasPrice    = $config['gas_price']  ?? 0.000000021;
        $this->blockTime   = $config['block_time'] ?? 12.0;
        $this->persistent  = !empty($config['storage_path']);
        $this->storagePath = $config['storage_path'] ?? '';

        if ($this->persistent && file_exists($this->storagePath)) {
            $this->load();
        } else {
            $this->genesis($config);
        }
    }

    // ── Genesis ───────────────────────────────────────────────────────────────

    private function genesis(array $config): void
    {
        $ts = time();
        $this->blocks[] = [
            'number'       => 0,
            'hash'         => '0x' . str_repeat('0', 64),
            'parent_hash'  => '0x' . str_repeat('0', 64),
            'timestamp'    => $ts,
            'datetime'     => date('Y-m-d H:i:s', $ts),
            'miner'        => '0x' . str_repeat('0', 40),
            'gas_limit'    => 30000000,
            'gas_used'     => 0,
            'base_fee_gwei'=> '21.000000000',
            'tx_count'     => 0,
            'transactions' => [],
            'merkle_root'  => Crypto::merkleRoot([]),
            'size_bytes'   => 512,
            'difficulty'   => 1,
            'nonce'        => '0x0000000000000000',
            'extra_data'   => '0x' . bin2hex('FakeChain Genesis'),
        ];

        // Pré-mintar saldo para wallets configuradas no genesis
        foreach ($config['genesis_wallets'] ?? [] as $address => $balance) {
            $this->balances[strtolower($address)] = (float)$balance;
        }
    }

    // ── Persistência ──────────────────────────────────────────────────────────

    public function save(): void
    {
        if (!$this->persistent) return;
        file_put_contents($this->storagePath, json_encode([
            'balances'    => $this->balances,
            'tokens'      => $this->tokens,
            'contracts'   => $this->contracts,
            'txPool'      => $this->txPool,
            'blocks'      => $this->blocks,
            'txIndex'     => $this->txIndex,
            'walletTxs'   => $this->walletTxs,
            'walletNames' => $this->walletNames,
            'chainId'     => $this->chainId,
            'network'     => $this->network,
        ], JSON_PRETTY_PRINT));
    }

    private function load(): void
    {
        $data = json_decode(file_get_contents($this->storagePath), true);
        $this->balances    = $data['balances']    ?? [];
        $this->tokens      = $data['tokens']      ?? [];
        $this->contracts   = $data['contracts']   ?? [];
        $this->txPool      = $data['txPool']      ?? [];
        $this->blocks      = $data['blocks']      ?? [];
        $this->txIndex     = $data['txIndex']     ?? [];
        $this->walletTxs   = $data['walletTxs']   ?? [];
        $this->walletNames = $data['walletNames'] ?? [];
        $this->chainId     = $data['chainId']     ?? 1337;
        $this->network     = $data['network']     ?? 'fakechain';
    }

    // ── Snapshot / Rollback ──────────────────────────────────────────────────

    private array $snapshots = [];

    public function snapshot(string $label = ''): int
    {
        $id = count($this->snapshots);
        $this->snapshots[$id] = [
            'label'      => $label ?: "snapshot_{$id}",
            'balances'   => $this->balances,
            'tokens'     => $this->tokens,
            'contracts'  => $this->contracts,
            'blocks'     => $this->blocks,
            'txIndex'    => $this->txIndex,
            'walletTxs'  => $this->walletTxs,
            'txPool'     => $this->txPool,
        ];
        return $id;
    }

    public function rollback(int $snapshotId): void
    {
        if (!isset($this->snapshots[$snapshotId])) {
            throw new Web3Exception("Snapshot [{$snapshotId}] não encontrado.");
        }
        $s = $this->snapshots[$snapshotId];
        $this->balances  = $s['balances'];
        $this->tokens    = $s['tokens'];
        $this->contracts = $s['contracts'];
        $this->blocks    = $s['blocks'];
        $this->txIndex   = $s['txIndex'];
        $this->walletTxs = $s['walletTxs'];
        $this->txPool    = $s['txPool'];
    }

    public function listSnapshots(): array
    {
        return array_map(fn($s, $i) => ['id' => $i, 'label' => $s['label']], $this->snapshots, array_keys($this->snapshots));
    }

    // ── Wallets ───────────────────────────────────────────────────────────────

    public function setBalance(string $address, float $balance): void
    {
        $this->balances[strtolower($address)] = $balance;
    }

    public function getBalance(string $address): float
    {
        return $this->balances[strtolower($address)] ?? 0.0;
    }

    public function setWalletName(string $address, string $name): void
    {
        $this->walletNames[strtolower($address)] = $name;
    }

    public function getWalletName(string $address): string
    {
        return $this->walletNames[strtolower($address)] ?? '';
    }

    // ── Tokens ────────────────────────────────────────────────────────────────

    public function deployToken(string $contractAddr, string $name, string $symbol, int $decimals, float $totalSupply, string $owner): void
    {
        $addr = strtolower($contractAddr);
        $this->tokens[$addr] = [
            'name'         => $name,
            'symbol'       => $symbol,
            'decimals'     => $decimals,
            'total_supply' => $totalSupply,
            'owner'        => strtolower($owner),
            'balances'     => [strtolower($owner) => $totalSupply],
            'allowances'   => [],
        ];
    }

    public function getTokenBalance(string $contractAddr, string $holder): float
    {
        $addr   = strtolower($contractAddr);
        $holder = strtolower($holder);
        return $this->tokens[$addr]['balances'][$holder] ?? 0.0;
    }

    public function getTokenInfo(string $contractAddr): array
    {
        $addr = strtolower($contractAddr);
        if (!isset($this->tokens[$addr])) {
            throw new ContractException("Token não encontrado: {$contractAddr}");
        }
        return $this->tokens[$addr];
    }

    public function tokenTransfer(string $contractAddr, string $from, string $to, float $amount): void
    {
        $addr = strtolower($contractAddr);
        $from = strtolower($from);
        $to   = strtolower($to);

        if (!isset($this->tokens[$addr])) {
            throw new ContractException("Token não existe: {$contractAddr}");
        }
        $bal = $this->tokens[$addr]['balances'][$from] ?? 0.0;
        if ($bal < $amount) {
            throw new TransferException("Saldo insuficiente de token. Tem: {$bal}, precisa: {$amount}");
        }
        $this->tokens[$addr]['balances'][$from] = $bal - $amount;
        $this->tokens[$addr]['balances'][$to]   = ($this->tokens[$addr]['balances'][$to] ?? 0.0) + $amount;
    }

    public function setAllowance(string $contractAddr, string $owner, string $spender, float $amount): void
    {
        $addr = strtolower($contractAddr);
        $this->tokens[$addr]['allowances'][strtolower($owner)][strtolower($spender)] = $amount;
    }

    public function getAllowance(string $contractAddr, string $owner, string $spender): float
    {
        $addr = strtolower($contractAddr);
        return $this->tokens[$addr]['allowances'][strtolower($owner)][strtolower($spender)] ?? 0.0;
    }

    // ── Contratos Genéricos ────────────────────────────────────────────────────

    public function deployContract(string $addr, array $state, array $abi = []): void
    {
        $this->contracts[strtolower($addr)] = [
            'state' => $state,
            'abi'   => $abi,
            'code'  => '0x' . bin2hex(random_bytes(32)),
        ];
    }

    public function getContractState(string $addr): array
    {
        $a = strtolower($addr);
        if (!isset($this->contracts[$a])) {
            throw new ContractException("Contrato não deployado: {$addr}");
        }
        return $this->contracts[$a]['state'];
    }

    public function setContractState(string $addr, array $state): void
    {
        $this->contracts[strtolower($addr)]['state'] = $state;
    }

    public function contractExists(string $addr): bool
    {
        return isset($this->contracts[strtolower($addr)])
            || isset($this->tokens[strtolower($addr)]);
    }

    // ── Transações e Blocos ───────────────────────────────────────────────────

    public function addToPending(array $tx): void
    {
        $this->txPool[$tx['hash']] = $tx;
    }

    public function mine(string $minerAddress = '0x' . '0' . str_repeat('1', 39)): array
    {
        $pending    = array_values($this->txPool);
        $parent     = end($this->blocks);
        $blockNum   = count($this->blocks);
        $ts         = (int)($parent['timestamp'] + $this->blockTime);
        $txHashes   = array_column($pending, 'hash');
        $gasUsed    = array_sum(array_column($pending, 'gas_used'));

        // Aplicar transações pendentes ao estado
        foreach ($pending as $tx) {
            $this->applyTx($tx, $blockNum);
        }

        $blockData = [
            'number'        => $blockNum,
            'parent_hash'   => $parent['hash'],
            'timestamp'     => $ts,
            'miner'         => $minerAddress,
            'gas_limit'     => 30000000,
            'gas_used'      => $gasUsed,
            'tx_count'      => count($pending),
            'transactions'  => $txHashes,
            'merkle_root'   => Crypto::merkleRoot($txHashes),
        ];

        $blockData['hash']         = Crypto::blockHash($blockData);
        $blockData['datetime']     = date('Y-m-d H:i:s', $ts);
        $blockData['base_fee_gwei']= number_format($this->gasPrice * 1e9, 9, '.', '');
        $blockData['size_bytes']   = strlen(json_encode($blockData)) + count($pending) * 200;
        $blockData['difficulty']   = 1;
        $blockData['nonce']        = '0x' . str_pad(dechex($blockNum), 16, '0', STR_PAD_LEFT);
        $blockData['extra_data']   = '0x' . bin2hex('FakeChain');

        // Indexar transações
        foreach ($pending as $pos => $tx) {
            $this->txIndex[$tx['hash']] = ['block' => $blockNum, 'pos' => $pos];
        }

        $this->blocks[] = $blockData;
        $this->txPool   = [];

        $this->save();
        return $blockData;
    }

    private function applyTx(array $tx, int $blockNum): void
    {
        $from   = strtolower($tx['from']);
        $to     = strtolower($tx['to'] ?? '');
        $value  = (float)($tx['value'] ?? 0);
        $fee    = (float)($tx['gas_fee'] ?? 0);

        // Deduzir fee do remetente
        $this->balances[$from] = ($this->balances[$from] ?? 0) - $fee;

        if ($tx['type'] === 'transfer') {
            $this->balances[$from] = ($this->balances[$from] ?? 0) - $value;
            $this->balances[$to]   = ($this->balances[$to]   ?? 0) + $value;
        } elseif ($tx['type'] === 'token_transfer') {
            $this->tokenTransfer($tx['contract'], $from, $to, $value);
        } elseif ($tx['type'] === 'contract_deploy') {
            $this->deployContract($to ?: $tx['contract_address'], $tx['initial_state'] ?? [], $tx['abi'] ?? []);
        } elseif ($tx['type'] === 'contract_call') {
            // Executar handler do contrato (se registrado)
            $addr = strtolower($tx['to']);
            if (isset($this->contracts[$addr]['handler'])) {
                ($this->contracts[$addr]['handler'])($tx, $this);
            }
        }
    }

    public function recordWalletTx(string $address, string $txHash): void
    {
        $addr = strtolower($address);
        $this->walletTxs[$addr][] = $txHash;
    }

    public function getWalletTxs(string $address): array
    {
        return $this->walletTxs[strtolower($address)] ?? [];
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getLatestBlockNumber(): int
    {
        return count($this->blocks) - 1;
    }

    public function getBlock(int|string $ref): array
    {
        if ($ref === 'latest') return end($this->blocks);
        if (is_int($ref) || ctype_digit((string)$ref)) {
            return $this->blocks[(int)$ref]
                ?? throw new BlockException("Bloco não encontrado: {$ref}");
        }
        // buscar por hash
        foreach ($this->blocks as $b) {
            if ($b['hash'] === $ref) return $b;
        }
        throw new BlockException("Bloco não encontrado: {$ref}");
    }

    public function getTx(string $hash): ?array
    {
        // No pool pendente
        if (isset($this->txPool[$hash])) {
            return array_merge($this->txPool[$hash], ['status' => 'pending', 'block' => null]);
        }
        // No índice
        $idx = $this->txIndex[$hash] ?? null;
        if (!$idx) return null;

        $block = $this->blocks[$idx['block']] ?? null;
        if (!$block) return null;

        // Reconstruir TX do hash (dados ficam no txPool snapshot) — guardamos dados completos
        return $this->txIndex[$hash . '_data'] ?? array_merge(
            ['hash' => $hash, 'block' => $idx['block'], 'status' => 'success'],
        );
    }

    public function storeTxData(string $hash, array $data): void
    {
        $this->txIndex[$hash . '_data'] = $data;
    }

    public function getAllTxs(): array
    {
        $all = [];
        foreach ($this->txIndex as $key => $val) {
            if (str_ends_with($key, '_data')) {
                $all[] = $val;
            }
        }
        return $all;
    }

    public function getPendingTxs(): array
    {
        return array_values($this->txPool);
    }

    public function getChainId(): int    { return $this->chainId; }
    public function getNetwork(): string { return $this->network; }
    public function getGasPrice(): float { return $this->gasPrice; }
    public function getAllBlocks(): array { return $this->blocks; }

    public function getNonce(string $address): int
    {
        return count($this->walletTxs[strtolower($address)] ?? []);
    }

    public function getAllTokens(): array { return $this->tokens; }
    public function getAllContracts(): array { return $this->contracts; }

    /**
     * Reset completo — volta ao estado genesis.
     */
    public function reset(array $config = []): void
    {
        $this->balances  = [];
        $this->tokens    = [];
        $this->contracts = [];
        $this->txPool    = [];
        $this->blocks    = [];
        $this->txIndex   = [];
        $this->walletTxs = [];
        $this->snapshots = [];
        $this->genesis($config);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MÓDULO: WALLET — interface idêntica ao Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

class WalletModule
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Saldo nativo da carteira (ETH/MATIC/etc fake).
     */
    public function getBalance(string $address): string
    {
        return (string)$this->ledger->getBalance($address);
    }

    /**
     * Saldo de token ERC-20 fake.
     */
    public function getTokenBalance(string $walletAddress, string $contractAddress, int $decimals = 18): string
    {
        return (string)$this->ledger->getTokenBalance($contractAddress, $walletAddress);
    }

    /**
     * Nonce atual (número de transações enviadas).
     */
    public function getNonce(string $address): int
    {
        return $this->ledger->getNonce($address);
    }

    /**
     * Allowance de token.
     */
    public function getAllowance(string $contractAddress, string $owner, string $spender, int $decimals = 18): string
    {
        return (string)$this->ledger->getAllowance($contractAddress, $owner, $spender);
    }

    /**
     * Histórico de transações.
     */
    public function getTransactionHistory(string $address, string $explorerApiKey = '', int $page = 1, int $offset = 25): array
    {
        $hashes = $this->ledger->getWalletTxs($address);
        $all    = [];

        foreach ($hashes as $hash) {
            $tx = $this->ledger->getTx($hash);
            if ($tx) $all[] = $tx;
        }

        // Paginar
        $offset = max(1, $offset);
        $start  = ($page - 1) * $offset;
        return array_slice(array_reverse($all), $start, $offset);
    }

    /**
     * Transfers de token (filtra do histórico).
     */
    public function getTokenTransfers(string $address, string $explorerApiKey = '', ?string $contractAddress = null): array
    {
        $history = $this->getTransactionHistory($address);
        return array_filter($history, function ($tx) use ($contractAddress) {
            $isToken = ($tx['type'] ?? '') === 'token_transfer';
            if ($contractAddress) {
                return $isToken && strtolower($tx['contract'] ?? '') === strtolower($contractAddress);
            }
            return $isToken;
        });
    }

    /**
     * Lista todos os tokens que um endereço possui.
     */
    public function getTokenPortfolio(string $address): array
    {
        $portfolio = [];
        foreach ($this->ledger->getAllTokens() as $contractAddr => $token) {
            $balance = $token['balances'][strtolower($address)] ?? 0.0;
            if ($balance > 0) {
                $portfolio[] = [
                    'contract' => $contractAddr,
                    'symbol'   => $token['symbol'],
                    'name'     => $token['name'],
                    'balance'  => $balance,
                    'decimals' => $token['decimals'],
                ];
            }
        }
        return $portfolio;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MÓDULO: BLOCK — interface idêntica ao Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

class BlockModule
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Número do último bloco minerado.
     */
    public function getLatestBlockNumber(): int
    {
        return $this->ledger->getLatestBlockNumber();
    }

    /**
     * Detalhes de um bloco por número, hash ou 'latest'.
     */
    public function getBlock(int|string $blockNumberOrHash = 'latest', bool $fullTransactions = false): array
    {
        $block = $this->ledger->getBlock($blockNumberOrHash);

        if ($fullTransactions && !empty($block['transactions'])) {
            $block['transactions'] = array_filter(array_map(
                fn($h) => $this->ledger->getTx($h),
                $block['transactions']
            ));
        }

        return $block;
    }

    /**
     * Detalhes de uma transação por hash.
     */
    public function getTransaction(string $txHash): array
    {
        $tx = $this->ledger->getTx($txHash);
        if (!$tx) throw new BlockException("Transação não encontrada: {$txHash}");
        return $tx;
    }

    /**
     * Gas info simulado.
     */
    public function getGasInfo(): array
    {
        $gwei = $this->ledger->getGasPrice() * 1e9;
        return [
            'gas_price_wei'  => (string)(int)($this->ledger->getGasPrice() * 1e18),
            'gas_price_gwei' => number_format($gwei, 9, '.', ''),
            'base_fee_gwei'  => number_format($gwei * 0.8, 9, '.', ''),
            'priority_fee'   => number_format($gwei * 0.2, 9, '.', ''),
        ];
    }

    /**
     * Estima gas (fixo para simplificar).
     */
    public function estimateGas(array $tx): string
    {
        $base = !empty($tx['data']) && $tx['data'] !== '0x' ? 60000 : 21000;
        return (string)$base;
    }

    /**
     * Lista todos os blocos.
     */
    public function getAllBlocks(): array
    {
        return $this->ledger->getAllBlocks();
    }

    /**
     * Busca transações em um range de blocos.
     */
    public function getBlockRange(int $from, int $to): array
    {
        $blocks = [];
        $latest = $this->getLatestBlockNumber();
        $to     = min($to, $latest);

        for ($i = $from; $i <= $to; $i++) {
            try {
                $blocks[] = $this->getBlock($i);
            } catch (BlockException) {
                break;
            }
        }
        return $blocks;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MÓDULO: TRANSFER — interface idêntica ao Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

class TransferModule
{
    public function __construct(private Ledger $ledger, private FakeChain $chain) {}

    /**
     * Monta TX unsigned nativa (igual ao Web3PHP).
     */
    public function buildNativeTransfer(string $from, string $to, float $amount, ?int $customGas = null): array
    {
        $gas     = $customGas ?? 21000;
        $gasFee  = $gas * $this->ledger->getGasPrice();
        $nonce   = $this->ledger->getNonce($from);
        $chainId = $this->ledger->getChainId();

        return [
            'from'      => $from,
            'to'        => $to,
            'nonce'     => '0x' . dechex($nonce),
            'gas'       => '0x' . dechex($gas),
            'gasPrice'  => '0x' . dechex((int)($this->ledger->getGasPrice() * 1e18)),
            'value'     => '0x' . dechex((int)($amount * 1e18)),
            'data'      => '0x',
            'chainId'   => '0x' . dechex($chainId),
            '__meta'    => ['amount' => $amount, 'gas_fee' => $gasFee, 'type' => 'transfer'],
        ];
    }

    /**
     * Monta TX unsigned de token (igual ao Web3PHP).
     */
    public function buildTokenTransfer(
        string $from,
        string $contractAddress,
        string $to,
        float  $amount,
        int    $decimals = 18,
        ?int   $customGas = null
    ): array {
        $gas    = $customGas ?? 60000;
        $gasFee = $gas * $this->ledger->getGasPrice();
        $nonce  = $this->ledger->getNonce($from);

        $selector = '0xa9059cbb'; // transfer(address,uint256)

        return [
            'from'            => $from,
            'to'              => $contractAddress,
            'nonce'           => '0x' . dechex($nonce),
            'gas'             => '0x' . dechex($gas),
            'gasPrice'        => '0x' . dechex((int)($this->ledger->getGasPrice() * 1e18)),
            'value'           => '0x0',
            'data'            => $selector . str_pad(ltrim($to, '0x'), 64, '0', STR_PAD_LEFT)
                                          . str_pad(dechex((int)($amount * (10 ** $decimals))), 64, '0', STR_PAD_LEFT),
            'chainId'         => '0x' . dechex($this->ledger->getChainId()),
            '__meta'          => [
                'amount'    => $amount,
                'gas_fee'   => $gasFee,
                'type'      => 'token_transfer',
                'contract'  => $contractAddress,
                'recipient' => $to,
                'decimals'  => $decimals,
            ],
        ];
    }

    /**
     * "Assina" e envia uma TX construída (fake — valida a chave privada).
     * Equivalente ao sendRaw() no Web3PHP.
     */
    public function sendRaw(string $rawTxOrPrivateKey, array $unsignedTx = []): string
    {
        // Modo 1: sendRaw('privateKey', $unsignedTx)
        if (!empty($unsignedTx)) {
            return $this->signAndSend($rawTxOrPrivateKey, $unsignedTx);
        }
        // Modo 2: sendRaw('0xRAW_HEX') — simula broadcast
        $txHash = '0x' . hash('sha3-256', $rawTxOrPrivateKey . microtime(true));
        return $txHash;
    }

    /**
     * Assina com chave privada e envia a transação.
     */
    public function signAndSend(string $privateKey, array $unsignedTx): string
    {
        $from = $unsignedTx['from'] ?? '';
        if (!Crypto::verifySignature($from, $privateKey)) {
            throw new TransferException("Chave privada não corresponde ao endereço: {$from}");
        }

        $meta  = $unsignedTx['__meta'] ?? [];
        $type  = $meta['type'] ?? 'transfer';
        $ts    = time();
        $nonce = $this->ledger->getNonce($from);

        $txData = [
            'hash'      => '',
            'from'      => strtolower($from),
            'to'        => strtolower($unsignedTx['to'] ?? ''),
            'value'     => $meta['amount'] ?? 0,
            'value_eth' => (string)($meta['amount'] ?? 0),
            'value_wei' => (string)((int)(($meta['amount'] ?? 0) * 1e18)),
            'gas'       => hexdec(ltrim($unsignedTx['gas'] ?? '0x5208', '0x')),
            'gas_price' => hexdec(ltrim($unsignedTx['gasPrice'] ?? '0x0', '0x')),
            'gas_used'  => hexdec(ltrim($unsignedTx['gas'] ?? '0x5208', '0x')),
            'gas_fee'   => $meta['gas_fee'] ?? 0,
            'nonce'     => $nonce,
            'input'     => $unsignedTx['data'] ?? '0x',
            'timestamp' => $ts,
            'datetime'  => date('Y-m-d H:i:s', $ts),
            'type'      => $type,
            'status'    => 'pending',
            'block'     => null,
            'logs'      => [],
        ];

        if ($type === 'token_transfer') {
            $txData['contract']  = strtolower($meta['contract'] ?? '');
            $txData['to']        = strtolower($meta['recipient'] ?? $txData['to']);
            $txData['token_amount'] = $meta['amount'] ?? 0;
        }

        $txData['hash'] = Crypto::txHash($txData);
        $hash = $txData['hash'];

        // Validação de saldo
        $balance = $this->ledger->getBalance($from);
        $totalCost = ($meta['amount'] ?? 0) + ($meta['gas_fee'] ?? 0);

        if ($type === 'transfer' && $balance < $totalCost) {
            throw new TransferException(
                "Saldo insuficiente. Tem: {$balance}, precisa: {$totalCost} (valor + gas)"
            );
        }
        if ($type === 'token_transfer') {
            $tokenBal = $this->ledger->getTokenBalance($meta['contract'] ?? '', $from);
            if ($tokenBal < ($meta['amount'] ?? 0)) {
                throw new TransferException("Saldo de token insuficiente. Tem: {$tokenBal}");
            }
            if ($balance < ($meta['gas_fee'] ?? 0)) {
                throw new TransferException("Saldo insuficiente para gas. Tem: {$balance}");
            }
        }

        // Adicionar ao pool e registrar
        $this->ledger->addToPending($txData);
        $this->ledger->storeTxData($hash, $txData);
        $this->ledger->recordWalletTx($from, $hash);
        $this->ledger->recordWalletTx($txData['to'], $hash);

        // Auto-minar se configurado
        if ($this->chain->autoMine) {
            $block = $this->ledger->mine($this->chain->minerAddress);
            $txData['status'] = 'success';
            $txData['block']  = $block['number'];
            $this->ledger->storeTxData($hash, $txData);
        }

        return $hash;
    }

    /**
     * Aguarda confirmação (no fake, a TX está confirmada imediatamente se autoMine = true).
     */
    public function waitForConfirmation(string $txHash, int $timeoutSeconds = 120, int $intervalSeconds = 1): array
    {
        $start = time();
        while (time() - $start < $timeoutSeconds) {
            $tx = $this->ledger->getTx($txHash);
            if ($tx && ($tx['status'] ?? '') === 'success') {
                return [
                    'hash'     => $txHash,
                    'status'   => 'success',
                    'block'    => $tx['block'],
                    'gas_used' => $tx['gas_used'] ?? 21000,
                    'logs'     => $tx['logs'] ?? [],
                    'receipt'  => $tx,
                ];
            }
            if (!$this->chain->autoMine) {
                $this->chain->mineBlock();
            }
            sleep($intervalSeconds);
        }
        throw new TransferException("TX não confirmada após {$timeoutSeconds}s: {$txHash}");
    }

    /**
     * Simula UTXOs Bitcoin (para compatibilidade de interface).
     */
    public function getBitcoinUTXOs(string $address): array
    {
        $balance = $this->ledger->getBalance($address);
        if ($balance <= 0) return [];
        return [[
            'txid'      => '0x' . str_repeat('a', 64),
            'vout'      => 0,
            'value_sat' => (int)($balance * 1e8),
            'value_btc' => $balance,
            'confirmed' => true,
        ]];
    }

    public function broadcastBitcoin(string $rawTxHex): string
    {
        return '0x' . hash('sha3-256', $rawTxHex);
    }

    public function sendSolanaTransaction(string $signedTxBase64): string
    {
        return base64_encode(random_bytes(32));
    }

    public function broadcastTron(array $signedTx): array
    {
        return ['result' => true, 'txid' => hash('sha3-256', json_encode($signedTx))];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MÓDULO: NETWORK STATS — interface idêntica ao Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

class NetworkStatsModule
{
    public function __construct(private Ledger $ledger, private FakeChain $chain) {}

    public function getChainId(): int
    {
        return $this->ledger->getChainId();
    }

    public function getNodeInfo(): array
    {
        return [
            'version'    => 'FakeChain/v1.0.0/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'chain_id'   => $this->ledger->getChainId(),
            'peer_count' => 0,
            'listening'  => true,
            'syncing'    => false,
            'network'    => $this->ledger->getNetwork(),
            'rpc_url'    => 'local://fakechain',
            'symbol'     => $this->chain->config['symbol'] ?? 'ETH',
            'mode'       => 'local_simulation',
        ];
    }

    public function getMempoolSize(): int
    {
        return count($this->ledger->getPendingTxs());
    }

    public function getBitcoinFeeRecommendations(): array
    {
        return ['fastestFee' => 25, 'halfHourFee' => 15, 'hourFee' => 10, 'economyFee' => 5, 'minimumFee' => 1];
    }

    public function getBitcoinMempoolStats(): array
    {
        return ['count' => count($this->ledger->getPendingTxs()), 'vsize' => 0, 'total_fee' => 0];
    }

    public function getSolanaEpoch(): array
    {
        $block = $this->ledger->getLatestBlockNumber();
        return ['epoch' => (int)($block / 432000), 'slotIndex' => $block % 432000, 'slotsInEpoch' => 432000, 'absoluteSlot' => $block];
    }

    public function getTronBandwidth(string $address): array
    {
        return ['freeNetUsed' => 0, 'freeNetLimit' => 1500, 'NetUsed' => 0, 'NetLimit' => 0, 'EnergyUsed' => 0, 'EnergyLimit' => 0];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MÓDULO: CONTRACT — interface idêntica ao Web3PHP
// ─────────────────────────────────────────────────────────────────────────────

class ContractModule
{
    public function __construct(
        private Ledger    $ledger,
        private FakeChain $chain,
        private string    $contractAddress,
        private array     $abi = []
    ) {}

    /**
     * Chama uma função view do contrato.
     * Para contratos token, resolve automaticamente name/symbol/decimals/totalSupply/balanceOf.
     */
    public function call(string $functionSignature, array $types = [], array $values = []): string
    {
        $func = strtolower(explode('(', $functionSignature)[0]);
        $addr = $this->contractAddress;

        // ERC-20 automático
        try {
            $token = $this->ledger->getTokenInfo($addr);
            return match ($func) {
                'name'        => $token['name'],
                'symbol'      => $token['symbol'],
                'decimals'    => (string)$token['decimals'],
                'totalsupply' => (string)$token['total_supply'],
                'balanceof'   => (string)($token['balances'][strtolower($values[0] ?? '')] ?? 0),
                'allowance'   => (string)($token['allowances'][strtolower($values[0] ?? '')][strtolower($values[1] ?? '')] ?? 0),
                default       => $this->callContractState($func, $values),
            };
        } catch (ContractException) {
            // Não é token, tenta contrato genérico
            return $this->callContractState($func, $values);
        }
    }

    private function callContractState(string $func, array $values): string
    {
        try {
            $state = $this->ledger->getContractState($this->contractAddress);
            return (string)($state[$func] ?? $state[implode('_', array_merge([$func], $values))] ?? '0x');
        } catch (ContractException) {
            return '0x';
        }
    }

    public function callUint256(string $functionSignature, array $types = [], array $values = []): string
    {
        $result = $this->call($functionSignature, $types, $values);
        return is_numeric($result) ? $result : '0';
    }

    public function callAddress(string $functionSignature, array $types = [], array $values = []): string
    {
        $result = $this->call($functionSignature, $types, $values);
        return preg_match('/^0x[0-9a-fA-F]{40}$/', $result) ? $result : '0x' . str_repeat('0', 40);
    }

    public function erc20Info(string $holderAddress): array
    {
        try {
            $token = $this->ledger->getTokenInfo($this->contractAddress);
            return [
                'name'         => $token['name'],
                'symbol'       => $token['symbol'],
                'decimals'     => $token['decimals'],
                'total_supply' => (string)$token['total_supply'],
                'balance'      => (string)($token['balances'][strtolower($holderAddress)] ?? 0),
            ];
        } catch (ContractException $e) {
            throw new ContractException("Contrato não é um ERC-20: " . $e->getMessage());
        }
    }

    public function erc721OwnerOf(int $tokenId): string
    {
        try {
            $state = $this->ledger->getContractState($this->contractAddress);
            return $state['owners'][$tokenId] ?? '0x' . str_repeat('0', 40);
        } catch (ContractException) {
            return '0x' . str_repeat('0', 40);
        }
    }

    public function erc721TokenURI(int $tokenId): string
    {
        try {
            $state = $this->ledger->getContractState($this->contractAddress);
            return $state['tokenURIs'][$tokenId] ?? "https://fakechain.local/nft/{$tokenId}";
        } catch (ContractException) {
            return "https://fakechain.local/nft/{$tokenId}";
        }
    }

    public function buildTransaction(
        string $fromAddress,
        string $functionSignature,
        array  $types = [],
        array  $values = [],
        string $value = '0x0',
        ?int   $gasLimit = null
    ): array {
        $gas    = $gasLimit ?? 60000;
        $gasFee = $gas * $this->ledger->getGasPrice();
        $nonce  = $this->ledger->getNonce($fromAddress);

        return [
            'from'    => $fromAddress,
            'to'      => $this->contractAddress,
            'nonce'   => '0x' . dechex($nonce),
            'gas'     => '0x' . dechex($gas),
            'gasPrice'=> '0x' . dechex((int)($this->ledger->getGasPrice() * 1e18)),
            'value'   => $value,
            'data'    => '0x' . bin2hex($functionSignature),
            'chainId' => '0x' . dechex($this->ledger->getChainId()),
            '__meta'  => [
                'type'     => 'contract_call',
                'function' => $functionSignature,
                'args'     => $values,
                'gas_fee'  => $gasFee,
                'amount'   => hexdec(ltrim($value, '0x')) / 1e18,
            ],
        ];
    }

    public function getLogs(string $eventSignature, int $fromBlock = 0, string $toBlock = 'latest'): array
    {
        $logs = [];
        $latestBlock = $this->ledger->getLatestBlockNumber();
        $toBlockNum  = $toBlock === 'latest' ? $latestBlock : (int)$toBlock;

        foreach ($this->ledger->getAllTxs() as $tx) {
            if (($tx['block'] ?? -1) < $fromBlock) continue;
            if (($tx['block'] ?? PHP_INT_MAX) > $toBlockNum) continue;
            if (strtolower($tx['to'] ?? '') !== strtolower($this->contractAddress)) continue;

            foreach ($tx['logs'] ?? [] as $log) {
                if (str_contains($log['event'] ?? '', explode('(', $eventSignature)[0])) {
                    $logs[] = $log;
                }
            }
        }
        return $logs;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FAKECHAIN — Facade principal (mesma interface do Web3PHP)
// ─────────────────────────────────────────────────────────────────────────────

class FakeChain
{
    public readonly WalletModule       $wallet;
    public readonly BlockModule        $block;
    public readonly TransferModule     $transfer;
    public readonly NetworkStatsModule $network;
    public readonly Ledger             $ledger;

    public bool   $autoMine    = true;
    public string $minerAddress = '0x' . str_repeat('f', 40);
    public array  $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'network'    => 'fakechain',
            'chain_id'   => 1337,
            'symbol'     => 'ETH',
            'gas_price'  => 0.000000021,  // 21 Gwei
            'block_time' => 12,
            'auto_mine'  => true,
        ], $config);

        $this->autoMine    = $this->config['auto_mine'];
        $this->minerAddress = $config['miner'] ?? $this->minerAddress;

        $this->ledger   = new Ledger($this->config);
        $this->wallet   = new WalletModule($this->ledger);
        $this->block    = new BlockModule($this->ledger);
        $this->transfer = new TransferModule($this->ledger, $this);
        $this->network  = new NetworkStatsModule($this->ledger, $this);
    }

    // ── Helpers de alto nível (mesmo que Web3PHP) ──────────────────────────

    public function balanceOf(string $address): string
    {
        return $this->wallet->getBalance($address);
    }

    public function latestBlock(): int
    {
        return $this->block->getLatestBlockNumber();
    }

    public function getTransaction(string $hash): array
    {
        return $this->block->getTransaction($hash);
    }

    /**
     * Sem API — não faz sentido aqui, mas mantém interface.
     */
    public function rpc(string $method, array $params = []): mixed
    {
        return match ($method) {
            'eth_blockNumber'           => '0x' . dechex($this->ledger->getLatestBlockNumber()),
            'eth_chainId'               => '0x' . dechex($this->ledger->getChainId()),
            'eth_gasPrice'              => '0x' . dechex((int)($this->ledger->getGasPrice() * 1e18)),
            'net_version'               => (string)$this->ledger->getChainId(),
            'net_listening'             => true,
            'net_peerCount'             => '0x0',
            'txpool_status'             => ['pending' => '0x' . dechex(count($this->ledger->getPendingTxs())), 'queued' => '0x0'],
            'web3_clientVersion'        => 'FakeChain/v1.0.0',
            default                     => null,
        };
    }

    public function info(): array
    {
        return [
            'library'     => 'FakeChain',
            'version'     => '1.0.0',
            'network'     => $this->config['network'],
            'provider'    => 'local_simulation',
            'rpc_url'     => 'local://fakechain',
            'chain_id'    => $this->config['chain_id'],
            'symbol'      => $this->config['symbol'],
            'is_evm'      => true,
            'auto_mine'   => $this->autoMine,
            'total_blocks'=> $this->ledger->getLatestBlockNumber() + 1,
        ];
    }

    public function switchNetwork(string $network): static
    {
        return new static(array_merge($this->config, ['network' => $network]));
    }

    public function contract(string $contractAddress, array $abi = []): ContractModule
    {
        return new ContractModule($this->ledger, $this, $contractAddress, $abi);
    }

    // ── FakeChain exclusive: ferramentas de desenvolvimento ────────────────

    /**
     * Cria uma carteira com saldo pré-definido.
     * Retorna address + private_key para uso nos testes.
     */
    public function createWallet(string $label = '', float $initialBalance = 0.0): array
    {
        $privateKey = Crypto::generatePrivateKey($label);
        $address    = Crypto::privateKeyToAddress($privateKey);

        $this->ledger->setBalance($address, $initialBalance);

        if ($label) {
            $this->ledger->setWalletName($address, $label);
        }

        return [
            'label'       => $label,
            'address'     => $address,
            'private_key' => $privateKey,
            'balance'     => $initialBalance,
        ];
    }

    /**
     * Adiciona saldo a uma carteira existente (faucet/airdrop fake).
     */
    public function faucet(string $address, float $amount): void
    {
        $current = $this->ledger->getBalance($address);
        $this->ledger->setBalance($address, $current + $amount);
    }

    /**
     * Atalho: transferência com assinatura em uma linha.
     *
     * $txHash = $chain->transfer($alice['address'], $bob['address'], 10.0, $alice['private_key']);
     */
    public function sendTransfer(string $from, string $to, float $amount, string $privateKey): string
    {
        $unsigned = $this->transfer->buildNativeTransfer($from, $to, $amount);
        return $this->transfer->signAndSend($privateKey, $unsigned);
    }

    /**
     * Atalho: transferência de token em uma linha.
     */
    public function sendTokenTransfer(
        string $from,
        string $contractAddress,
        string $to,
        float  $amount,
        string $privateKey,
        int    $decimals = 18
    ): string {
        $unsigned = $this->transfer->buildTokenTransfer($from, $contractAddress, $to, $amount, $decimals);
        return $this->transfer->signAndSend($privateKey, $unsigned);
    }

    /**
     * Deploya um token ERC-20 fake.
     * Retorna o endereço do contrato.
     */
    public function deployERC20(
        string $name,
        string $symbol,
        int    $decimals,
        float  $totalSupply,
        string $ownerAddress
    ): string {
        $contractAddr = Crypto::generateAddress("erc20_{$symbol}_{$name}");
        $this->ledger->deployToken($contractAddr, $name, $symbol, $decimals, $totalSupply, $ownerAddress);
        return $contractAddr;
    }

    /**
     * Deploya um contrato genérico com estado inicial.
     */
    public function deployContract(array $initialState = [], array $abi = []): string
    {
        $contractAddr = Crypto::generateAddress(json_encode($initialState) . microtime(true));
        $this->ledger->deployContract($contractAddr, $initialState, $abi);
        return $contractAddr;
    }

    /**
     * Deploya um NFT ERC-721 fake.
     */
    public function deployERC721(string $name, string $symbol, string $ownerAddress): string
    {
        $contractAddr = Crypto::generateAddress("erc721_{$symbol}_{$name}");
        $this->ledger->deployContract($contractAddr, [
            'name'        => $name,
            'symbol'      => $symbol,
            'owner'       => $ownerAddress,
            'owners'      => [],
            'tokenURIs'   => [],
            'totalSupply' => 0,
        ]);
        return $contractAddr;
    }

    /**
     * Minta um NFT para um endereço.
     */
    public function mintNFT(string $contractAddr, string $toAddress, ?string $tokenURI = null): int
    {
        $state  = $this->ledger->getContractState($contractAddr);
        $tokenId = ($state['totalSupply'] ?? 0) + 1;

        $state['owners'][$tokenId]    = $toAddress;
        $state['tokenURIs'][$tokenId] = $tokenURI ?? "https://fakechain.local/nft/{$contractAddr}/{$tokenId}";
        $state['totalSupply']         = $tokenId;

        $this->ledger->setContractState($contractAddr, $state);
        return $tokenId;
    }

    /**
     * Minera um bloco manualmente (quando autoMine = false).
     */
    public function mineBlock(): array
    {
        return $this->ledger->mine($this->minerAddress);
    }

    /**
     * Tira um snapshot do estado atual.
     */
    public function snapshot(string $label = ''): int
    {
        return $this->ledger->snapshot($label);
    }

    /**
     * Volta para um snapshot anterior.
     */
    public function rollback(int $snapshotId): void
    {
        $this->ledger->rollback($snapshotId);
    }

    /**
     * Lista snapshots disponíveis.
     */
    public function listSnapshots(): array
    {
        return $this->ledger->listSnapshots();
    }

    /**
     * Reset total da blockchain.
     */
    public function reset(): void
    {
        $this->ledger->reset($this->config);
    }

    /**
     * Dump do estado completo (debug).
     */
    public function dump(): array
    {
        $blocks  = $this->ledger->getAllBlocks();
        $tokens  = $this->ledger->getAllTokens();
        $pending = $this->ledger->getPendingTxs();

        $wallets = [];
        // Reconstituir lista de wallets a partir dos saldos internos
        $reflection = new \ReflectionProperty(Ledger::class, 'balances');
        $reflection->setAccessible(true);
        $balances = $reflection->getValue($this->ledger);

        foreach ($balances as $addr => $bal) {
            $wallets[$addr] = [
                'address' => $addr,
                'name'    => $this->ledger->getWalletName($addr),
                'balance' => $bal,
                'nonce'   => $this->ledger->getNonce($addr),
            ];
        }

        return [
            'info'           => $this->info(),
            'wallets'        => $wallets,
            'tokens'         => $tokens,
            'blocks'         => $blocks,
            'pending_txs'    => $pending,
            'total_txs'      => count($this->ledger->getAllTxs()),
        ];
    }

    /**
     * Imprime dump formatado no terminal (útil para debug).
     */
    public function inspect(): void
    {
        $dump = $this->dump();
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║              🔗 FAKECHAIN INSPECTOR                     ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n";
        echo "  Network : {$dump['info']['network']} (Chain ID: {$dump['info']['chain_id']})\n";
        echo "  Blocks  : {$dump['info']['total_blocks']}\n";
        echo "  TXs     : {$dump['total_txs']}\n";
        echo "  Pending : " . count($dump['pending_txs']) . "\n\n";

        echo "┌── WALLETS ─────────────────────────────────────────────\n";
        foreach ($dump['wallets'] as $w) {
            $name = $w['name'] ? "[{$w['name']}]" : '';
            echo "│  {$w['address']} {$name}\n";
            echo "│    Balance: {$w['balance']} {$this->config['symbol']}  |  Nonce: {$w['nonce']}\n";
        }

        if (!empty($dump['tokens'])) {
            echo "├── TOKENS ──────────────────────────────────────────────\n";
            foreach ($dump['tokens'] as $addr => $token) {
                echo "│  [{$token['symbol']}] {$token['name']} @ {$addr}\n";
                echo "│    Total Supply: {$token['total_supply']}  |  Decimals: {$token['decimals']}\n";
                foreach ($token['balances'] as $holder => $bal) {
                    if ($bal > 0) echo "│    {$holder}: {$bal}\n";
                }
            }
        }

        echo "└── BLOCKS ─────────────────────────────────────────────\n";
        foreach (array_slice(array_reverse($dump['blocks']), 0, 5) as $b) {
            echo "  #{$b['number']} | {$b['datetime']} | TXs: {$b['tx_count']} | Hash: " . substr($b['hash'], 0, 20) . "...\n";
        }
        if (count($dump['blocks']) > 5) {
            echo "  ... (mostrando últimos 5 de " . count($dump['blocks']) . ")\n";
        }
        echo "\n";
    }
}
