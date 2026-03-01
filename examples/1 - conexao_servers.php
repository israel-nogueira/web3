<?php

// ═══════════════════════════════════════════════════════════
//  Web3PHP — Configuração com Servidores Externos
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Web3PHP\Web3PHP;

// ─── 1. Infura ────────────────────────────────────────────
$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'SEU_INFURA_KEY',
]);

// ─── 2. Alchemy ───────────────────────────────────────────
$poly = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

// ─── 3. QuickNode (endpoint completo) ────────────────────
$qn = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'quicknode',
    'rpc_url'  => 'https://SEU_ENDPOINT.quiknode.pro/SEU_TOKEN/',
]);

// ─── 4. RPC Público (sem chave) ───────────────────────────
$bsc  = new Web3PHP(['network' => 'bsc']);
$arb  = new Web3PHP(['network' => 'arbitrum']);
$base = new Web3PHP(['network' => 'base']);
$avax = new Web3PHP(['network' => 'avalanche']);
$op   = new Web3PHP(['network' => 'optimism']);

// ─── 5. Nó Local (Hardhat / Ganache) ─────────────────────
$local = new Web3PHP([
    'network'  => 'hardhat',
    'provider' => 'local',
    'rpc_url'  => 'http://127.0.0.1:8545',
]);

// ─── 6. Bitcoin (mempool.space) ───────────────────────────
$btc = new Web3PHP(['network' => 'bitcoin']);

// ─── 7. Solana ────────────────────────────────────────────
$sol = new Web3PHP(['network' => 'solana']);

// ─── 8. Tron via TronGrid ─────────────────────────────────
$tron = new Web3PHP([
    'network'  => 'tron',
    'provider' => 'public',
    'api_key'  => 'SEU_TRONGRID_KEY', // opcional
]);

// ═══════════════════════════════════════════════════════════
//  Uso após conectar
// ═══════════════════════════════════════════════════════════

// Info da conexão
print_r($eth->info());

// Último bloco
echo $eth->latestBlock() . PHP_EOL;

// Saldo nativo
echo $eth->wallet->getBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045') . PHP_EOL;

// Detalhes de TX
$tx = $eth->getTransaction('0xSUA_TX_HASH');
print_r($tx);

// Trocar de rede reutilizando credenciais do Infura
$goerli  = $eth->switchNetwork('goerli');
$polygon = $eth->switchNetwork('polygon');

// RPC direto (usuários avançados)
$accounts = $local->rpc('eth_accounts');
$txPool   = $local->rpc('txpool_content');

// ─── Tratamento de erros ──────────────────────────────────
use Web3PHP\WalletException;
use Web3PHP\NetworkException;
use Web3PHP\Web3Exception;

try {
    $balance = $eth->wallet->getBalance('endereco_invalido');
} catch (WalletException $e) {
    echo "Erro de carteira: " . $e->getMessage() . PHP_EOL;
} catch (NetworkException $e) {
    echo "Erro de rede: " . $e->getMessage() . PHP_EOL;
} catch (Web3Exception $e) {
    echo "Erro genérico: " . $e->getMessage() . PHP_EOL;
}