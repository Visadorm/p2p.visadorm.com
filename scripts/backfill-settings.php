<?php

declare(strict_types=1);

/**
 * Bare PDO settings backfill — runs WITHOUT booting Laravel.
 *
 * Inserts required Spatie LaravelSettings rows so the typed Settings classes
 * can be hydrated by Filament/Inertia/etc on every subsequent boot.
 *
 * Idempotent — uses INSERT IGNORE on the (group, name) unique key.
 */

$envPath = __DIR__ . '/../.env';
if (! is_file($envPath)) {
    fwrite(STDERR, "missing .env at {$envPath}\n");
    exit(1);
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $trim = ltrim($line);
    if ($trim === '' || str_starts_with($trim, '#')) {
        continue;
    }
    if (! str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

$conn = $env['DB_CONNECTION'] ?? 'mysql';
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int) ($env['DB_PORT'] ?? 3306);
$db   = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';

if (! in_array($conn, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "unsupported driver: {$conn}\n");
    exit(2);
}

if ($db === '' || $user === '') {
    fwrite(STDERR, "missing db/user in .env (db='{$db}' user='{$user}')\n");
    exit(3);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'connect failed: ' . $e->getMessage() . "\n");
    exit(4);
}

// Ensure settings table exists. If not, nothing to backfill yet — exit cleanly.
$tbl = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
if (! $tbl) {
    echo "settings table not present yet — nothing to backfill\n";
    exit(0);
}

$rows = [
    ['general', 'support_url',      'null'],
    ['general', 'homepage_variant', '"classic"'],
    ['general', 'weglot_enabled',   'false'],
    ['general', 'weglot_api_key',   'null'],
];

$stmt = $pdo->prepare(
    'INSERT IGNORE INTO settings (`group`, name, payload, locked, created_at, updated_at)
     VALUES (?, ?, ?, 0, NOW(), NOW())',
);

$inserted = 0;
foreach ($rows as [$group, $name, $payload]) {
    $stmt->execute([$group, $name, $payload]);
    if ($stmt->rowCount() > 0) {
        $inserted++;
    }
}

echo "inserted={$inserted} considered=" . count($rows) . "\n";
exit(0);
