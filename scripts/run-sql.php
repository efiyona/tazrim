<?php

require dirname(__DIR__) . '/secrets.php';

$host = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') ? '127.0.0.1' : DB_HOST;
$conn = new mysqli($host, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    fwrite(STDERR, 'Connection failed: ' . $conn->connect_error . PHP_EOL);
    exit(1);
}

if (!isset($argv[1]) || $argv[1] === '') {
    fwrite(STDERR, 'Usage: php scripts/run-sql.php path/to/file.sql' . PHP_EOL);
    exit(1);
}

$sql = file_get_contents($argv[1]);
if ($sql === false) {
    fwrite(STDERR, 'Cannot read file: ' . $argv[1] . PHP_EOL);
    exit(1);
}

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Migration applied\n";
} else {
    fwrite(STDERR, $conn->error . PHP_EOL);
    exit(1);
}

exec('php ' . escapeshellarg(dirname(__DIR__) . '/scripts/export-schema.php'));
