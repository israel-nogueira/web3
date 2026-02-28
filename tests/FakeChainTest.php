<?php

/**
 * Web3PHP + FakeChain — Test Suite
 * ==================================
 * Testes unitários prontos para rodar com PHPUnit.
 *
 * INSTALAR:
 *   composer require --dev phpunit/phpunit
 *
 * RODAR:
 *   ./vendor/bin/phpunit tests/
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FakeChain\FakeChain;
use FakeChain\TransferException;
use FakeChain\BlockException;
use FakeChain\ContractException;
use FakeChain\WalletException;

require_once __DIR__ . '/../src/FakeChain.php';

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 1 — WALLETS
// ─────────────────────────────────────────────────────────────────────────────

class WalletTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;

    protected function setUp(): void
    {
        $this->chain = new FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   50.0);
    }

    public function testCreateWalletReturnsCorrectStructure(): void
    {
        $this->assertArrayHasKey('address',     $this->alice);
        $this->assertArrayHasKey('private_key', $this->alice);
        $this->assertArrayHasKey('balance',     $this->alice);
        $this->assertArrayHasKey('label',       $this->alice);
        $this->assertEquals('Alice', $this->alice['label']);
    }

    public function testAddressFormat(): void
    {
        $address = $this->alice['address'];
        $this->assertStringStartsWith('0x', $address);
        $this->assertEquals(42, strlen($address));
    }

    public function testInitialBalance(): void
    {
        $this->assertEquals('100', $this->chain->wallet->getBalance($this->alice['address']));
        $this->assertEquals('50',  $this->chain->wallet->getBalance($this->bob['address']));
    }

    public function testUnknownAddressReturnsZero(): void
    {
        $this->assertEquals('0', $this->chain->wallet->getBalance('0x' . str_repeat('0', 40)));
    }

    public function testFaucet(): void
    {
        $this->chain->faucet($this->alice['address'], 25.0);
        $this->assertEquals('125', $this->chain->wallet->getBalance($this->alice['address']));
    }

    public function testNonceStartsAtZero(): void
    {
        $this->assertEquals(0, $this->chain->wallet->getNonce($this->alice['address']));
    }

    public function testNonceIncrementsAfterTx(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->assertEquals(1, $this->chain->wallet->getNonce($this->alice['address']));
    }

    public function testBalanceOfShortcut(): void
    {
        $this->assertEquals(
            $this->chain->wallet->getBalance($this->alice['address']),
            $this->chain->balanceOf($this->alice['address'])
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 2 — TRANSFERÊNCIAS NATIVAS
// ─────────────────────────────────────────────────────────────────────────────

class TransferTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;
    private array $carol;
    private int $snapshot;

    protected function setUp(): void
    {
        $this->chain   = new FakeChain(['auto_mine' => true]);
        $this->alice   = $this->chain->createWallet('Alice', 100.0);
        $this->bob     = $this->chain->createWallet('Bob',   50.0);
        $this->carol   = $this->chain->createWallet('Carol', 0.0);
        $this->snapshot = $this->chain->snapshot('setUp');
    }

    protected function tearDown(): void
    {
        $this->chain->rollback($this->snapshot);
    }

    public function testTransferReturnsTxHash(): void
    {
        $txHash = $this->chain->sendTransfer(
            $this->alice['address'], $this->bob['address'], 10.0, $this->alice['private_key']
        );
        $this->assertNotEmpty($txHash);
        $this->assertStringStartsWith('0x', $txHash);
    }

    public function testTransferDebitsSender(): void
    {
        $before = (float)$this->chain->wallet->getBalance($this->alice['address']);
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 10.0, $this->alice['private_key']);
        $after = (float)$this->chain->wallet->getBalance($this->alice['address']);
        $this->assertLessThan($before, $after);       // pagou valor + gas
        $this->assertGreaterThan($before - 11, $after); // não debitou mais do que valor + 1 ETH de gas
    }

    public function testTransferCreditRecipient(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->carol['address'], 10.0, $this->alice['private_key']);
        $this->assertEquals('10', $this->chain->wallet->getBalance($this->carol['address']));
    }

    public function testInsufficientBalanceThrows(): void
    {
        $this->expectException(TransferException::class);
        $this->chain->sendTransfer($this->carol['address'], $this->alice['address'], 9999.0, $this->carol['private_key']);
    }

    public function testWrongPrivateKeyThrows(): void
    {
        $this->expectException(TransferException::class);
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->bob['private_key']);
    }

    public function testTransactionIsMinedAndIndexed(): void
    {
        $txHash = $this->chain->sendTransfer(
            $this->alice['address'], $this->bob['address'], 5.0, $this->alice['private_key']
        );
        $tx = $this->chain->getTransaction($txHash);
        $this->assertEquals('success', $tx['status']);
        $this->assertNotNull($tx['block']);
    }

    public function testTxAppearsInHistory(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 5.0, $this->alice['private_key']);
        $history = $this->chain->wallet->getTransactionHistory($this->alice['address']);
        $this->assertNotEmpty($history);
    }

    public function testBuildAndSignSeparately(): void
    {
        $unsigned = $this->chain->transfer->buildNativeTransfer(
            $this->alice['address'], $this->bob['address'], 2.0
        );
        $this->assertArrayHasKey('from',     $unsigned);
        $this->assertArrayHasKey('to',       $unsigned);
        $this->assertArrayHasKey('nonce',    $unsigned);
        $this->assertArrayHasKey('gas',      $unsigned);
        $this->assertArrayHasKey('gasPrice', $unsigned);
        $this->assertArrayHasKey('chainId',  $unsigned);

        $txHash = $this->chain->transfer->signAndSend($this->alice['private_key'], $unsigned);
        $this->assertStringStartsWith('0x', $txHash);
    }

    public function testMultipleTransfers(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        }
        $this->assertEquals(5, $this->chain->wallet->getNonce($this->alice['address']));
        $this->assertGreaterThan(0, (float)$this->chain->wallet->getBalance($this->alice['address']));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 3 — TOKENS ERC-20
// ─────────────────────────────────────────────────────────────────────────────

class TokenTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;
    private string $usdtAddr;
    private int $snapshot;

    protected function setUp(): void
    {
        $this->chain    = new FakeChain(['auto_mine' => true]);
        $this->alice    = $this->chain->createWallet('Alice', 100.0);
        $this->bob      = $this->chain->createWallet('Bob',   100.0);
        $this->usdtAddr = $this->chain->deployERC20('Fake USDT', 'USDT', 6, 1_000_000.0, $this->alice['address']);
        $this->snapshot = $this->chain->snapshot('setUp');
    }

    protected function tearDown(): void
    {
        $this->chain->rollback($this->snapshot);
    }

    public function testDeployMintsTotalSupplyToOwner(): void
    {
        $bal = $this->chain->wallet->getTokenBalance($this->alice['address'], $this->usdtAddr, 6);
        $this->assertEquals('1000000', $bal);
    }

    public function testTokenTransferDebitsSender(): void
    {
        $this->chain->sendTokenTransfer($this->alice['address'], $this->usdtAddr, $this->bob['address'], 250.0, $this->alice['private_key'], 6);
        $this->assertEquals('999750', $this->chain->wallet->getTokenBalance($this->alice['address'], $this->usdtAddr, 6));
    }

    public function testTokenTransferCreditsRecipient(): void
    {
        $this->chain->sendTokenTransfer($this->alice['address'], $this->usdtAddr, $this->bob['address'], 250.0, $this->alice['private_key'], 6);
        $this->assertEquals('250', $this->chain->wallet->getTokenBalance($this->bob['address'], $this->usdtAddr, 6));
    }

    public function testInsufficientTokenBalanceThrows(): void
    {
        $this->expectException(TransferException::class);
        $this->chain->sendTokenTransfer($this->bob['address'], $this->usdtAddr, $this->alice['address'], 9999.0, $this->bob['private_key'], 6);
    }

    public function testErc20InfoViaCOntract(): void
    {
        $info = $this->chain->contract($this->usdtAddr)->erc20Info($this->alice['address']);
        $this->assertEquals('Fake USDT', $info['name']);
        $this->assertEquals('USDT',      $info['symbol']);
        $this->assertEquals(6,           $info['decimals']);
        $this->assertEquals('1000000',   $info['total_supply']);
        $this->assertEquals('1000000',   $info['balance']);
    }

    public function testContractCallName(): void
    {
        $name = $this->chain->contract($this->usdtAddr)->call('name()');
        $this->assertEquals('Fake USDT', $name);
    }

    public function testContractCallDecimals(): void
    {
        $dec = $this->chain->contract($this->usdtAddr)->callUint256('decimals()');
        $this->assertEquals('6', $dec);
    }

    public function testAllowance(): void
    {
        $this->chain->ledger->setAllowance($this->usdtAddr, $this->alice['address'], $this->bob['address'], 500.0);
        $allowance = $this->chain->wallet->getAllowance($this->usdtAddr, $this->alice['address'], $this->bob['address']);
        $this->assertEquals('500', $allowance);
    }

    public function testTokenPortfolio(): void
    {
        $portfolio = $this->chain->wallet->getTokenPortfolio($this->alice['address']);
        $this->assertNotEmpty($portfolio);
        $this->assertEquals('USDT', $portfolio[0]['symbol']);
    }

    public function testTokenTransferHistory(): void
    {
        $this->chain->sendTokenTransfer($this->alice['address'], $this->usdtAddr, $this->bob['address'], 100.0, $this->alice['private_key'], 6);
        $transfers = $this->chain->wallet->getTokenTransfers($this->alice['address']);
        $this->assertNotEmpty($transfers);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 4 — NFT ERC-721
// ─────────────────────────────────────────────────────────────────────────────

class NFTTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;
    private string $nftAddr;

    protected function setUp(): void
    {
        $this->chain   = new FakeChain(['auto_mine' => true]);
        $this->alice   = $this->chain->createWallet('Alice', 100.0);
        $this->bob     = $this->chain->createWallet('Bob',   100.0);
        $this->nftAddr = $this->chain->deployERC721('FakeApe', 'FAPE', $this->alice['address']);
    }

    public function testMintReturnsTokenId(): void
    {
        $id = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $this->assertEquals(1, $id);
    }

    public function testMintIncrementsTokenId(): void
    {
        $id1 = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $id2 = $this->chain->mintNFT($this->nftAddr, $this->bob['address']);
        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
    }

    public function testOwnerOf(): void
    {
        $id = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $owner = $this->chain->contract($this->nftAddr)->erc721OwnerOf($id);
        $this->assertEquals(strtolower($this->alice['address']), strtolower($owner));
    }

    public function testTokenURIDefault(): void
    {
        $id  = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $uri = $this->chain->contract($this->nftAddr)->erc721TokenURI($id);
        $this->assertStringContainsString((string)$id, $uri);
    }

    public function testCustomTokenURI(): void
    {
        $customURI = 'https://meu-nft.com/metadata/1.json';
        $id  = $this->chain->mintNFT($this->nftAddr, $this->alice['address'], $customURI);
        $uri = $this->chain->contract($this->nftAddr)->erc721TokenURI($id);
        $this->assertEquals($customURI, $uri);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 5 — BLOCOS
// ─────────────────────────────────────────────────────────────────────────────

class BlockTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;

    protected function setUp(): void
    {
        $this->chain = new FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   50.0);
    }

    public function testGenesisBlockExists(): void
    {
        $genesis = $this->chain->block->getBlock(0);
        $this->assertEquals(0, $genesis['number']);
        $this->assertEquals(0, $genesis['tx_count']);
    }

    public function testLatestBlockNumberStartsAtZero(): void
    {
        $fresh = new FakeChain(['auto_mine' => false]);
        $this->assertEquals(0, $fresh->latestBlock());
    }

    public function testLatestBlockIncrements(): void
    {
        $before = $this->chain->latestBlock();
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $after = $this->chain->latestBlock();
        $this->assertEquals($before + 1, $after);
    }

    public function testGetBlockByNumber(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $block = $this->chain->block->getBlock(1);
        $this->assertEquals(1, $block['number']);
    }

    public function testGetBlockLatest(): void
    {
        $block = $this->chain->block->getBlock('latest');
        $this->assertArrayHasKey('hash',      $block);
        $this->assertArrayHasKey('timestamp', $block);
        $this->assertArrayHasKey('tx_count',  $block);
    }

    public function testGetBlockNotFoundThrows(): void
    {
        $this->expectException(BlockException::class);
        $this->chain->block->getBlock(99999);
    }

    public function testBlockHasHash(): void
    {
        $block = $this->chain->block->getBlock(0);
        $this->assertStringStartsWith('0x', $block['hash']);
        $this->assertEquals(66, strlen($block['hash']));
    }

    public function testBlockHasMerkleRoot(): void
    {
        $block = $this->chain->block->getBlock(0);
        $this->assertArrayHasKey('merkle_root', $block);
        $this->assertStringStartsWith('0x', $block['merkle_root']);
    }

    public function testBlockCountsTxs(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $latest = $this->chain->block->getBlock('latest');
        $this->assertGreaterThanOrEqual(1, $latest['tx_count']);
    }

    public function testGetBlockRange(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $range = $this->chain->block->getBlockRange(0, 2);
        $this->assertCount(3, $range);
    }

    public function testGasInfo(): void
    {
        $gas = $this->chain->block->getGasInfo();
        $this->assertArrayHasKey('gas_price_wei',  $gas);
        $this->assertArrayHasKey('gas_price_gwei', $gas);
        $this->assertArrayHasKey('base_fee_gwei',  $gas);
    }

    public function testEstimateGas(): void
    {
        $est = $this->chain->block->estimateGas(['from' => $this->alice['address'], 'to' => $this->bob['address'], 'data' => '0x']);
        $this->assertEquals('21000', $est);
    }

    public function testEstimateGasHigherForContract(): void
    {
        $est = $this->chain->block->estimateGas(['from' => $this->alice['address'], 'to' => '0xcontract', 'data' => '0xa9059cbb']);
        $this->assertGreaterThan(21000, (int)$est);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 6 — TRANSAÇÕES
// ─────────────────────────────────────────────────────────────────────────────

class TransactionTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;

    protected function setUp(): void
    {
        $this->chain = new FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   50.0);
    }

    public function testGetTransactionByHash(): void
    {
        $txHash = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 5.0, $this->alice['private_key']);
        $tx = $this->chain->getTransaction($txHash);
        $this->assertEquals($txHash, $tx['hash']);
        $this->assertEquals(strtolower($this->alice['address']), strtolower($tx['from']));
        $this->assertEquals(strtolower($this->bob['address']),   strtolower($tx['to']));
    }

    public function testNotFoundThrows(): void
    {
        $this->expectException(BlockException::class);
        $this->chain->getTransaction('0x' . str_repeat('f', 64));
    }

    public function testTxStatusSuccessAfterMine(): void
    {
        $txHash = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $tx = $this->chain->getTransaction($txHash);
        $this->assertEquals('success', $tx['status']);
    }

    public function testTxIsPendingBeforeMine(): void
    {
        $this->chain->autoMine = false;
        $txHash = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $tx = $this->chain->getTransaction($txHash);
        $this->assertEquals('pending', $tx['status']);
        $this->chain->autoMine = true;
    }

    public function testWaitForConfirmation(): void
    {
        $this->chain->autoMine = false;
        $txHash = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $receipt = $this->chain->transfer->waitForConfirmation($txHash, 5, 0);
        $this->assertEquals('success', $receipt['status']);
        $this->chain->autoMine = true;
    }

    public function testManualMine(): void
    {
        $this->chain->autoMine = false;
        $before = $this->chain->latestBlock();
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->chain->mineBlock();
        $after = $this->chain->latestBlock();
        $this->assertEquals($before + 1, $after);
        $this->chain->autoMine = true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 7 — SNAPSHOT E ROLLBACK
// ─────────────────────────────────────────────────────────────────────────────

class SnapshotTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;
    private array $bob;

    protected function setUp(): void
    {
        $this->chain = new FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   50.0);
    }

    public function testSnapshotReturnsId(): void
    {
        $id = $this->chain->snapshot('test');
        $this->assertIsInt($id);
        $this->assertEquals(0, $id);
    }

    public function testRollbackRestoresBalance(): void
    {
        $snap = $this->chain->snapshot('before');
        $this->chain->faucet($this->alice['address'], 99999.0);
        $this->chain->rollback($snap);
        $this->assertEquals('100', $this->chain->wallet->getBalance($this->alice['address']));
    }

    public function testRollbackRestoresAfterTx(): void
    {
        $snap = $this->chain->snapshot('before');
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 50.0, $this->alice['private_key']);
        $this->chain->rollback($snap);
        $bal = (float)$this->chain->wallet->getBalance($this->alice['address']);
        $this->assertEquals(100.0, $bal);
    }

    public function testRollbackRestoresBlocks(): void
    {
        $snap = $this->chain->snapshot('before');
        $blocksBefore = $this->chain->latestBlock();
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->chain->rollback($snap);
        $this->assertEquals($blocksBefore, $this->chain->latestBlock());
    }

    public function testMultipleSnapshots(): void
    {
        $snap1 = $this->chain->snapshot('snap1');
        $this->chain->faucet($this->alice['address'], 100.0);

        $snap2 = $this->chain->snapshot('snap2');
        $this->chain->faucet($this->alice['address'], 100.0);

        $this->assertEquals('300', $this->chain->wallet->getBalance($this->alice['address']));

        $this->chain->rollback($snap2);
        $this->assertEquals('200', $this->chain->wallet->getBalance($this->alice['address']));

        $this->chain->rollback($snap1);
        $this->assertEquals('100', $this->chain->wallet->getBalance($this->alice['address']));
    }

    public function testInvalidSnapshotThrows(): void
    {
        $this->expectException(\FakeChain\Web3Exception::class);
        $this->chain->rollback(9999);
    }

    public function testListSnapshots(): void
    {
        $this->chain->snapshot('snap_a');
        $this->chain->snapshot('snap_b');
        $list = $this->chain->listSnapshots();
        $this->assertCount(2, $list);
        $this->assertEquals('snap_a', $list[0]['label']);
        $this->assertEquals('snap_b', $list[1]['label']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 8 — REDE E STATS
// ─────────────────────────────────────────────────────────────────────────────

class NetworkTest extends TestCase
{
    private FakeChain $chain;

    protected function setUp(): void
    {
        $this->chain = new FakeChain(['chain_id' => 1337, 'network' => 'fakechain', 'symbol' => 'ETH']);
    }

    public function testChainId(): void
    {
        $this->assertEquals(1337, $this->chain->network->getChainId());
    }

    public function testNodeInfo(): void
    {
        $info = $this->chain->network->getNodeInfo();
        $this->assertArrayHasKey('version',    $info);
        $this->assertArrayHasKey('chain_id',   $info);
        $this->assertArrayHasKey('network',    $info);
        $this->assertArrayHasKey('rpc_url',    $info);
        $this->assertEquals(1337,          $info['chain_id']);
        $this->assertEquals('fakechain',   $info['network']);
        $this->assertEquals('local_simulation', $info['mode']);
    }

    public function testMempoolSizeIsZeroAfterMine(): void
    {
        $alice = $this->chain->createWallet('A', 100.0);
        $bob   = $this->chain->createWallet('B', 10.0);
        $this->chain->autoMine = true;
        $this->chain->sendTransfer($alice['address'], $bob['address'], 1.0, $alice['private_key']);
        $this->assertEquals(0, $this->chain->network->getMempoolSize());
    }

    public function testMempoolSizeIncreasesBeforeMine(): void
    {
        $alice = $this->chain->createWallet('A', 100.0);
        $bob   = $this->chain->createWallet('B', 10.0);
        $this->chain->autoMine = false;
        $this->chain->sendTransfer($alice['address'], $bob['address'], 1.0, $alice['private_key']);
        $this->assertEquals(1, $this->chain->network->getMempoolSize());
        $this->chain->autoMine = true;
    }

    public function testLibraryInfo(): void
    {
        $info = $this->chain->info();
        $this->assertEquals('FakeChain', $info['library']);
        $this->assertEquals('1.0.0',     $info['version']);
        $this->assertTrue($info['is_evm']);
    }

    public function testRpcEthBlockNumber(): void
    {
        $result = $this->chain->rpc('eth_blockNumber');
        $this->assertStringStartsWith('0x', $result);
    }

    public function testRpcEthChainId(): void
    {
        $result = $this->chain->rpc('eth_chainId');
        $this->assertEquals('0x' . dechex(1337), $result);
    }

    public function testSwitchNetwork(): void
    {
        $polygon = $this->chain->switchNetwork('polygon');
        $this->assertEquals('polygon', $polygon->info()['network']);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 9 — SMART CONTRACTS GENÉRICOS
// ─────────────────────────────────────────────────────────────────────────────

class ContractTest extends TestCase
{
    private FakeChain $chain;
    private array $alice;

    protected function setUp(): void
    {
        $this->chain = new FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
    }

    public function testDeployContractReturnsAddress(): void
    {
        $addr = $this->chain->deployContract(['counter' => 0]);
        $this->assertStringStartsWith('0x', $addr);
        $this->assertEquals(42, strlen($addr));
    }

    public function testReadContractState(): void
    {
        $addr = $this->chain->deployContract(['title' => 'Hello World', 'value' => 42]);
        $contract = $this->chain->contract($addr);
        $this->assertEquals('Hello World', $contract->call('title'));
        $this->assertEquals('42',          $contract->call('value'));
    }

    public function testSetAndReadState(): void
    {
        $addr  = $this->chain->deployContract(['count' => 0]);
        $state = $this->chain->ledger->getContractState($addr);
        $state['count'] = 5;
        $this->chain->ledger->setContractState($addr, $state);

        $contract = $this->chain->contract($addr);
        $this->assertEquals('5', $contract->call('count'));
    }

    public function testContractExistence(): void
    {
        $addr = $this->chain->deployContract([]);
        $this->assertTrue($this->chain->ledger->contractExists($addr));
        $this->assertFalse($this->chain->ledger->contractExists('0x' . str_repeat('0', 40)));
    }

    public function testBuildTransactionStructure(): void
    {
        $addr = $this->chain->deployContract(['x' => 0]);
        $tx   = $this->chain->contract($addr)->buildTransaction(
            $this->alice['address'], 'setValue(uint256)', ['uint256'], [42]
        );
        $this->assertArrayHasKey('from',    $tx);
        $this->assertArrayHasKey('to',      $tx);
        $this->assertArrayHasKey('nonce',   $tx);
        $this->assertArrayHasKey('gas',     $tx);
        $this->assertArrayHasKey('chainId', $tx);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 10 — PERSISTÊNCIA
// ─────────────────────────────────────────────────────────────────────────────

class PersistenceTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/fakechain_test_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testSaveAndLoad(): void
    {
        // Criar chain com persistência e adicionar dados
        $chain1 = new FakeChain(['storage_path' => $this->tmpFile, 'auto_mine' => true]);
        $alice  = $chain1->createWallet('Alice', 100.0);
        $bob    = $chain1->createWallet('Bob',   50.0);
        $chain1->sendTransfer($alice['address'], $bob['address'], 10.0, $alice['private_key']);
        // save() é chamado automaticamente após mine()

        // Reabrir a mesma chain
        $chain2 = new FakeChain(['storage_path' => $this->tmpFile, 'auto_mine' => true]);

        // Verificar que os saldos foram persistidos
        $this->assertLessThan(100.0, (float)$chain2->wallet->getBalance($alice['address']));
        $this->assertEquals('60', $chain2->wallet->getBalance($bob['address']));
        $this->assertGreaterThan(0, $chain2->latestBlock());
    }

    public function testPersistenceFileIsJson(): void
    {
        $chain = new FakeChain(['storage_path' => $this->tmpFile, 'auto_mine' => true]);
        $alice = $chain->createWallet('Alice', 100.0);
        $chain->mineBlock(); // forçar save

        $this->assertFileExists($this->tmpFile);
        $data = json_decode(file_get_contents($this->tmpFile), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('balances', $data);
        $this->assertArrayHasKey('blocks',   $data);
    }
}
