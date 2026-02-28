#!/usr/bin/env php
<?php

/**
 * fix_fakechain.php — Corrige erros "Constant expression contains invalid operations"
 *
 * USO:
 *   php fix_fakechain.php
 *
 * O script corrige dois bugs no src/FakeChain.php e salva o arquivo.
 */

$file = __DIR__ . '/src/FakeChain.php';

if (!file_exists($file)) {
    die("ERRO: Arquivo não encontrado: {$file}\n");
}

$src = file_get_contents($file);
$original = $src;
$fixes = 0;

// ─────────────────────────────────────────────────────────────────────────────
// FIX 1 — Propriedade de classe com str_repeat()
//
// ANTES:
//   public string $minerAddress = '0x' . str_repeat('f', 40);
//
// DEPOIS:
//   public string $minerAddress = '0xffffffffffffffffffffffffffffffffffffffff';
// ─────────────────────────────────────────────────────────────────────────────

$before1 = "public string \$minerAddress = '0x' . str_repeat('f', 40);";
$after1  = "public string \$minerAddress = '0xffffffffffffffffffffffffffffffffffffffff';";

if (str_contains($src, $before1)) {
    $src = str_replace($before1, $after1, $src);
    echo "[FIX 1] Corrigido: propriedade \$minerAddress\n";
    $fixes++;
} else {
    echo "[FIX 1] AVISO: padrão não encontrado (já corrigido ou diferente)\n";
    echo "         Buscando por: " . $before1 . "\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// FIX 2 — Parâmetro padrão de método com str_repeat()
//
// ANTES:
//   public function mine(string $minerAddress = '0x' . '0' . str_repeat('1', 39)): array
//
// DEPOIS:
//   public function mine(string $minerAddress = '0x0111111111111111111111111111111111111111'): array
// ─────────────────────────────────────────────────────────────────────────────

$before2 = "public function mine(string \$minerAddress = '0x' . '0' . str_repeat('1', 39)): array";
$after2  = "public function mine(string \$minerAddress = '0x0111111111111111111111111111111111111111'): array";

if (str_contains($src, $before2)) {
    $src = str_replace($before2, $after2, $src);
    echo "[FIX 2] Corrigido: parâmetro padrão do método mine()\n";
    $fixes++;
} else {
    // Tenta variação sem espaço/com espaço diferente
    $pattern = "/public function mine\(string \\\$minerAddress = '0x' \. '0' \. str_repeat\('1', 39\)\): array/";
    if (preg_match($pattern, $src)) {
        $src = preg_replace($pattern, $after2, $src);
        echo "[FIX 2] Corrigido via regex: parâmetro padrão do método mine()\n";
        $fixes++;
    } else {
        echo "[FIX 2] AVISO: padrão não encontrado (já corrigido ou diferente)\n";
        echo "         Buscando por: " . $before2 . "\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FIX 3 — Busca genérica por qualquer str_repeat em propriedade/parâmetro
//          (captura variações não previstas)
// ─────────────────────────────────────────────────────────────────────────────

$remaining = preg_match_all(
    "/(public|protected|private|readonly).*=.*str_repeat\(/",
    $src,
    $matches
);

if ($remaining > 0) {
    echo "\n[AVISO] Ainda existem {$remaining} ocorrência(s) de str_repeat() em propriedades:\n";
    foreach ($matches[0] as $m) {
        echo "  → " . trim($m) . "\n";
    }
    echo "  Corrija manualmente substituindo a chamada pelo valor literal.\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Salvar
// ─────────────────────────────────────────────────────────────────────────────

if ($fixes > 0) {
    // Backup
    file_put_contents($file . '.bak', $original);
    echo "\n[BACKUP] Original salvo em: {$file}.bak\n";

    file_put_contents($file, $src);
    echo "[SALVO] {$file} atualizado com {$fixes} correção(ões).\n";
} else {
    echo "\nNenhuma alteração necessária.\n";
}

// Validação rápida
echo "\n[VALIDANDO] php -l {$file}\n";
$output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
echo $output;
