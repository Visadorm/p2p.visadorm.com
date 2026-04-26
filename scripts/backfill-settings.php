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
    ['general',      'support_url',                   'null'],
    ['general',      'homepage_variant',              '"classic"'],
    ['general',      'weglot_enabled',                'false'],
    ['general',      'weglot_api_key',                'null'],
    ['general',      'merchant_registration_enabled', 'true'],
    ['general',      'p2p_trading_enabled',           'true'],
    ['general',      'cash_meetings_enabled',         'true'],
    ['notifications','admin_email',                   '""'],
    ['notifications','email_notifications_enabled',   'true'],
    ['trade',        'sell_enabled',                       'true'],
    ['trade',        'sell_max_offers_per_wallet',         '5'],
    ['trade',        'sell_max_outstanding_usdc',          '50000'],
    ['trade',        'sell_kyc_threshold_usdc',            '1000'],
    ['trade',        'sell_kyc_threshold_window_days',     '30'],
    ['trade',        'sell_cash_meeting_enabled',          'false'],
    ['trade',        'sell_default_offer_timer_minutes',   '60'],
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

// Address rotations — UPDATE existing rows ONLY when payload still matches the
// previous canonical value. Admin overrides (any non-matching value) are left
// untouched. Each entry: [group, name, expected_old_payload, new_payload].
$rotations = [
    [
        'blockchain',
        'trade_escrow_address',
        '"0xc4D74Ddcc4ee8DFa9687C37De8be3A21f813C00D"',
        '"0xD9771DF5f6EA84AceeA98F6DF27497c159dd940c"',
    ],
];

$rotateStmt = $pdo->prepare(
    'UPDATE settings SET payload = ?, updated_at = NOW()
     WHERE `group` = ? AND name = ? AND payload = ?'
);

$rotated = 0;
foreach ($rotations as [$group, $name, $oldPayload, $newPayload]) {
    $rotateStmt->execute([$newPayload, $group, $name, $oldPayload]);
    if ($rotateStmt->rowCount() > 0) {
        $rotated++;
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
        '2026_04_25_000001_add_sell_flags_to_trade_settings',
        '2026_04_26_000002_drop_default_currency_country_from_general',
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

echo "inserted={$inserted} considered=" . count($rows) . " rotated={$rotated} marked_migrations={$markedMigrations}\n";
exit(0);
