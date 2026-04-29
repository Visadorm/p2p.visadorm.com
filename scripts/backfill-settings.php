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
    // Sell flow defaults — must exist before TradeSettings hydrates.
    ['trade', 'sell_enabled',                 'true'],
    ['trade', 'sell_cash_trade_enabled',      'true'],
    ['trade', 'sell_default_expiry_minutes',  '60'],
    ['trade', 'sell_anti_spam_stake_usdc',    '5'],
    ['trade', 'sell_require_stake_public',    'true'],
    ['trade', 'sell_require_stake_link',      'false'],
    ['trade', 'sell_require_stake_cash',      'true'],
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

// Mark the Spatie settings migrations whose rows we just guaranteed as
// already-ran. Otherwise `php artisan migrate` will retry them and crash with
// SettingAlreadyExists, aborting all subsequent migrations (including the new
// pages table). Idempotent — INSERT IGNORE on the unique migration name.
$migrationsTable = $pdo->query("SHOW TABLES LIKE 'migrations'")->fetchColumn();
$markedMigrations = 0;
if ($migrationsTable) {
    $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
    if ($batch === 0) {
        $batch = 1;
    }

    $migrationNames = [
        '2026_04_23_000001_add_homepage_and_weglot_to_general_settings',
        '2026_04_24_000001_add_support_url_to_general_settings',
        '2026_04_28_000001_add_sell_settings_to_trade',
    ];

    $checkStmt  = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
    $insertStmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');

    foreach ($migrationNames as $name) {
        $checkStmt->execute([$name]);
        if ((int) $checkStmt->fetchColumn() === 0) {
            $insertStmt->execute([$name, $batch]);
            $markedMigrations++;
        }
    }
}

// Address rotations — UPDATE blockchain settings to current deployed contracts.
// Each rotation: ['name' => '...', 'old' => '0x...', 'new' => '0x...'].
// Only updates if the current value matches `old` (case-insensitive). Idempotent.
$rotations = [
    // Local rotation chain (post-rollback)
    [
        'name' => 'trade_escrow_address',
        'old'  => '0xc4D74Ddcc4ee8DFa9687C37De8be3A21f813C00D',
        'new'  => '0x75B60DD962370d5569cDfe97F52833882B9ae66B',
    ],
    // Prod was on intermediate address — also rotate it
    [
        'name' => 'trade_escrow_address',
        'old'  => '0xD9771DF5f6EA84AceeA98F6DF27497c159dd940c',
        'new'  => '0x75B60DD962370d5569cDfe97F52833882B9ae66B',
    ],
    [
        'name' => 'usdc_address',
        'old'  => '0xe3B1038eecea95053256D0e5d52D11A0703D1c4F',
        'new'  => '0x7c33814E64FaC03Fd45C3B11C94a4BFa7cb6E1d1',
    ],
    [
        'name' => 'soulbound_nft_address',
        'old'  => '0xD81a5b95550E94C7ec995af6BaaD4ab7281B5FFD',
        'new'  => '0xA91dB431d01aD94310c8cFee2e139720121D1AA2',
    ],
    // Also handle prior rollback intermediate addresses if present
    [
        'name' => 'soulbound_nft_address',
        'old'  => '0xC31d56C9FfEb857aBB69dd6a686658E3Fd15bB4e',
        'new'  => '0xA91dB431d01aD94310c8cFee2e139720121D1AA2',
    ],
    [
        'name' => 'usdc_address',
        'old'  => '0xc4d1c4B5778f61d8DdAB492FEF745FB5133FEC53',
        'new'  => '0x7c33814E64FaC03Fd45C3B11C94a4BFa7cb6E1d1',
    ],
];

$updateStmt = $pdo->prepare(
    "UPDATE settings SET payload = ?, updated_at = NOW()
     WHERE `group` = 'blockchain' AND name = ? AND LOWER(payload) = LOWER(?)"
);

$rotated = 0;
foreach ($rotations as $r) {
    $updateStmt->execute(['"' . $r['new'] . '"', $r['name'], '"' . $r['old'] . '"']);
    if ($updateStmt->rowCount() > 0) {
        $rotated++;
    }
}

echo "inserted={$inserted} considered=" . count($rows) . " marked_migrations={$markedMigrations} rotated={$rotated}\n";
exit(0);
