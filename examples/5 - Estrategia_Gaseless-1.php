<?php

// ═══════════════════════════════════════════════════════════
//  Web3PHP — Estratégias Gasless / Meta-Transactions
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/vendor/autoload.php';

use Web3PHP\Web3PHP;
use Web3PHP\Math;
use kornrunner\Ethereum\Transaction;


// ════════════════════════════════════════════════════════════
// ESTRATÉGIA 1 — Top-up mínimo + devolução do troco
//
// Fluxo:
//   Hot wallet envia exatamente o gas necessário
//   Após o sweep, o troco de MATIC que sobrou volta pra hot wallet
//   Custo real = apenas o gas gasto, não o top-up inteiro
// ════════════════════════════════════════════════════════════

$w3      = new Web3PHP(['network' => 'polygon', 'provider' => 'alchemy', 'api_key' => 'KEY']);
$chainId = 137;

$hotWallet     = ['address' => '0xHOT',     'private_key' => 'HOT_PK'];
$depositWallet = ['address' => '0xDEPOSIT', 'private_key' => 'DEPOSIT_PK'];
$usdtContract  = '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';

// 1a. Calcular gas do sweep
$gasInfo      = $w3->block->getGasInfo();
$gasEstimado  = (int)$w3->block->estimateGas([
    'from' => $depositWallet['address'],
    'to'   => $usdtContract,
    'data' => '0xa9059cbb',
]);
$gasLimit     = (int)ceil($gasEstimado * 1.25);
$gasCostMatic = bcdiv(
    bcmul((string)$gasLimit, $gasInfo['gas_price_wei']),
    bcpow('10', '18'),
    8
);

// 1b. Enviar EXATAMENTE o gas necessário (sem gordurinhas)
$topUpTx = $w3->transfer->buildNativeTransfer(
    from:   $hotWallet['address'],
    to:     $depositWallet['address'],
    amount: (float)$gasCostMatic
);
$topUpSigned = new Transaction(
    $topUpTx['nonce'], $topUpTx['gasPrice'], $topUpTx['gas'],
    $topUpTx['to'], $topUpTx['value'], $topUpTx['data']
);
$topUpHash = $w3->transfer->sendRaw(
    $topUpSigned->getRaw($hotWallet['private_key'], $chainId)
);
$w3->transfer->waitForConfirmation($topUpHash);

// 1c. Sweep do USDT
$saldoUsdt  = $w3->wallet->getTokenBalance($depositWallet['address'], $usdtContract, 6);
$sweepTx    = $w3->transfer->buildTokenTransfer(
    from: $depositWallet['address'], contractAddress: $usdtContract,
    to: $hotWallet['address'], amount: (float)$saldoUsdt,
    decimals: 6, customGas: $gasLimit
);
$sweepSigned = new Transaction(
    $sweepTx['nonce'], $sweepTx['gasPrice'], $sweepTx['gas'],
    $sweepTx['to'], '0x0', $sweepTx['data']
);
$sweepHash = $w3->transfer->sendRaw(
    $sweepSigned->getRaw($depositWallet['private_key'], $chainId)
);
$sweepReceipt = $w3->transfer->waitForConfirmation($sweepHash);

// 1d. Devolver o troco de MATIC que sobrou na deposit wallet
$saldoMaticRestante = $w3->wallet->getBalance($depositWallet['address']);
$gasParaDevolucao   = (int)$w3->block->estimateGas([
    'from' => $depositWallet['address'],
    'to'   => $hotWallet['address'],
]);
$custoDevolucaoMatic = bcdiv(
    bcmul((string)$gasParaDevolucao, $gasInfo['gas_price_wei']),
    bcpow('10', '18'),
    8
);
$trocoLiquido = bcsub($saldoMaticRestante, $custoDevolucaoMatic, 8);

if (bccomp($trocoLiquido, '0', 8) > 0) {
    $trocoTx = $w3->transfer->buildNativeTransfer(
        from: $depositWallet['address'], to: $hotWallet['address'],
        amount: (float)$trocoLiquido
    );
    $trocoSigned = new Transaction(
        $trocoTx['nonce'], $trocoTx['gasPrice'], $trocoTx['gas'],
        $trocoTx['to'], $trocoTx['value'], $trocoTx['data']
    );
    $trocoHash = $w3->transfer->sendRaw(
        $trocoSigned->getRaw($depositWallet['private_key'], $chainId)
    );
    echo "Troco devolvido: {$trocoLiquido} MATIC → {$trocoHash}" . PHP_EOL;
}

echo "Custo real do ciclo completo: ~" .
     bcsub($gasCostMatic, $trocoLiquido ?? '0', 8) . " MATIC" . PHP_EOL;


// ════════════════════════════════════════════════════════════
// ESTRATÉGIA 2 — Relayer (seu servidor paga o gas)
//
// Fluxo:
//   Usuário assina a intenção offline (sem MATIC)
//   Seu servidor (relayer) submete a TX e paga o gas
//   Compatível com EIP-2771 e contratos que suportam forwardRequest
//
// Serviços prontos (gratuitos até certo volume):
//   Gelato Network  → relay.gelato.network
//   Biconomy        → biconomy.io
//   OpenGSN         → opengsn.org
// ════════════════════════════════════════════════════════════

// 2a. Usuário assina a mensagem de intenção (sem broadcast, sem gas)
function criarAssinaturaMetaTx(
    string $from,
    string $to,
    string $data,
    int    $nonce,
    string $privateKey
): array {
    // Estrutura EIP-712 simplificada para ForwardRequest
    $hash = hash('sha256', json_encode([
        'from'  => $from,
        'to'    => $to,
        'data'  => $data,
        'nonce' => $nonce,
    ]));

    // Em produção: assinar com ECDSA secp256k1 real
    // $signature = signEIP712($hash, $privateKey);

    return [
        'from'      => $from,
        'to'        => $to,
        'data'      => $data,
        'nonce'     => $nonce,
        'signature' => '0x' . $hash, // placeholder — use lib ECDSA real
    ];
}

// 2b. Relayer recebe a intenção e submete na blockchain
function relayarTransacao(array $forwardRequest, Web3PHP $w3, array $relayerWallet, int $chainId): string
{
    // Em produção: chamar contrato Forwarder (EIP-2771)
    // ou usar API do Gelato/Biconomy diretamente

    // Simulação: relayer constrói a TX chamando o trusted forwarder
    $forwarderContract = '0xENDERECO_DO_FORWARDER';

    $tx = $w3->contract($forwarderContract)->buildTransaction(
        from:      $relayerWallet['address'],
        signature: 'execute((address,address,bytes,uint256),bytes)',
        types:     ['tuple(address,address,bytes,uint256)', 'bytes'],
        values:    [
            [$forwardRequest['from'], $forwardRequest['to'],
             $forwardRequest['data'], $forwardRequest['nonce']],
            $forwardRequest['signature'],
        ]
    );

    $signed = new Transaction(
        $tx['nonce'], $tx['gasPrice'], $tx['gas'],
        $tx['to'], $tx['value'], $tx['data']
    );

    return $w3->transfer->sendRaw(
        $signed->getRaw($relayerWallet['private_key'], $chainId)
    );
}

// Uso:
$intencao   = criarAssinaturaMetaTx(
    from:       $depositWallet['address'],
    to:         $usdtContract,
    data:       '0xa9059cbb...',  // calldata do transfer()
    nonce:      0,
    privateKey: $depositWallet['private_key']
);
// $txHash = relayarTransacao($intencao, $w3, $hotWallet, $chainId);


// ════════════════════════════════════════════════════════════
// ESTRATÉGIA 3 — EIP-2612 permit() ← A MAIS ELEGANTE
//
// Fluxo:
//   Usuário assina um "permit" offline autorizando o gasto
//   Hot wallet chama permit() + transferFrom() em 1 ou 2 TXs
//   Usuário NUNCA precisa de MATIC — tudo pago pela hot wallet
//
// ⚠️  Requer que o token suporte EIP-2612 (USDC, DAI, USDT na Polygon sim)
// ════════════════════════════════════════════════════════════

function criarPermitSignature(
    string $ownerAddress,
    string $spenderAddress, // hot wallet ou contrato que vai gastar
    string $valor,          // em wei/units
    int    $deadline,       // timestamp unix de expiração
    string $privateKey,
    string $tokenContract,
    int    $chainId
): array {
    // Estrutura EIP-712 do permit()
    // Em produção: usar biblioteca ECDSA para assinar corretamente
    $domainSeparator = hash('sha256', json_encode([
        'name'              => 'Tether USD',
        'version'           => '1',
        'chainId'           => $chainId,
        'verifyingContract' => $tokenContract,
    ]));

    $permitHash = hash('sha256', json_encode([
        'owner'    => $ownerAddress,
        'spender'  => $spenderAddress,
        'value'    => $valor,
        'nonce'    => 0, // buscar nonce real: $w3->contract($token)->call('nonces(address)', ...)
        'deadline' => $deadline,
    ]));

    // Assinar com ECDSA — produção usa secp256k1 real
    // list($v, $r, $s) = ecdsaSign($permitHash, $privateKey);

    return [
        'v'        => 27,         // placeholder
        'r'        => '0x' . str_repeat('a', 64),
        's'        => '0x' . str_repeat('b', 64),
        'deadline' => $deadline,
        'valor'    => $valor,
    ];
}

function executarPermitESweep(
    Web3PHP $w3,
    array   $hotWallet,
    string  $depositAddress,
    string  $tokenContract,
    array   $permit,
    int     $chainId
): string {
    // TX 1: chamar permit() — hot wallet paga o gas, usuário não precisa de MATIC
    $permitTx = $w3->contract($tokenContract)->buildTransaction(
        from:      $hotWallet['address'],
        signature: 'permit(address,address,uint256,uint256,uint8,bytes32,bytes32)',
        types:     ['address','address','uint256','uint256','uint8','bytes32','bytes32'],
        values:    [
            $depositAddress,
            $hotWallet['address'],
            $permit['valor'],
            $permit['deadline'],
            $permit['v'],
            $permit['r'],
            $permit['s'],
        ]
    );

    $signed = new Transaction(
        $permitTx['nonce'], $permitTx['gasPrice'], $permitTx['gas'],
        $permitTx['to'], $permitTx['value'], $permitTx['data']
    );
    $permitHash = $w3->transfer->sendRaw(
        $signed->getRaw($hotWallet['private_key'], $chainId)
    );
    $w3->transfer->waitForConfirmation($permitHash);

    // TX 2: chamar transferFrom() — hot wallet move os tokens do usuário
    $transferTx = $w3->contract($tokenContract)->buildTransaction(
        from:      $hotWallet['address'],
        signature: 'transferFrom(address,address,uint256)',
        types:     ['address','address','uint256'],
        values:    [$depositAddress, $hotWallet['address'], $permit['valor']]
    );

    $signed2    = new Transaction(
        $transferTx['nonce'], $transferTx['gasPrice'], $transferTx['gas'],
        $transferTx['to'], $transferTx['value'], $transferTx['data']
    );
    return $w3->transfer->sendRaw(
        $signed2->getRaw($hotWallet['private_key'], $chainId)
    );
}

// Uso da Estratégia 3:
$deadline = time() + 3600; // válido por 1 hora
$valor    = Math::parseUnits('100', 6); // 100 USDT

$permit = criarPermitSignature(
    ownerAddress:   $depositWallet['address'],
    spenderAddress: $hotWallet['address'],
    valor:          $valor,
    deadline:       $deadline,
    privateKey:     $depositWallet['private_key'],
    tokenContract:  $usdtContract,
    chainId:        $chainId
);

// $txHash = executarPermitESweep($w3, $hotWallet, $depositWallet['address'], $usdtContract, $permit, $chainId);


// ════════════════════════════════════════════════════════════
// COMPARATIVO DE CUSTO REAL (Polygon)
// ════════════════════════════════════════════════════════════

// Estratégia 1 — Top-up + troco
//   2 TXs de MATIC nativo  (~21.000 gas cada)  ≈ 0.0003 MATIC
//   1 TX ERC-20 transfer   (~65.000 gas)        ≈ 0.0008 MATIC
//   Total líquido por ciclo                     ≈ $0.0004 USD

// Estratégia 2 — Relayer (Gelato free tier)
//   Custo para você        ≈ $0 (até 100 req/dia no free tier)
//   Custo para o usuário   = $0 (nunca precisa de MATIC)

// Estratégia 3 — permit() + transferFrom()
//   2 TXs pagas pela hot wallet                 ≈ 0.0012 MATIC
//   Custo para o usuário                        = $0 (nunca precisa de MATIC)
//   Melhor para: plataformas onde você controla o frontend

echo "Escolha da estratégia:" . PHP_EOL;
echo "  Volume alto + UX simples  → Estratégia 3 (permit)" . PHP_EOL;
echo "  Zero custo operacional    → Estratégia 2 (Gelato relayer)" . PHP_EOL;
echo "  Controle total            → Estratégia 1 (top-up + troco)" . PHP_EOL;