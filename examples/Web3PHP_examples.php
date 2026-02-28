<?php

/**
 * Web3PHP — Usage Examples
 * ========================
 * Exemplos completos de uso da biblioteca Web3PHP.
 * Substitua as chaves de API pelas suas antes de usar.
 */

require_once __DIR__ . '/Web3PHP.php';
use Web3PHP\Web3PHP;
use Web3PHP\Networks;
use Web3PHP\Web3Exception;

// ═════════════════════════════════════════════════════════════════════════════
// 1. CONFIGURAÇÃO — Escolha seu provider
// ═════════════════════════════════════════════════════════════════════════════

// Via Infura (Ethereum, Polygon, Arbitrum, Optimism, Avalanche)
$eth = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'infura',
    'api_key'  => 'SEU_INFURA_KEY',
]);

// Via Alchemy (Ethereum, Polygon, Arbitrum, Base, Optimism)
$poly = new Web3PHP([
    'network'  => 'polygon',
    'provider' => 'alchemy',
    'api_key'  => 'SEU_ALCHEMY_KEY',
]);

// Via RPC Público (sem chave — OK para desenvolvimento)
$bsc = new Web3PHP(['network' => 'bsc']);
$avax = new Web3PHP(['network' => 'avalanche']);
$arb  = new Web3PHP(['network' => 'arbitrum']);
$base = new Web3PHP(['network' => 'base']);
$op   = new Web3PHP(['network' => 'optimism']);

// Bitcoin (via mempool.space)
$btc = new Web3PHP(['network' => 'bitcoin']);

// Solana
$sol = new Web3PHP(['network' => 'solana']);

// Tron via TronGrid
$tron = new Web3PHP([
    'network'  => 'tron',
    'provider' => 'public',
    'api_key'  => 'SEU_TRONGRID_KEY', // opcional, aumenta rate limit
]);

// Node Local (Hardhat / Geth / Ganache)
$local = new Web3PHP([
    'network'  => 'hardhat',
    'provider' => 'local',
    'rpc_url'  => 'http://127.0.0.1:8545',
]);

// QuickNode (informe o endpoint completo)
$qn = new Web3PHP([
    'network'  => 'ethereum',
    'provider' => 'quicknode',
    'rpc_url'  => 'https://SEU_ENDPOINT.quiknode.pro/SEU_TOKEN/',
]);

// ═════════════════════════════════════════════════════════════════════════════
// 2. INFORMAÇÕES DA BIBLIOTECA E REDE
// ═════════════════════════════════════════════════════════════════════════════

$info = $eth->info();
print_r($info);
/*
Array (
    [library]  => Web3PHP
    [version]  => 1.0.0
    [network]  => ethereum
    [provider] => infura
    [rpc_url]  => https://mainnet.infura.io/v3/...
    [chain_id] => 1
    [symbol]   => ETH
    [is_evm]   => true
)
*/

// ═════════════════════════════════════════════════════════════════════════════
// 3. CARTEIRAS — Saldos
// ═════════════════════════════════════════════════════════════════════════════

$address = '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'; // Vitalik

// Saldo ETH (retorna string em ETH)
$ethBalance = $eth->wallet->getBalance($address);
echo "ETH Balance: {$ethBalance} ETH\n";

// Mesmo endereço, rede diferente
$maticBalance = $poly->wallet->getBalance($address);
echo "MATIC Balance: {$maticBalance} MATIC\n";

// Bitcoin
$btcBalance = $btc->wallet->getBalance('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');
echo "BTC Balance: {$btcBalance} BTC\n";

// Solana
$solBalance = $sol->wallet->getBalance('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM');
echo "SOL Balance: {$solBalance} SOL\n";

// Tron
$trxBalance = $tron->wallet->getBalance('TN3W4H6rK2ce4vX9YnFQHwKENnHjoxb3m9');
echo "TRX Balance: {$trxBalance} TRX\n";

// Saldo de Token ERC-20 (ex: USDT no Ethereum)
$usdtContract = '0xdAC17F958D2ee523a2206206994597C13D831ec7';
$usdtBalance  = $eth->wallet->getTokenBalance($address, $usdtContract, decimals: 6);
echo "USDT Balance: {$usdtBalance} USDT\n";

// Nonce / próximo número de transação
$nonce = $eth->wallet->getNonce($address);
echo "Nonce: {$nonce}\n";

// Allowance de um token
$spender     = '0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D'; // Uniswap V2 Router
$allowance   = $eth->wallet->getAllowance($usdtContract, $address, $spender, decimals: 6);
echo "Allowance USDT → Uniswap: {$allowance}\n";

// ═════════════════════════════════════════════════════════════════════════════
// 4. BLOCOS
// ═════════════════════════════════════════════════════════════════════════════

// Último bloco
$latestBlock = $eth->block->getLatestBlockNumber();
echo "Latest ETH block: {$latestBlock}\n";

// Detalhes do bloco
$block = $eth->block->getBlock('latest');
print_r($block);
/*
Array (
    [number]        => 19000000
    [hash]          => 0xabc...
    [parent_hash]   => 0xdef...
    [timestamp]     => 1700000000
    [datetime]      => 2023-11-14 22:13:20
    [miner]         => 0x...
    [gas_limit]     => 30000000
    [gas_used]      => 14500000
    [base_fee_gwei] => 12.500000000
    [tx_count]      => 142
    [transactions]  => [...]
    ...
)
*/

// Bloco por número
$block18M = $eth->block->getBlock(18000000);

// Bloco Bitcoin
$btcBlock = $btc->block->getBlock('latest');
print_r($btcBlock);

// Bloco Solana (slot)
$solBlock = $sol->block->getBlock('latest');

// ═════════════════════════════════════════════════════════════════════════════
// 5. TRANSAÇÕES
// ═════════════════════════════════════════════════════════════════════════════

// Detalhes de uma transação
$tx = $eth->getTransaction('0x5c504ed432cb51138bcf09aa5e8a410dd4a1e204ef84bfed1be16dfba1b22060');
print_r($tx);
/*
Array (
    [hash]      => 0x5c50...
    [from]      => 0x...
    [to]        => 0x...
    [value_eth] => 1.0
    [value_wei] => 1000000000000000000
    [gas]       => 21000
    [gas_price] => 50000000000
    [nonce]     => 0
    [block]     => 46147
    [status]    => success
    [gas_used]  => 21000
    [logs]      => []
)
*/

// Transação Bitcoin
$btcTx = $btc->block->getTransaction('a1075db55d416d3ca199f55b6084e2115b9345e16c5cf302fc80e9d5fbf5d48d');

// Transação Solana
$solTx = $sol->block->getTransaction('ASSINATURA_DA_TRANSACAO');

// Transação Tron
$tronTx = $tron->block->getTransaction('TXID_AQUI');

// ═════════════════════════════════════════════════════════════════════════════
// 6. GAS e TAXAS
// ═════════════════════════════════════════════════════════════════════════════

// Gas price e base fee (EVM)
$gas = $eth->block->getGasInfo();
print_r($gas);
/*
Array (
    [gas_price_wei]  => 15000000000
    [gas_price_gwei] => 15.000000000
    [base_fee_gwei]  => 12.500000000
)
*/

// Estimar gas de uma transação
$estimatedGas = $eth->block->estimateGas([
    'from'  => '0xSEU_ENDERECO',
    'to'    => '0xDESTINO',
    'value' => '0xDE0B6B3A7640000', // 1 ETH em hex
]);
echo "Gas estimado: {$estimatedGas}\n";

// Taxas recomendadas para Bitcoin
$btcFees = $btc->network->getBitcoinFeeRecommendations();
print_r($btcFees);
/*
Array (
    [fastestFee]  => 25  (sat/vbyte)
    [halfHourFee] => 20
    [hourFee]     => 15
    [economyFee]  => 10
    [minimumFee]  => 5
)
*/

// Mempool Bitcoin
$mempool = $btc->network->getBitcoinMempoolStats();

// ═════════════════════════════════════════════════════════════════════════════
// 7. SMART CONTRACTS (EVM)
// ═════════════════════════════════════════════════════════════════════════════

// Instanciar um contrato
$usdt = $eth->contract('0xdAC17F958D2ee523a2206206994597C13D831ec7');

// Chamar função view (read-only)
$rawName = $usdt->call('name()');
echo "Token: {$rawName}\n";

$decimals = $usdt->callUint256('decimals()');
echo "Decimals: {$decimals}\n";

$totalSupply = $usdt->callUint256('totalSupply()');
echo "Total Supply: {$totalSupply}\n";

// Informações completas ERC-20
$erc20Info = $usdt->erc20Info($address);
print_r($erc20Info);
/*
Array (
    [name]         => Tether USD
    [symbol]       => USDT
    [decimals]     => 6
    [total_supply] => 83000000000000000
    [balance]      => 1500000000
)
*/

// NFT ERC-721 — dono de um token
$bayc      = $eth->contract('0xBC4CA0EdA7647A8aB7C2061c2E118A18a936f13D');
$owner     = $bayc->erc721OwnerOf(1);
echo "Owner of BAYC #1: {$owner}\n";

// Token URI
$uri = $bayc->erc721TokenURI(1);
echo "Token URI: {$uri}\n";

// Construir transação para escrita (state-changing)
$unsignedTx = $usdt->buildTransaction(
    fromAddress:       '0xSEU_ENDERECO',
    functionSignature: 'transfer(address,uint256)',
    types:             ['address', 'uint256'],
    values:            ['0xDESTINO', '1000000'], // 1 USDT (6 decimais)
    gasLimit:          60000
);
// Assinar com kornrunner/ethereum-offline-raw-tx e enviar com $eth->transfer->sendRaw()

// Buscar eventos/logs do contrato
$transferEvents = $usdt->getLogs(
    'Transfer(address,address,uint256)',
    fromBlock: 19000000,
    toBlock: 'latest'
);
print_r($transferEvents);

// ═════════════════════════════════════════════════════════════════════════════
// 8. TRANSFERÊNCIAS
// ═════════════════════════════════════════════════════════════════════════════

// --- EVM ---
// Construir TX não-assinada de ETH nativo
$unsignedEthTx = $eth->transfer->buildNativeTransfer(
    from:   '0xSEU_ENDERECO',
    to:     '0xDESTINO',
    amount: 0.01 // 0.01 ETH
);
print_r($unsignedEthTx);
// → Assinar com sua biblioteca de preferência e chamar sendRaw()

// Construir TX de token ERC-20
$unsignedTokenTx = $eth->transfer->buildTokenTransfer(
    from:            '0xSEU_ENDERECO',
    contractAddress: '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    to:              '0xDESTINO',
    amount:          100.0,  // 100 USDT
    decimals:        6
);

// Enviar TX já assinada (raw hex)
// $txHash = $eth->transfer->sendRaw('0x' . $rawSignedHex);

// Aguardar mineração
// $receipt = $eth->transfer->waitForConfirmation($txHash, timeoutSeconds: 120);

// --- Bitcoin ---
// Obter UTXOs disponíveis
$utxos = $btc->transfer->getBitcoinUTXOs('SEU_ENDERECO_BITCOIN');
print_r($utxos);

// Broadcast de TX Bitcoin assinada (raw hex)
// $btcTxId = $btc->transfer->broadcastBitcoin('TX_HEX_ASSINADA');

// --- Solana ---
// Broadcast de TX Solana assinada (base64)
// $solTxId = $sol->transfer->sendSolanaTransaction('BASE64_TX_ASSINADA');

// --- Tron ---
// Broadcast de TX Tron assinada (array)
// $tronResult = $tron->transfer->broadcastTron(['txID' => '...', 'signature' => ['...'], ...]);

// ═════════════════════════════════════════════════════════════════════════════
// 9. HISTÓRICO DE TRANSAÇÕES (requer chave da API do Explorer)
// ═════════════════════════════════════════════════════════════════════════════

$history = $eth->wallet->getTransactionHistory(
    address:        '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
    explorerApiKey: 'SEU_ETHERSCAN_KEY',
    page:           1,
    offset:         10
);

foreach ($history as $tx) {
    echo "[{$tx['datetime']}] {$tx['from']} → {$tx['to']} | {$tx['value_eth']} ETH | {$tx['status']}\n";
}

// Transferências de token
$tokenHistory = $eth->wallet->getTokenTransfers(
    address:         '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
    explorerApiKey:  'SEU_ETHERSCAN_KEY',
    contractAddress: '0xdAC17F958D2ee523a2206206994597C13D831ec7' // filtrar por USDT
);

// ═════════════════════════════════════════════════════════════════════════════
// 10. INFORMAÇÕES DA REDE
// ═════════════════════════════════════════════════════════════════════════════

// Informações do nó
$nodeInfo = $eth->network->getNodeInfo();
print_r($nodeInfo);
/*
Array (
    [version]    => Geth/v1.13.2-stable/linux-amd64/go1.21.0
    [chain_id]   => 1
    [peer_count] => 50
    [listening]  => true
    [syncing]    => false
    [network]    => ethereum
    [rpc_url]    => https://mainnet.infura.io/v3/...
    [symbol]     => ETH
)
*/

// Chain ID
echo "Chain ID: " . $eth->network->getChainId() . "\n";

// Epoch Solana
$epoch = $sol->network->getSolanaEpoch();
print_r($epoch);

// Bandwidth e Energia Tron
$tronResources = $tron->network->getTronBandwidth('TRON_ADDRESS');
print_r($tronResources);

// ═════════════════════════════════════════════════════════════════════════════
// 11. TROCAR DE REDE SEM RECONFIGURAR
// ═════════════════════════════════════════════════════════════════════════════

// $eth foi configurado com Infura → reutilizar as credenciais para outra rede
$goerli = $eth->switchNetwork('goerli');
echo "Goerli latest block: " . $goerli->latestBlock() . "\n";

$polygon = $eth->switchNetwork('polygon');
echo "Polygon latest block: " . $polygon->latestBlock() . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 12. JSON-RPC DIRETO (usuários avançados)
// ═════════════════════════════════════════════════════════════════════════════

// Fazer qualquer chamada RPC diretamente
$accounts      = $local->rpc('eth_accounts');
$pendingTxPool = $local->rpc('txpool_content');
$proof         = $eth->rpc('eth_getProof', ['0xSEU_ENDERECO', [], 'latest']);

// ═════════════════════════════════════════════════════════════════════════════
// 13. TRATAMENTO DE ERROS
// ═════════════════════════════════════════════════════════════════════════════

use Web3PHP\WalletException;
use Web3PHP\NetworkException;
use Web3PHP\BlockException;
use Web3PHP\TransferException;
use Web3PHP\ContractException;

try {
    $balance = $eth->wallet->getBalance('endereco_invalido');
} catch (WalletException $e) {
    echo "Erro de carteira: " . $e->getMessage() . "\n";
} catch (NetworkException $e) {
    echo "Erro de rede: " . $e->getMessage() . "\n";
} catch (Web3PHP\Web3Exception $e) {
    echo "Erro genérico: " . $e->getMessage() . "\n";
}

// ═════════════════════════════════════════════════════════════════════════════
// 14. UTILITÁRIOS — Conversões de unidade
// ═════════════════════════════════════════════════════════════════════════════

use Web3PHP\Math;
use Web3PHP\Address;

// ETH ↔ Wei
echo Math::etherToWei(1.5) . "\n";           // 1500000000000000000
echo Math::weiToEther('1500000000000000000') . "\n"; // 1.5

// SOL ↔ Lamports
echo Math::solToLamports(1.5) . "\n";        // 1500000000
echo Math::lamportsToSol(1500000000) . "\n"; // 1.5

// BTC ↔ Satoshi
echo Math::satoshiToBtc(100000000) . "\n";   // 1.0

// TRX ↔ Sun
echo Math::sunToTrx(1000000) . "\n";         // 1.0

// Unidades genéricas (ERC-20 tokens, etc.)
echo Math::parseUnits('100', 6) . "\n";       // 100000000 (100 USDT)
echo Math::formatUnits('100000000', 6) . "\n"; // 100.000000

// Validação de endereços
var_dump(Address::isValidEVM('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'));   // true
var_dump(Address::isValidBitcoin('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh')); // true
var_dump(Address::isValidSolana('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM')); // true
var_dump(Address::isValidTron('TN3W4H6rK2ce4vX9YnFQHwKENnHjoxb3m9'));             // true

// Validação automática por rede
var_dump(Address::validate('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', 'ethereum')); // true
var_dump(Address::validate('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 'bitcoin')); // true
