<?php

$db = "tazrim";
$output = "docs/database/tazrim.sql";

$command = "/Applications/XAMPP/xamppfiles/bin/mysqldump --no-data -u root {$db} > {$output}";
exec($command);

echo "Schema exported\n";