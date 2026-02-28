<?php

/**
 * FakeChain — Exemplos de Uso
 * ============================
 * Demonstração completa de todas as funcionalidades.
 * Zero API. Zero rede. 100% local.
 */

require_once __DIR__ . '/FakeChain.php';
use FakeChain\FakeChain;

// ═════════════════════════════════════════════════════════════════════════════
// 1. INICIALIZAÇÃO
// ═════════════════════════════════════════════════════════════════════════════

// Básico — configuração mínima
$chain = new FakeChain();

// Com todas as opções
$chain = new FakeChain([
    'network'    => 'fakechain',   // nome da rede (qualquer string)
    'chain_id'   => 1337,          // ID da chain
    'symbol'     => 'ETH',         // símbolo nativo
    'gas_price'  => 0.000000021,   // 21 Gwei
    'block_time' => 12,            // segundos entre blocos
    'auto_mine'  => true,          // minera automaticamente após cada TX
]);

// Com persistência em arquivo (recarrega estado entre execuções)
$chainPersistente = new FakeChain([
    'storage_path' => __DIR__ . '/fakechain_data.json',
    'auto_mine'    => true,
]);

// Com wallets pré-criadas no genesis
$chainComGenesis = new FakeChain([
    'genesis_wallets' => [
        '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => 1000.0,
        '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' => 500.0,
    ]
]);

echo "✅ " . $chain->info()['library'] . " v" . $chain->info()['version'] . " iniciado!\n\n";

// ═════════════════════════════════════════════════════════════════════════════
// 2. CRIANDO CARTEIRAS
// ═════════════════════════════════════════════════════════════════════════════

// Criar carteiras com label e saldo inicial
$alice = $chain->createWallet('Alice', 100.0);
$bob   = $chain->createWallet('Bob',   50.0);
$carol = $chain->createWallet('Carol', 0.0);

echo "👛 Alice: {$alice['address']}\n";
echo "   Chave: {$alice['private_key']}\n";
echo "   Saldo: {$alice['balance']} ETH\n\n";

// Checar saldo (mesma interface do Web3PHP)
echo $chain->wallet->getBalance($alice['address']); // "100"
echo $chain->balanceOf($bob['address']);             // "50" (atalho)

// Faucet: adicionar saldo a qualquer carteira
$chain->faucet($carol['address'], 25.0);
echo "Carol após faucet: " . $chain->wallet->getBalance($carol['address']) . " ETH\n";

// ═════════════════════════════════════════════════════════════════════════════
// 3. TRANSFERÊNCIAS NATIVAS
// ═════════════════════════════════════════════════════════════════════════════

// Atalho de uma linha (mais simples)
$txHash = $chain->sendTransfer(
    from:       $alice['address'],
    to:         $bob['address'],
    amount:     10.0,
    privateKey: $alice['private_key']
);
echo "\n💸 TX enviada: {$txHash}\n";

// Verificar saldos após transferência
echo "Alice: " . $chain->wallet->getBalance($alice['address']) . " ETH\n";
echo "Bob:   " . $chain->wallet->getBalance($bob['address'])   . " ETH\n";

// Forma completa (compatível com Web3PHP — build + sign + send)
$unsignedTx = $chain->transfer->buildNativeTransfer(
    from:   $bob['address'],
    to:     $carol['address'],
    amount: 5.0
);
$txHash2 = $chain->transfer->signAndSend($bob['private_key'], $unsignedTx);
echo "\n💸 TX2: {$txHash2}\n";

// Erro esperado: saldo insuficiente
try {
    $chain->sendTransfer($carol['address'], $alice['address'], 9999.0, $carol['private_key']);
} catch (\FakeChain\TransferException $e) {
    echo "❌ Esperado: " . $e->getMessage() . "\n";
}

// Erro esperado: chave privada errada
try {
    $chain->sendTransfer($alice['address'], $bob['address'], 1.0, $bob['private_key']); // chave errada!
} catch (\FakeChain\TransferException $e) {
    echo "❌ Esperado: " . $e->getMessage() . "\n";
}

// ═════════════════════════════════════════════════════════════════════════════
// 4. TOKENS ERC-20
// ═════════════════════════════════════════════════════════════════════════════

// Deploy de token
$usdtAddr = $chain->deployERC20(
    name:        'Fake USDT',
    symbol:      'USDT',
    decimals:    6,
    totalSupply: 1_000_000.0,
    ownerAddress: $alice['address']
);
echo "\n🪙 USDT deployado em: {$usdtAddr}\n";

// Verificar saldo de token
$aliceUsdt = $chain->wallet->getTokenBalance($alice['address'], $usdtAddr, 6);
echo "Alice USDT: {$aliceUsdt}\n"; // 1000000

// Transferir token (atalho)
$tokenTxHash = $chain->sendTokenTransfer(
    from:            $alice['address'],
    contractAddress: $usdtAddr,
    to:              $bob['address'],
    amount:          250.0,
    privateKey:      $alice['private_key'],
    decimals:        6
);
echo "💸 Token TX: {$tokenTxHash}\n";

echo "Alice USDT após: " . $chain->wallet->getTokenBalance($alice['address'], $usdtAddr, 6) . "\n"; // 999750
echo "Bob USDT:        " . $chain->wallet->getTokenBalance($bob['address'],   $usdtAddr, 6) . "\n"; // 250

// Transferir token (forma Web3PHP — buildTokenTransfer + signAndSend)
$tokenUnsigned = $chain->transfer->buildTokenTransfer(
    from:            $bob['address'],
    contractAddress: $usdtAddr,
    to:              $carol['address'],
    amount:          100.0,
    decimals:        6
);
$chain->transfer->signAndSend($bob['private_key'], $tokenUnsigned);

// Info ERC-20 via módulo contract (mesma interface do Web3PHP)
$usdtContract = $chain->contract($usdtAddr);
$info = $usdtContract->erc20Info($alice['address']);
print_r($info);
/*
Array (
    [name]         => Fake USDT
    [symbol]       => USDT
    [decimals]     => 6
    [total_supply] => 1000000
    [balance]      => 999750
)
*/

// Chamadas individuais
echo $usdtContract->call('name()') . "\n";           // Fake USDT
echo $usdtContract->callUint256('decimals()') . "\n"; // 6
echo $usdtContract->callUint256('totalSupply()') . "\n";

// Allowance
$chain->ledger->setAllowance($usdtAddr, $alice['address'], $bob['address'], 500.0);
echo "Allowance Alice→Bob: " . $chain->wallet->getAllowance($usdtAddr, $alice['address'], $bob['address']) . "\n";

// Portfolio de tokens de uma carteira
$portfolio = $chain->wallet->getTokenPortfolio($alice['address']);
foreach ($portfolio as $token) {
    echo "  {$token['symbol']}: {$token['balance']}\n";
}

// Deploy de um segundo token
$daiAddr = $chain->deployERC20('Fake DAI', 'DAI', 18, 500_000.0, $bob['address']);
$chain->sendTokenTransfer($bob['address'], $daiAddr, $carol['address'], 1000.0, $bob['private_key'], 18);

// ═════════════════════════════════════════════════════════════════════════════
// 5. NFT ERC-721
// ═════════════════════════════════════════════════════════════════════════════

// Deploy de NFT
$nftAddr = $chain->deployERC721('FakeApe', 'FAPE', $alice['address']);
echo "\n🖼️ NFT deployado em: {$nftAddr}\n";

// Mintar NFTs
$tokenId1 = $chain->mintNFT($nftAddr, $alice['address'], 'https://api.fakeapes.com/1.json');
$tokenId2 = $chain->mintNFT($nftAddr, $bob['address']);
$tokenId3 = $chain->mintNFT($nftAddr, $alice['address']);

echo "Token #1 owner: " . $chain->contract($nftAddr)->erc721OwnerOf($tokenId1) . "\n";
echo "Token #1 URI:   " . $chain->contract($nftAddr)->erc721TokenURI($tokenId1) . "\n";
echo "Token #2 owner: " . $chain->contract($nftAddr)->erc721OwnerOf($tokenId2) . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 6. BLOCOS
// ═════════════════════════════════════════════════════════════════════════════

// Último bloco
$latestNum = $chain->latestBlock();
echo "\n📦 Último bloco: #{$latestNum}\n";

// Detalhes do bloco
$latestBlock = $chain->block->getBlock('latest');
echo "  Hash:      " . substr($latestBlock['hash'], 0, 20) . "...\n";
echo "  Timestamp: {$latestBlock['datetime']}\n";
echo "  TXs:       {$latestBlock['tx_count']}\n";
echo "  Gas usado: {$latestBlock['gas_used']}\n";
echo "  Gas limit: {$latestBlock['gas_limit']}\n";

// Bloco com transações completas
$blockComTxs = $chain->block->getBlock('latest', fullTransactions: true);

// Bloco por número
$bloco0 = $chain->block->getBlock(0); // Genesis

// Range de blocos
$blocos = $chain->block->getBlockRange(0, 5);
foreach ($blocos as $b) {
    echo "  #{$b['number']} — {$b['tx_count']} txs — {$b['datetime']}\n";
}

// Gas info
$gasInfo = $chain->block->getGasInfo();
print_r($gasInfo);

// Estimar gas
$gasEst = $chain->block->estimateGas(['from' => $alice['address'], 'to' => $bob['address'], 'data' => '0x']);
echo "Gas estimado (ETH transfer): {$gasEst}\n"; // 21000

// ═════════════════════════════════════════════════════════════════════════════
// 7. TRANSAÇÕES
// ═════════════════════════════════════════════════════════════════════════════

// Detalhes de uma TX específica
$tx = $chain->getTransaction($txHash);
print_r($tx);
/*
Array (
    [hash]      => 0x...
    [from]      => 0xalice...
    [to]        => 0xbob...
    [value]     => 10
    [value_eth] => 10
    [gas]       => 21000
    [nonce]     => 0
    [status]    => success
    [block]     => 1
    [datetime]  => 2024-01-01 12:00:00
    [type]      => transfer
)
*/

// Histórico de transações de uma carteira
$history = $chain->wallet->getTransactionHistory($alice['address']);
echo "\n📋 Histórico Alice (" . count($history) . " txs):\n";
foreach ($history as $h) {
    $dir = strtolower($h['from']) === strtolower($alice['address']) ? '→ enviou' : '← recebeu';
    echo "  [{$h['datetime']}] {$dir} {$h['value_eth']} ETH | {$h['status']}\n";
}

// Histórico de transferências de token
$tokenHistory = $chain->wallet->getTokenTransfers($alice['address']);
echo "\n🪙 Histórico Token Alice (" . count($tokenHistory) . " txs)\n";

// Aguardar confirmação (quando autoMine = false)
$chain->autoMine = false;
$txPendente = $chain->sendTransfer($alice['address'], $carol['address'], 1.0, $alice['private_key']);
echo "\nTX pendente: {$txPendente}\n";
echo "Pending pool: " . $chain->network->getMempoolSize() . " txs\n";

// Minar manualmente
$novoBloco = $chain->mineBlock();
echo "Bloco #{$novoBloco['number']} minerado!\n";
$chain->autoMine = true;

// waitForConfirmation (interface Web3PHP)
// $receipt = $chain->transfer->waitForConfirmation($txPendente);

// ═════════════════════════════════════════════════════════════════════════════
// 8. CONTRATO GENÉRICO (estado personalizado)
// ═════════════════════════════════════════════════════════════════════════════

// Deploy de um contrato de votação fake
$votingAddr = $chain->deployContract([
    'title'       => 'Melhor blockchain?',
    'options'     => ['Ethereum', 'Solana', 'Tron'],
    'votes'       => [0, 0, 0],
    'totalVotes'  => 0,
    'ended'       => false,
]);

echo "\n🗳️ Contrato de votação: {$votingAddr}\n";

// Ler estado do contrato
$voting = $chain->contract($votingAddr);
echo $voting->call('title') . "\n"; // Melhor blockchain?

// Modificar estado manualmente (simula execução de função)
$state = $chain->ledger->getContractState($votingAddr);
$state['votes'][0]++;    // +1 voto para Ethereum
$state['totalVotes']++;
$chain->ledger->setContractState($votingAddr, $state);

$stateAtualizado = $chain->ledger->getContractState($votingAddr);
echo "Votos Ethereum: " . $stateAtualizado['votes'][0] . "\n";
echo "Total votos:    " . $stateAtualizado['totalVotes'] . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 9. INFORMAÇÕES DE REDE
// ═════════════════════════════════════════════════════════════════════════════

$nodeInfo = $chain->network->getNodeInfo();
print_r($nodeInfo);
/*
Array (
    [version]    => FakeChain/v1.0.0/php8.3
    [chain_id]   => 1337
    [peer_count] => 0
    [listening]  => true
    [syncing]    => false
    [network]    => fakechain
    [rpc_url]    => local://fakechain
    [symbol]     => ETH
    [mode]       => local_simulation
)
*/

echo "Chain ID: " . $chain->network->getChainId() . "\n";

// Taxas Bitcoin fake
$btcFees = $chain->network->getBitcoinFeeRecommendations();
print_r($btcFees);

// ═════════════════════════════════════════════════════════════════════════════
// 10. SNAPSHOT E ROLLBACK — perfeito para testes unitários
// ═════════════════════════════════════════════════════════════════════════════

// Estado antes dos testes
$snapshotId = $chain->snapshot('antes_dos_testes');
echo "\n📸 Snapshot criado: ID {$snapshotId}\n";

// Executar operações destrutivas
$chain->faucet($alice['address'], 99999.0);
echo "Alice após faucet: " . $chain->wallet->getBalance($alice['address']) . " ETH\n";

$chain->sendTransfer($alice['address'], $bob['address'], 50000.0, $alice['private_key']);
echo "Bob após tx: " . $chain->wallet->getBalance($bob['address']) . " ETH\n";

// Voltar ao estado anterior
$chain->rollback($snapshotId);
echo "✅ Rollback executado!\n";
echo "Alice restaurada: " . $chain->wallet->getBalance($alice['address']) . " ETH\n";
echo "Bob restaurado:   " . $chain->wallet->getBalance($bob['address'])   . " ETH\n";

// Listar snapshots
$snapshots = $chain->listSnapshots();
foreach ($snapshots as $s) {
    echo "  [{$s['id']}] {$s['label']}\n";
}

// ═════════════════════════════════════════════════════════════════════════════
// 11. NONCE
// ═════════════════════════════════════════════════════════════════════════════

$aliceNonce = $chain->wallet->getNonce($alice['address']);
echo "\n🔢 Nonce Alice: {$aliceNonce}\n";

// ═════════════════════════════════════════════════════════════════════════════
// 12. RPC RAW (mesma interface do Web3PHP)
// ═════════════════════════════════════════════════════════════════════════════

echo "\n🔌 RPC:\n";
echo "eth_blockNumber: " . $chain->rpc('eth_blockNumber') . "\n";
echo "eth_chainId:     " . $chain->rpc('eth_chainId') . "\n";
echo "eth_gasPrice:    " . $chain->rpc('eth_gasPrice') . "\n";
echo "net_version:     " . $chain->rpc('net_version') . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 13. INSPETOR VISUAL (debug)
// ═════════════════════════════════════════════════════════════════════════════

$chain->inspect();
/*
╔══════════════════════════════════════════════════════════╗
║              🔗 FAKECHAIN INSPECTOR                     ║
╚══════════════════════════════════════════════════════════╝
  Network : fakechain (Chain ID: 1337)
  Blocks  : 12
  TXs     : 8
  Pending : 0

┌── WALLETS ─────────────────────────────────────────────
│  0xabc...  [Alice]
│    Balance: 89.999...  |  Nonce: 3
│  0xdef...  [Bob]
│    Balance: 65.0  |  Nonce: 2
...
*/

// ═════════════════════════════════════════════════════════════════════════════
// 14. SWITCH DE REDE (interface Web3PHP)
// ═════════════════════════════════════════════════════════════════════════════

// Cria uma instância "irmã" com outro chain_id
$polygon = $chain->switchNetwork('polygon');
echo "\nNetwork switched to: " . $polygon->info()['network'] . "\n";

// ═════════════════════════════════════════════════════════════════════════════
// 15. USO EM TESTES UNITÁRIOS (exemplo PHPUnit)
// ═════════════════════════════════════════════════════════════════════════════

/*
class TransferServiceTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;
    private int $snapshot;

    public function setUp(): void
    {
        $this->chain = new FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   0.0);
        $this->snapshot = $this->chain->snapshot('test_setup');
    }

    public function tearDown(): void
    {
        $this->chain->rollback($this->snapshot); // estado limpo para o próximo teste
    }

    public function testTransfer(): void
    {
        $txHash = $this->chain->sendTransfer(
            $this->alice['address'],
            $this->bob['address'],
            10.0,
            $this->alice['private_key']
        );

        $this->assertNotEmpty($txHash);
        $this->assertStringStartsWith('0x', $txHash);

        $aliceBal = (float)$this->chain->wallet->getBalance($this->alice['address']);
        $bobBal   = (float)$this->chain->wallet->getBalance($this->bob['address']);

        $this->assertLessThan(100.0, $aliceBal); // pagou valor + gas
        $this->assertEquals(10.0, $bobBal);
    }

    public function testTokenTransfer(): void
    {
        $usdtAddr = $this->chain->deployERC20('USDT', 'USDT', 6, 10000.0, $this->alice['address']);

        $this->chain->sendTokenTransfer(
            $this->alice['address'], $usdtAddr, $this->bob['address'], 500.0, $this->alice['private_key'], 6
        );

        $this->assertEquals('9500', $this->chain->wallet->getTokenBalance($this->alice['address'], $usdtAddr, 6));
        $this->assertEquals('500',  $this->chain->wallet->getTokenBalance($this->bob['address'],   $usdtAddr, 6));
    }

    public function testInsufficientBalance(): void
    {
        $this->expectException(\FakeChain\TransferException::class);
        $this->chain->sendTransfer($this->bob['address'], $this->alice['address'], 9999.0, $this->bob['private_key']);
    }
}
*/

echo "\n✅ Todos os exemplos executados!\n";
