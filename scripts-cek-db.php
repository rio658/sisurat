<?php

$url = getenv('MYSQL_PUBLIC_URL') ?: getenv('DATABASE_PUBLIC_URL') ?: getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
if ($url) {
    $p = parse_url($url);
    $host = $p['host'] ?? null;
    $port = $p['port'] ?? 3306;
    $db = ltrim($p['path'] ?? '', '/');
    $user = $p['user'] ?? null;
    $pass = $p['pass'] ?? null;
} else {
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST');
    $port = getenv('MYSQLPORT') ?: 3306;
    $db = getenv('MYSQLDATABASE') ?: getenv('DB_DATABASE');
    $user = getenv('MYSQLUSER') ?: getenv('DB_USERNAME');
    $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD');
}

echo 'public_url='.($url ? 'yes' : 'no').PHP_EOL;
echo 'internal_host='.(str_contains((string) $host, 'railway.internal') ? 'yes' : 'no').PHP_EOL;

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_TIMEOUT => 8]);
    echo 'connect=ok'.PHP_EOL;
    echo 'surat='.$pdo->query('select count(*) from surats')->fetchColumn().PHP_EOL;
} catch (Throwable $e) {
    echo 'connect=fail'.PHP_EOL;
}
