<?php

require dirname(__DIR__) . '/secrets.php';

$host = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') ? '127.0.0.1' : DB_HOST;
$conn = new mysqli($host, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("DB connection failed");
}

$action = $argv[1] ?? '';

switch ($action) {

    case 'tables':
        $result = $conn->query("SHOW TABLES");
        $tables = [];

        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        echo json_encode($tables, JSON_PRETTY_PRINT);
        break;

    case 'columns':
        $table = $argv[2] ?? '';
        $result = $conn->query("DESCRIBE `$table`");

        $columns = [];

        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }

        echo json_encode($columns, JSON_PRETTY_PRINT);
        break;

    default:
        echo "Usage:\n";
        echo "php db-introspect.php tables\n";
        echo "php db-introspect.php columns users\n";
}