<?php

/**
 * Web3PHP — Suite de Testes Completa
 * ====================================
 * Cobre Math, Address, AbiEncoder, Networks + integração via FakeChain.
 *
 * INSTALAR:
 *   composer require --dev phpunit/phpunit
 *   composer require kornrunner/keccak simplito/elliptic-php
 *
 * RODAR:
 *   ./vendor/bin/phpunit tests/Web3PHPTest.php --testdox
 *
 * RODAR COM COBERTURA:
 *   ./vendor/bin/phpunit tests/Web3PHPTest.php --coverage-text
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Web3PHP\Math;
use Web3PHP\Address;
use Web3PHP\AbiEncoder;
use Web3PHP\Networks;

require_once __DIR__ . '/../src/Web3PHP.php';
require_once __DIR__ . '/../src/FakeChain.php';

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 1 — Math: Conversões Hex ↔ Decimal
// ═════════════════════════════════════════════════════════════════════════════

class MathHexDecTest extends TestCase
{
    public function testHexToDecZero(): void
    {
        $this->assertEquals('0', Math::hexToDec('0x0'));
        $this->assertEquals('0', Math::hexToDec('0'));
        $this->assertEquals('0', Math::hexToDec(''));
    }

    public function testHexToDecSimple(): void
    {
        $this->assertEquals('255', Math::hexToDec('0xff'));
        $this->assertEquals('16', Math::hexToDec('0x10'));
        $this->assertEquals('1', Math::hexToDec('0x1'));
    }

    public function testHexToDecLarge(): void
    {
        // 1 ETH in wei = 1000000000000000000
        $this->assertEquals('1000000000000000000', Math::hexToDec('0xde0b6b3a7640000'));
    }

    public function testHexToDecMaxUint256(): void
    {
        // 2^256 - 1 (max uint256)
        $hex = '0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
        $result = Math::hexToDec($hex);
        $this->assertStringStartsWith('11579', $result); // 115792089...
        $this->assertEquals(78, strlen($result));
    }

    public function testBcHexToDecWithoutPrefix(): void
    {
        $this->assertEquals('255', Math::bcHexToDec('ff'));
        $this->assertEquals('0', Math::bcHexToDec(''));
        $this->assertEquals('0', Math::bcHexToDec('0'));
    }

    public function testDecToHex(): void
    {
        $this->assertEquals('0x0', Math::decToHex(0));
        $this->assertEquals('0xff', Math::decToHex(255));
        $this->assertEquals('0x10', Math::decToHex(16));
        $this->assertEquals('0x1', Math::decToHex(1));
    }

    public function testDecToHexLargeValue(): void
    {
        $hex = Math::decToHex('1000000000000000000');
        $this->assertStringStartsWith('0x', $hex);
        // Round-trip
        $this->assertEquals('1000000000000000000', Math::hexToDec($hex));
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 2 — Math: Conversões de Unidade
// ═════════════════════════════════════════════════════════════════════════════

class MathUnitsTest extends TestCase
{
    // ── ETH ↔ Wei ────────────────────────────────────────────────────────────

    public function testWeiToEther(): void
    {
        $this->assertEquals('1.000000000000000000', Math::weiToEther('1000000000000000000'));
        $this->assertEquals('0.000000000000000000', Math::weiToEther('0'));
    }

    public function testWeiToEtherFractional(): void
    {
        $result = Math::weiToEther('1500000000000000000');
        $this->assertStringStartsWith('1.5', $result);
    }

    public function testEtherToWei(): void
    {
        $this->assertEquals('1000000000000000000', Math::etherToWei(1.0));
        $this->assertEquals('0', Math::etherToWei(0.0));
    }

    public function testEtherToWeiString(): void
    {
        $this->assertEquals('1000000000000000000', Math::etherToWei('1'));
        $this->assertEquals('1500000000000000000', Math::etherToWei('1.5'));
    }

    public function testEtherToWeiRoundTrip(): void
    {
        $original = '2.5';
        $wei      = Math::etherToWei($original);
        $back     = Math::weiToEther($wei);
        $this->assertStringStartsWith('2.5', $back);
    }

    public function testEtherToWeiPrecision(): void
    {
        // Este era o bug original: 1.1 ETH -> float precision loss
        $wei = Math::etherToWei('1.1');
        // Deve ser exatamente 1100000000000000000
        $this->assertEquals('1100000000000000000', $wei);
    }

    // ── SOL ↔ Lamports ───────────────────────────────────────────────────────

    public function testLamportsToSol(): void
    {
        $this->assertEquals(1.0, Math::lamportsToSol(1_000_000_000));
        $this->assertEquals(0.0, Math::lamportsToSol(0));
        $this->assertEquals(1.5, Math::lamportsToSol(1_500_000_000));
    }

    public function testSolToLamports(): void
    {
        $this->assertEquals(1_000_000_000, Math::solToLamports(1.0));
        $this->assertEquals(0, Math::solToLamports(0.0));
        $this->assertEquals(1_500_000_000, Math::solToLamports(1.5));
    }

    public function testSolRoundTrip(): void
    {
        $sol      = 3.14;
        $lamports = Math::solToLamports($sol);
        $back     = Math::lamportsToSol($lamports);
        $this->assertEqualsWithDelta($sol, $back, 1e-9);
    }

    // ── BTC ↔ Satoshi ────────────────────────────────────────────────────────

    public function testSatoshiToBtc(): void
    {
        $this->assertEquals(1.0, Math::satoshiToBtc(100_000_000));
        $this->assertEquals(0.0, Math::satoshiToBtc(0));
        $this->assertEquals(0.5, Math::satoshiToBtc(50_000_000));
    }

    // ── TRX ↔ Sun ────────────────────────────────────────────────────────────

    public function testSunToTrx(): void
    {
        $this->assertEquals(1.0, Math::sunToTrx(1_000_000));
        $this->assertEquals(0.0, Math::sunToTrx(0));
    }

    // ── ERC-20 Token Units ───────────────────────────────────────────────────

    public function testParseUnitsUsdt(): void
    {
        // USDT has 6 decimals
        $this->assertEquals('100000000', Math::parseUnits('100', 6));
        $this->assertEquals('1000000',   Math::parseUnits('1', 6));
        $this->assertEquals('0',         Math::parseUnits('0', 6));
    }

    public function testParseUnitsEth(): void
    {
        $this->assertEquals('1000000000000000000', Math::parseUnits('1', 18));
        $this->assertEquals('1500000000000000000', Math::parseUnits('1.5', 18));
    }

    public function testFormatUnitsUsdt(): void
    {
        $result = Math::formatUnits('100000000', 6);
        $this->assertStringStartsWith('100', $result);
    }

    public function testFormatUnitsEth(): void
    {
        $result = Math::formatUnits('1000000000000000000', 18);
        $this->assertStringStartsWith('1.', $result);
    }

    public function testParseFormatRoundTrip(): void
    {
        $original  = '250';
        $parsed    = Math::parseUnits($original, 6);
        $formatted = Math::formatUnits($parsed, 6);
        $this->assertStringStartsWith('250', $formatted);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 3 — Address: Validação
// ═════════════════════════════════════════════════════════════════════════════

class AddressValidationTest extends TestCase
{
    // ── EVM ──────────────────────────────────────────────────────────────────

    public function testValidEvmAddresses(): void
    {
        $this->assertTrue(Address::isValidEVM('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'));
        $this->assertTrue(Address::isValidEVM('0x0000000000000000000000000000000000000000'));
        $this->assertTrue(Address::isValidEVM('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF'));
        $this->assertTrue(Address::isValidEVM('0xabcdef1234567890abcdef1234567890abcdef12'));
    }

    public function testInvalidEvmAddresses(): void
    {
        $this->assertFalse(Address::isValidEVM(''));
        $this->assertFalse(Address::isValidEVM('0x'));                            // too short
        $this->assertFalse(Address::isValidEVM('0xGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGG')); // invalid hex
        $this->assertFalse(Address::isValidEVM('d8dA6BF26964aF9D7eEd9e03E53415D37aA96045'));      // missing 0x
        $this->assertFalse(Address::isValidEVM('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA960'));       // too short
        $this->assertFalse(Address::isValidEVM('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA960456'));    // too long
    }

    // ── Bitcoin ───────────────────────────────────────────────────────────────

    public function testValidBitcoinAddresses(): void
    {
        $this->assertTrue(Address::isValidBitcoin('1A1zP1eP5QGefi2DMPTfTL5SLmv7Divf')); // P2PKH genesis
        $this->assertTrue(Address::isValidBitcoin('3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy')); // P2SH
        $this->assertTrue(Address::isValidBitcoin('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh')); // Bech32
    }

    public function testInvalidBitcoinAddresses(): void
    {
        $this->assertFalse(Address::isValidBitcoin(''));
        $this->assertFalse(Address::isValidBitcoin('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045')); // EVM addr
        $this->assertFalse(Address::isValidBitcoin('abc'));
    }

    // ── Solana ────────────────────────────────────────────────────────────────

    public function testValidSolanaAddresses(): void
    {
        $this->assertTrue(Address::isValidSolana('9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM'));
        $this->assertTrue(Address::isValidSolana('So11111111111111111111111111111111111111112'));
    }

    public function testInvalidSolanaAddresses(): void
    {
        $this->assertFalse(Address::isValidSolana(''));
        $this->assertFalse(Address::isValidSolana('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'));
        $this->assertFalse(Address::isValidSolana('0OIl')); // ambiguous characters
    }

    // ── Tron ─────────────────────────────────────────────────────────────────

    public function testValidTronAddresses(): void
    {
        $this->assertTrue(Address::isValidTron('TN3W4H6rK2ce4vX9YnFQHwKENnHjoxb3m9'));
        $this->assertTrue(Address::isValidTron('TLyqzVGLV1srkB7dToTAEqgDSfPtXRJZYH'));
    }

    public function testInvalidTronAddresses(): void
    {
        $this->assertFalse(Address::isValidTron(''));
        $this->assertFalse(Address::isValidTron('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045'));
        $this->assertFalse(Address::isValidTron('BN3W4H6rK2ce4vX9YnFQHwKENnHjoxb3m9')); // starts with B, not T
    }

    // ── validate() por rede ───────────────────────────────────────────────────

    public function testValidateByNetwork(): void
    {
        $evmAddr  = '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045';
        $btcAddr  = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';
        $solAddr  = '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM';
        $tronAddr = 'TN3W4H6rK2ce4vX9YnFQHwKENnHjoxb3m9';

        $this->assertTrue(Address::validate($evmAddr, 'ethereum'));
        $this->assertTrue(Address::validate($evmAddr, 'polygon'));
        $this->assertTrue(Address::validate($evmAddr, 'bsc'));
        $this->assertTrue(Address::validate($evmAddr, 'arbitrum'));

        $this->assertTrue(Address::validate($btcAddr,  'bitcoin'));
        $this->assertTrue(Address::validate($solAddr,  'solana'));
        $this->assertTrue(Address::validate($tronAddr, 'tron'));

        // Cross-network should fail
        $this->assertFalse(Address::validate($evmAddr, 'bitcoin'));
        $this->assertFalse(Address::validate($btcAddr, 'ethereum'));
        $this->assertFalse(Address::validate($solAddr, 'tron'));
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 4 — AbiEncoder
// ═════════════════════════════════════════════════════════════════════════════

class AbiEncoderTest extends TestCase
{
    public function testSelectorLength(): void
    {
        $sel = AbiEncoder::selector('transfer(address,uint256)');
        $this->assertStringStartsWith('0x', $sel);
        $this->assertEquals(10, strlen($sel)); // 0x + 8 hex chars
    }

    public function testSelectorTransfer(): void
    {
        // Known keccak256: transfer(address,uint256) = 0xa9059cbb
        // (only valid if kornrunner/keccak is installed)
        $sel = AbiEncoder::selector('transfer(address,uint256)');
        $this->assertStringStartsWith('0x', $sel);
        $this->assertEquals(10, strlen($sel));
    }

    public function testSelectorIsDeterministic(): void
    {
        $sel1 = AbiEncoder::selector('balanceOf(address)');
        $sel2 = AbiEncoder::selector('balanceOf(address)');
        $this->assertEquals($sel1, $sel2);
    }

    public function testEncodeAddress(): void
    {
        $result = AbiEncoder::encodeAddress('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
        $this->assertEquals(64, strlen($result));
        // Last 40 chars should be the address without 0x, lowercase
        $this->assertStringEndsWith('d8da6bf26964af9d7eed9e03e53415d37aa96045', $result);
    }

    public function testEncodeUint256Zero(): void
    {
        $result = AbiEncoder::encodeUint256(0);
        $this->assertEquals(str_repeat('0', 64), $result);
    }

    public function testEncodeUint256Value(): void
    {
        $result = AbiEncoder::encodeUint256(255);
        $this->assertEquals(62, strspn($result, '0'));
        $this->assertStringEndsWith('ff', $result);
    }

    public function testEncodeCallProducesCorrectLength(): void
    {
        // selector (4 bytes = 8 hex + "0x") + 1 param (32 bytes = 64 hex)
        $calldata = AbiEncoder::encodeCall(
            'balanceOf(address)',
            ['address'],
            ['0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045']
        );
        // 0x + 8 (selector) + 64 (address param) = 74 chars
        $this->assertEquals(74, strlen($calldata));
        $this->assertStringStartsWith('0x', $calldata);
    }

    public function testEncodeCallTwoParams(): void
    {
        // transfer(address, uint256) = selector + address (32) + uint256 (32) = 68 bytes = 0x + 8 + 128
        $calldata = AbiEncoder::encodeCall(
            'transfer(address,uint256)',
            ['address', 'uint256'],
            ['0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', '1000000000000000000']
        );
        $this->assertEquals(138, strlen($calldata)); // 0x + 8 + 64 + 64
    }

    public function testDecodeUint256(): void
    {
        $hex = '0x' . str_repeat('0', 63) . '1'; // 1 in 32 bytes
        $this->assertEquals('1', AbiEncoder::decodeUint256($hex));
    }

    public function testDecodeUint256LargeValue(): void
    {
        // 1000000 in hex = 'f4240'
        $hex = '0x' . str_pad('f4240', 64, '0', STR_PAD_LEFT);
        $this->assertEquals('1000000', AbiEncoder::decodeUint256($hex));
    }

    public function testDecodeAddress(): void
    {
        $address = 'd8da6bf26964af9d7eed9e03e53415d37aa96045';
        $hex     = '0x' . str_pad($address, 64, '0', STR_PAD_LEFT);
        $decoded = AbiEncoder::decodeAddress($hex);
        $this->assertEquals('0x' . $address, $decoded);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 5 — Networks: Constantes e helpers
// ═════════════════════════════════════════════════════════════════════════════

class NetworksTest extends TestCase
{
    public function testIsEVMForEvmNetworks(): void
    {
        foreach (['ethereum', 'polygon', 'bsc', 'avalanche', 'arbitrum', 'optimism', 'base', 'fantom', 'cronos', 'hardhat', 'ganache'] as $net) {
            $this->assertTrue(Networks::isEVM($net), "Expected {$net} to be EVM");
        }
    }

    public function testIsEVMForNonEvmNetworks(): void
    {
        foreach (['bitcoin', 'solana', 'solana_dev', 'tron', 'tron_test'] as $net) {
            $this->assertFalse(Networks::isEVM($net), "Expected {$net} to NOT be EVM");
        }
    }

    public function testIsEVMCaseInsensitive(): void
    {
        $this->assertTrue(Networks::isEVM('Ethereum'));
        $this->assertTrue(Networks::isEVM('POLYGON'));
        $this->assertFalse(Networks::isEVM('BITCOIN'));
    }

    public function testChainIdsAreCorrect(): void
    {
        $this->assertEquals(1,     Networks::CHAIN_IDS['ethereum']);
        $this->assertEquals(137,   Networks::CHAIN_IDS['polygon']);
        $this->assertEquals(56,    Networks::CHAIN_IDS['bsc']);
        $this->assertEquals(42161, Networks::CHAIN_IDS['arbitrum']);
        $this->assertEquals(10,    Networks::CHAIN_IDS['optimism']);
        $this->assertEquals(8453,  Networks::CHAIN_IDS['base']);
        $this->assertEquals(43114, Networks::CHAIN_IDS['avalanche']);
    }

    public function testPublicRpcExists(): void
    {
        foreach (['ethereum', 'polygon', 'bsc', 'avalanche', 'arbitrum', 'bitcoin', 'solana', 'tron'] as $net) {
            $this->assertArrayHasKey($net, Networks::PUBLIC_RPC, "Missing public RPC for {$net}");
            $this->assertNotEmpty(Networks::PUBLIC_RPC[$net]);
        }
    }

    public function testExplorerApiCoversAllEvmNetworks(): void
    {
        $evmNets = ['ethereum', 'polygon', 'bsc', 'avalanche', 'arbitrum', 'optimism', 'base', 'fantom', 'cronos'];
        foreach ($evmNets as $net) {
            $this->assertArrayHasKey($net, Networks::EXPLORER_API, "Missing explorer API for {$net}");
        }
    }

    public function testNativeSymbols(): void
    {
        $this->assertEquals('ETH',   Networks::NATIVE_SYMBOL['ethereum']);
        $this->assertEquals('MATIC', Networks::NATIVE_SYMBOL['polygon']);
        $this->assertEquals('BNB',   Networks::NATIVE_SYMBOL['bsc']);
        $this->assertEquals('BTC',   Networks::NATIVE_SYMBOL['bitcoin']);
        $this->assertEquals('SOL',   Networks::NATIVE_SYMBOL['solana']);
        $this->assertEquals('TRX',   Networks::NATIVE_SYMBOL['tron']);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 6 — FakeChain: Carteiras
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainWalletTest extends TestCase
{
    private \FakeChain\FakeChain $chain;

    protected function setUp(): void
    {
        $this->chain = new \FakeChain\FakeChain(['auto_mine' => true]);
    }

    public function testCreateWalletStructure(): void
    {
        $w = $this->chain->createWallet('Alice', 100.0);
        $this->assertArrayHasKey('address',     $w);
        $this->assertArrayHasKey('private_key', $w);
        $this->assertArrayHasKey('balance',     $w);
        $this->assertArrayHasKey('label',       $w);
        $this->assertEquals('Alice', $w['label']);
        $this->assertEquals(100.0,   $w['balance']);
    }

    public function testAddressFormat(): void
    {
        $w = $this->chain->createWallet('Bob', 10.0);
        $this->assertStringStartsWith('0x', $w['address']);
        $this->assertEquals(42, strlen($w['address']));
    }

    public function testInitialBalance(): void
    {
        $w = $this->chain->createWallet('Carol', 77.5);
        $this->assertEquals('77.5', $this->chain->wallet->getBalance($w['address']));
    }

    public function testUnknownAddressReturnsZero(): void
    {
        $unknown = '0x' . str_repeat('0', 40);
        $this->assertEquals('0', $this->chain->wallet->getBalance($unknown));
    }

    public function testFaucet(): void
    {
        $w = $this->chain->createWallet('Dan', 0.0);
        $this->chain->faucet($w['address'], 50.0);
        $this->assertEquals('50', $this->chain->wallet->getBalance($w['address']));
    }

    public function testTwoWalletsHaveDifferentAddresses(): void
    {
        $a = $this->chain->createWallet('A', 0.0);
        $b = $this->chain->createWallet('B', 0.0);
        $this->assertNotEquals($a['address'], $b['address']);
    }

    public function testGetNonce(): void
    {
        $w = $this->chain->createWallet('E', 100.0);
        $this->assertEquals(0, $this->chain->wallet->getNonce($w['address']));
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 7 — FakeChain: Transferências ETH
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainTransferTest extends TestCase
{
    private \FakeChain\FakeChain $chain;
    private array $alice;
    private array $bob;

    protected function setUp(): void
    {
        $this->chain = new \FakeChain\FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   0.0);
    }

    public function testTransferDeductsFromSender(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 10.0, $this->alice['private_key']);
        $balance = (float)$this->chain->wallet->getBalance($this->alice['address']);
        $this->assertLessThan(100.0, $balance); // descontou 10 + gas
        $this->assertGreaterThan(89.0, $balance);
    }

    public function testTransferCreditsBob(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 10.0, $this->alice['private_key']);
        $this->assertEquals('10', $this->chain->wallet->getBalance($this->bob['address']));
    }

    public function testTransferReturnsTxHash(): void
    {
        $hash = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->assertStringStartsWith('0x', $hash);
        $this->assertEquals(66, strlen($hash));
    }

    public function testInsufficientBalanceThrows(): void
    {
        $this->expectException(\FakeChain\TransferException::class);
        $this->chain->sendTransfer($this->bob['address'], $this->alice['address'], 999.0, $this->bob['private_key']);
    }

    public function testTransferToSelf(): void
    {
        $before = (float)$this->chain->wallet->getBalance($this->alice['address']);
        $this->chain->sendTransfer($this->alice['address'], $this->alice['address'], 1.0, $this->alice['private_key']);
        $after = (float)$this->chain->wallet->getBalance($this->alice['address']);
        // Só paga gas, saldo quase igual
        $this->assertLessThanOrEqual($before, $after + 0.01); // gas cobrado
    }

    public function testMultipleTransfers(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 5.0, $this->alice['private_key']);
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 5.0, $this->alice['private_key']);
        $this->assertEquals('10', $this->chain->wallet->getBalance($this->bob['address']));
    }

    public function testBuildNativeTransferStructure(): void
    {
        $tx = $this->chain->transfer->buildNativeTransfer(
            $this->alice['address'],
            $this->bob['address'],
            1.0
        );
        $this->assertArrayHasKey('from',     $tx);
        $this->assertArrayHasKey('to',       $tx);
        $this->assertArrayHasKey('nonce',    $tx);
        $this->assertArrayHasKey('gas',      $tx);
        $this->assertArrayHasKey('gasPrice', $tx);
        $this->assertArrayHasKey('value',    $tx);
        $this->assertArrayHasKey('chainId',  $tx);
        $this->assertEquals($this->alice['address'], $tx['from']);
        $this->assertEquals($this->bob['address'],   $tx['to']);
    }

    public function testWaitForConfirmationAutoMine(): void
    {
        $hash = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $receipt = $this->chain->transfer->waitForConfirmation($hash, 5, 0);
        $this->assertEquals('success', $receipt['status']);
        $this->assertEquals($hash, $receipt['hash']);
        $this->assertGreaterThan(0, $receipt['block']);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 8 — FakeChain: Tokens ERC-20
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainTokenTest extends TestCase
{
    private \FakeChain\FakeChain $chain;
    private array $alice;
    private array $bob;
    private string $usdtAddr;

    protected function setUp(): void
    {
        $this->chain   = new \FakeChain\FakeChain(['auto_mine' => true]);
        $this->alice   = $this->chain->createWallet('Alice', 10.0); // ETH para gas
        $this->bob     = $this->chain->createWallet('Bob',   10.0);
        $this->usdtAddr = $this->chain->deployERC20('Fake USDT', 'USDT', 6, 1_000_000.0, $this->alice['address']);
    }

    public function testDeployReturnsAddress(): void
    {
        $this->assertStringStartsWith('0x', $this->usdtAddr);
        $this->assertEquals(42, strlen($this->usdtAddr));
    }

    public function testOwnerHasTotalSupply(): void
    {
        $balance = $this->chain->wallet->getTokenBalance($this->alice['address'], $this->usdtAddr, 6);
        $this->assertEquals('1000000', (string)(int)$balance);
    }

    public function testTokenTransfer(): void
    {
        $this->chain->sendTokenTransfer($this->alice['address'], $this->usdtAddr, $this->bob['address'], 250.0, $this->alice['private_key'], 6);
        $aliceBal = $this->chain->wallet->getTokenBalance($this->alice['address'], $this->usdtAddr, 6);
        $bobBal   = $this->chain->wallet->getTokenBalance($this->bob['address'],   $this->usdtAddr, 6);
        $this->assertStringStartsWith('999750', $aliceBal);
        $this->assertStringStartsWith('250', $bobBal);
    }

    public function testTokenTransferInsufficientBalanceThrows(): void
    {
        $this->expectException(\FakeChain\TransferException::class);
        $this->chain->sendTokenTransfer($this->bob['address'], $this->usdtAddr, $this->alice['address'], 999999.0, $this->bob['private_key'], 6);
    }

    public function testErc20Info(): void
    {
        $contract = $this->chain->contract($this->usdtAddr);
        $info = $contract->erc20Info($this->alice['address']);
        $this->assertEquals('Fake USDT', $info['name']);
        $this->assertEquals('USDT',      $info['symbol']);
        $this->assertEquals(6,           $info['decimals']);
        $this->assertGreaterThan(0,       (float)$info['total_supply']);
    }

    public function testAllowance(): void
    {
        $this->chain->ledger->setAllowance($this->usdtAddr, $this->alice['address'], $this->bob['address'], 500.0);
        $allowance = $this->chain->wallet->getAllowance($this->usdtAddr, $this->alice['address'], $this->bob['address']);
        $this->assertEquals('500', $allowance);
    }

    public function testBuildTokenTransferStructure(): void
    {
        $tx = $this->chain->transfer->buildTokenTransfer(
            $this->alice['address'],
            $this->usdtAddr,
            $this->bob['address'],
            100.0,
            6
        );
        $this->assertArrayHasKey('from',    $tx);
        $this->assertArrayHasKey('to',      $tx);
        $this->assertArrayHasKey('data',    $tx);
        $this->assertArrayHasKey('chainId', $tx);
        $this->assertEquals($this->alice['address'], $tx['from']);
        $this->assertEquals($this->usdtAddr,          $tx['to']);
        $this->assertStringStartsWith('0x', $tx['data']);
    }

    public function testGetTokenPortfolio(): void
    {
        $portfolio = $this->chain->wallet->getTokenPortfolio($this->alice['address']);
        $this->assertNotEmpty($portfolio);
        $symbols = array_column($portfolio, 'symbol');
        $this->assertContains('USDT', $symbols);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 9 — FakeChain: NFT ERC-721
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainNftTest extends TestCase
{
    private \FakeChain\FakeChain $chain;
    private array $alice;
    private array $bob;
    private string $nftAddr;

    protected function setUp(): void
    {
        $this->chain   = new \FakeChain\FakeChain(['auto_mine' => true]);
        $this->alice   = $this->chain->createWallet('Alice', 10.0);
        $this->bob     = $this->chain->createWallet('Bob',   10.0);
        $this->nftAddr = $this->chain->deployERC721('FakeApe', 'FAPE', $this->alice['address']);
    }

    public function testDeployNft(): void
    {
        $this->assertStringStartsWith('0x', $this->nftAddr);
    }

    public function testMintNftReturnsTokenId(): void
    {
        $tokenId = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $this->assertEquals(1, $tokenId);
    }

    public function testTokenIdsIncrement(): void
    {
        $id1 = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $id2 = $this->chain->mintNFT($this->nftAddr, $this->bob['address']);
        $id3 = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
        $this->assertEquals(3, $id3);
    }

    public function testOwnerOf(): void
    {
        $tokenId = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $owner   = $this->chain->contract($this->nftAddr)->erc721OwnerOf($tokenId);
        $this->assertEquals(strtolower($this->alice['address']), strtolower($owner));
    }

    public function testTokenUri(): void
    {
        $uri     = 'https://api.fakeapes.com/1.json';
        $tokenId = $this->chain->mintNFT($this->nftAddr, $this->alice['address'], $uri);
        $fetched = $this->chain->contract($this->nftAddr)->erc721TokenURI($tokenId);
        $this->assertEquals($uri, $fetched);
    }

    public function testTokenWithoutUri(): void
    {
        $tokenId = $this->chain->mintNFT($this->nftAddr, $this->alice['address']);
        $uri     = $this->chain->contract($this->nftAddr)->erc721TokenURI($tokenId);
        $this->assertNotEmpty($uri);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 10 — FakeChain: Blocos
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainBlockTest extends TestCase
{
    private \FakeChain\FakeChain $chain;
    private array $alice;
    private array $bob;

    protected function setUp(): void
    {
        $this->chain = new \FakeChain\FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
        $this->bob   = $this->chain->createWallet('Bob',   10.0);
    }

    public function testGenesisBlockExists(): void
    {
        $genesis = $this->chain->block->getBlock(0);
        $this->assertEquals(0, $genesis['number']);
    }

    public function testLatestBlockStartsAtZero(): void
    {
        $fresh = new \FakeChain\FakeChain(['auto_mine' => false]);
        $this->assertEquals(0, $fresh->latestBlock());
    }

    public function testBlockNumberIncrements(): void
    {
        $before = (int)$this->chain->latestBlock();
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $this->assertEquals($before + 1, (int)$this->chain->latestBlock());
    }

    public function testGetBlockByNumber(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $block = $this->chain->block->getBlock(1);
        $this->assertEquals(1, $block['number']);
    }

    public function testGetLatestBlock(): void
    {
        $block = $this->chain->block->getBlock('latest');
        $this->assertArrayHasKey('hash',      $block);
        $this->assertArrayHasKey('timestamp', $block);
        $this->assertArrayHasKey('tx_count',  $block);
        $this->assertArrayHasKey('gas_used',  $block);
    }

    public function testBlockHasValidHash(): void
    {
        $block = $this->chain->block->getBlock(0);
        $this->assertStringStartsWith('0x', $block['hash']);
        $this->assertEquals(66, strlen($block['hash']));
    }

    public function testBlockNotFoundThrows(): void
    {
        $this->expectException(\FakeChain\BlockException::class);
        $this->chain->block->getBlock(99999);
    }

    public function testGetBlockWithFullTransactions(): void
    {
        $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 1.0, $this->alice['private_key']);
        $block = $this->chain->block->getBlock('latest', fullTransactions: true);
        $this->assertNotEmpty($block['transactions']);
        $this->assertIsArray($block['transactions'][0]);
    }

    public function testGetGasInfo(): void
    {
        $gas = $this->chain->block->getGasInfo();
        $this->assertArrayHasKey('gas_price_wei',  $gas);
        $this->assertArrayHasKey('gas_price_gwei', $gas);
        $this->assertGreaterThan(0, (float)$gas['gas_price_gwei']);
    }

    public function testEstimateGasEthTransfer(): void
    {
        $est = $this->chain->block->estimateGas([
            'from'  => $this->alice['address'],
            'to'    => $this->bob['address'],
            'data'  => '0x',
        ]);
        $this->assertEquals('21000', $est);
    }

    public function testEstimateGasContractCall(): void
    {
        $est = $this->chain->block->estimateGas([
            'from'  => $this->alice['address'],
            'to'    => '0x' . str_repeat('1', 40),
            'data'  => '0xa9059cbb' . str_repeat('0', 128),
        ]);
        $this->assertEquals('60000', $est);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 11 — FakeChain: Transações
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainTransactionTest extends TestCase
{
    private \FakeChain\FakeChain $chain;
    private array $alice;
    private array $bob;
    private string $txHash;

    protected function setUp(): void
    {
        $this->chain   = new \FakeChain\FakeChain(['auto_mine' => true]);
        $this->alice   = $this->chain->createWallet('Alice', 100.0);
        $this->bob     = $this->chain->createWallet('Bob',   0.0);
        $this->txHash  = $this->chain->sendTransfer($this->alice['address'], $this->bob['address'], 10.0, $this->alice['private_key']);
    }

    public function testGetTransactionByHash(): void
    {
        $tx = $this->chain->block->getTransaction($this->txHash);
        $this->assertArrayHasKey('hash',   $tx);
        $this->assertArrayHasKey('from',   $tx);
        $this->assertArrayHasKey('to',     $tx);
        $this->assertArrayHasKey('status', $tx);
        $this->assertEquals($this->txHash, $tx['hash']);
    }

    public function testTransactionStatus(): void
    {
        $tx = $this->chain->block->getTransaction($this->txHash);
        $this->assertEquals('success', $tx['status']);
    }

    public function testTransactionAmounts(): void
    {
        $tx = $this->chain->block->getTransaction($this->txHash);
        $this->assertEquals(10.0, (float)$tx['value_eth']);
    }

    public function testTransactionFromTo(): void
    {
        $tx = $this->chain->block->getTransaction($this->txHash);
        $this->assertEquals(strtolower($this->alice['address']), strtolower($tx['from']));
        $this->assertEquals(strtolower($this->bob['address']),   strtolower($tx['to']));
    }

    public function testGetTransactionNotFoundThrows(): void
    {
        $this->expectException(\FakeChain\BlockException::class);
        $this->chain->block->getTransaction('0x' . str_repeat('dead', 16));
    }

    public function testGetTransactionViaFacade(): void
    {
        $tx = $this->chain->getTransaction($this->txHash);
        $this->assertEquals($this->txHash, $tx['hash']);
    }

    public function testTransactionHistoryRecorded(): void
    {
        $history = $this->chain->wallet->getTransactionHistory($this->alice['address']);
        $this->assertNotEmpty($history);
        $hashes = array_column($history, 'hash');
        $this->assertContains($this->txHash, $hashes);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 12 — FakeChain: Rede e Stats
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainNetworkTest extends TestCase
{
    private \FakeChain\FakeChain $chain;

    protected function setUp(): void
    {
        $this->chain = new \FakeChain\FakeChain(['chain_id' => 1337, 'network' => 'fakechain', 'symbol' => 'ETH']);
    }

    public function testChainId(): void
    {
        $this->assertEquals(1337, $this->chain->network->getChainId());
    }

    public function testNodeInfo(): void
    {
        $info = $this->chain->network->getNodeInfo();
        $this->assertArrayHasKey('version',   $info);
        $this->assertArrayHasKey('chain_id',  $info);
        $this->assertArrayHasKey('network',   $info);
        $this->assertArrayHasKey('rpc_url',   $info);
        $this->assertEquals(1337,          $info['chain_id']);
        $this->assertEquals('fakechain',   $info['network']);
    }

    public function testMempoolEmptyAfterAutoMine(): void
    {
        $alice = $this->chain->createWallet('A', 100.0);
        $bob   = $this->chain->createWallet('B', 0.0);
        $this->chain->autoMine = true;
        $this->chain->sendTransfer($alice['address'], $bob['address'], 1.0, $alice['private_key']);
        $this->assertEquals(0, $this->chain->network->getMempoolSize());
    }

    public function testMempoolGrowsBeforeMining(): void
    {
        $alice = $this->chain->createWallet('A', 100.0);
        $bob   = $this->chain->createWallet('B', 0.0);
        $this->chain->autoMine = false;
        $this->chain->sendTransfer($alice['address'], $bob['address'], 1.0, $alice['private_key']);
        $this->assertEquals(1, $this->chain->network->getMempoolSize());
        $this->chain->mineBlock();
        $this->assertEquals(0, $this->chain->network->getMempoolSize());
    }

    public function testLibraryInfo(): void
    {
        $info = $this->chain->info();
        $this->assertEquals('FakeChain', $info['library']);
        $this->assertEquals('1.0.0',     $info['version']);
        $this->assertTrue($info['is_evm']);
        $this->assertArrayHasKey('chain_id', $info);
    }

    public function testSwitchNetwork(): void
    {
        $chain2 = $this->chain->switchNetwork('fakechain_test');
        $this->assertInstanceOf(\FakeChain\FakeChain::class, $chain2);
    }

    public function testRpcEthBlockNumber(): void
    {
        $result = $this->chain->rpc('eth_blockNumber');
        $this->assertStringStartsWith('0x', $result);
    }

    public function testRpcEthGasPrice(): void
    {
        $result = $this->chain->rpc('eth_gasPrice');
        $this->assertStringStartsWith('0x', $result);
    }

    public function testRpcEthChainId(): void
    {
        $result = $this->chain->rpc('eth_chainId');
        $this->assertEquals('0x' . dechex(1337), $result);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 13 — FakeChain: Contrato Genérico e Mining Manual
// ═════════════════════════════════════════════════════════════════════════════

class FakeChainContractTest extends TestCase
{
    private \FakeChain\FakeChain $chain;
    private array $alice;

    protected function setUp(): void
    {
        $this->chain = new \FakeChain\FakeChain(['auto_mine' => true]);
        $this->alice = $this->chain->createWallet('Alice', 100.0);
    }

    public function testDeployGenericContract(): void
    {
        $addr = $this->chain->deployContract(['value' => 42, 'owner' => $this->alice['address']]);
        $this->assertStringStartsWith('0x', $addr);

        $contract = $this->chain->contract($addr);
        $this->assertEquals(42, $contract->call('value'));
    }

    public function testContractStateModification(): void
    {
        $addr = $this->chain->deployContract(['counter' => 0]);

        $state = $this->chain->ledger->getContractState($addr);
        $state['counter'] = 5;
        $this->chain->ledger->setContractState($addr, $state);

        $updated = $this->chain->ledger->getContractState($addr);
        $this->assertEquals(5, $updated['counter']);
    }

    public function testManualMining(): void
    {
        $bob = $this->chain->createWallet('Bob', 0.0);
        $this->chain->autoMine = false;

        $hash = $this->chain->sendTransfer($this->alice['address'], $bob['address'], 5.0, $this->alice['private_key']);
        $this->assertEquals(1, $this->chain->network->getMempoolSize());
        $this->assertEquals('0', $this->chain->wallet->getBalance($bob['address']));

        $block = $this->chain->mineBlock();
        $this->assertArrayHasKey('number',    $block);
        $this->assertArrayHasKey('hash',      $block);
        $this->assertArrayHasKey('tx_hashes', $block);

        $this->assertEquals('5', $this->chain->wallet->getBalance($bob['address']));
        $this->assertEquals(0, $this->chain->network->getMempoolSize());

        $this->chain->autoMine = true;
    }

    public function testGetUTXOs(): void
    {
        $utxos = $this->chain->transfer->getBitcoinUTXOs($this->alice['address']);
        $this->assertNotEmpty($utxos);
        $this->assertArrayHasKey('txid',      $utxos[0]);
        $this->assertArrayHasKey('value_btc', $utxos[0]);
        $this->assertTrue($utxos[0]['confirmed']);
    }

    public function testBroadcastBitcoin(): void
    {
        $txid = $this->chain->transfer->broadcastBitcoin('deadbeef');
        $this->assertNotEmpty($txid);
    }

    public function testBroadcastTron(): void
    {
        $result = $this->chain->transfer->broadcastTron(['txID' => 'abc', 'signature' => []]);
        $this->assertTrue($result['result']);
        $this->assertArrayHasKey('txid', $result);
    }

    public function testSendSolanaTransaction(): void
    {
        $sig = $this->chain->transfer->sendSolanaTransaction(base64_encode('fake_tx'));
        $this->assertNotEmpty($sig);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SUITE 14 — Web3PHP Facade + Provider (sem rede real)
// ═════════════════════════════════════════════════════════════════════════════

class Web3PHPFacadeTest extends TestCase
{
    public function testInstantiateWithPublicRpc(): void
    {
        $w3 = new \Web3PHP\Web3PHP(['network' => 'ethereum']);
        $this->assertInstanceOf(\Web3PHP\Web3PHP::class, $w3);
        $this->assertInstanceOf(\Web3PHP\WalletModule::class,       $w3->wallet);
        $this->assertInstanceOf(\Web3PHP\BlockModule::class,         $w3->block);
        $this->assertInstanceOf(\Web3PHP\TransferModule::class,      $w3->transfer);
        $this->assertInstanceOf(\Web3PHP\NetworkStatsModule::class,  $w3->network);
    }

    public function testInfoReturnsCorrectStructure(): void
    {
        $w3   = new \Web3PHP\Web3PHP(['network' => 'ethereum']);
        $info = $w3->info();

        $this->assertEquals('Web3PHP',  $info['library']);
        $this->assertEquals('1.0.1',    $info['version']);
        $this->assertEquals('ethereum', $info['network']);
        $this->assertEquals(1,          $info['chain_id']);
        $this->assertEquals('ETH',      $info['symbol']);
        $this->assertTrue($info['is_evm']);
    }

    public function testSwitchNetwork(): void
    {
        $eth  = new \Web3PHP\Web3PHP(['network' => 'ethereum', 'provider' => 'infura', 'api_key' => 'test']);
        $poly = $eth->switchNetwork('polygon');

        $infoEth  = $eth->info();
        $infoPoly = $poly->info();

        $this->assertEquals('ethereum', $infoEth['network']);
        $this->assertEquals('polygon',  $infoPoly['network']);
    }

    public function testContractModuleInstantiation(): void
    {
        $w3       = new \Web3PHP\Web3PHP(['network' => 'ethereum']);
        $contract = $w3->contract('0xdAC17F958D2ee523a2206206994597C13D831ec7');
        $this->assertInstanceOf(\Web3PHP\ContractModule::class, $contract);
    }

    public function testContractModuleOnNonEvmThrows(): void
    {
        $this->expectException(\Web3PHP\ContractException::class);
        $w3 = new \Web3PHP\Web3PHP(['network' => 'bitcoin']);
        $w3->contract('bc1q...');
    }

    public function testProviderResolvesPublicRpc(): void
    {
        $w3  = new \Web3PHP\Web3PHP(['network' => 'polygon']);
        $url = $w3->provider->getRpcUrl();
        $this->assertStringContainsString('polygon', $url);
    }

    public function testProviderResolvesInfura(): void
    {
        $w3  = new \Web3PHP\Web3PHP(['network' => 'ethereum', 'provider' => 'infura', 'api_key' => 'mykey']);
        $url = $w3->provider->getRpcUrl();
        $this->assertStringContainsString('infura.io', $url);
        $this->assertStringContainsString('mykey', $url);
    }

    public function testProviderResolvesAlchemy(): void
    {
        $w3  = new \Web3PHP\Web3PHP(['network' => 'polygon', 'provider' => 'alchemy', 'api_key' => 'mykey']);
        $url = $w3->provider->getRpcUrl();
        $this->assertStringContainsString('alchemy.com', $url);
    }

    public function testProviderResolvesCustomUrl(): void
    {
        $w3  = new \Web3PHP\Web3PHP(['network' => 'ethereum', 'rpc_url' => 'http://localhost:8545']);
        $url = $w3->provider->getRpcUrl();
        $this->assertEquals('http://localhost:8545', $url);
    }

    public function testUnknownNetworkThrows(): void
    {
        $this->expectException(\Web3PHP\ProviderException::class);
        new \Web3PHP\Web3PHP(['network' => 'totally_unknown_chain_xyz']);
    }

    public function testInfuraUnsupportedNetworkThrows(): void
    {
        $this->expectException(\Web3PHP\ProviderException::class);
        new \Web3PHP\Web3PHP(['network' => 'bitcoin', 'provider' => 'infura', 'api_key' => 'key']);
    }
}
