<?php
require __DIR__ . '/../app/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
db()->exec($sql);
run('INSERT IGNORE INTO settings (id) VALUES (1)');
echo "Schema ready.\n";
