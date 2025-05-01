<?php
require_once __DIR__ . '/config/database.php';

$db = getDbConnection();

if ($db) {
    echo "✅ Connected to MySQL successfully.";
} else {
    echo "❌ Failed to connect to MySQL.";
}
