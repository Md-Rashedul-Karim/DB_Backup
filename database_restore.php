<?php
/**
 * ======================================================
 * Smart MySQL Chunked Restore Script (Auto Create DB)
 * ======================================================
 * Author: Md Rashedul Karim
 * Version: 3
 * ======================================================
 * run: php database_restore.php
 */

ini_set('memory_limit', '-1');
set_time_limit(0);
date_default_timezone_set('Asia/Dhaka');

// === 🛠 Database Config ===
$dbHost = '192.168.20.14';
$dbUser = 'root';
$dbPass = '351f0*57034e1a025#';
$dbName = 'z_dob'; // select database name

// === Backup Base Dir ===
$backupBaseDir = __DIR__ . '/backups';

// === 1️⃣ Find all backup folders path ===
$folders = glob("{$backupBaseDir}/*", GLOB_ONLYDIR);
if (empty($folders)) {
    die("❌ No backup folders found.\n");
}

echo "🗂 Available database backups:\n";
echo "-------------------------------------------\n";
foreach ($folders as $i => $folder) {
    echo "[" . ($i + 1) . "] " . basename($folder) . "\n";
}
echo "-------------------------------------------\n";

// === 2️⃣ User selects which backup folder to restore ===
echo "🔢 Enter the number of the backup you want to restore: ";
$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

if (!is_numeric($choice) || $choice < 1 || $choice > count($folders)) {
    die("❌ Invalid choice.\n");
}

$selectedBackup = $folders[$choice - 1];
echo "✅ Selected backup folder: {$selectedBackup}\n";
echo "📦 Restoring into database: {$dbName}\n\n";

// === 3️⃣ Connect to MySQL (without selecting DB first) ===
$mysqli = new mysqli($dbHost, $dbUser, $dbPass);
if ($mysqli->connect_error) {
    die("❌ MySQL connection failed: " . $mysqli->connect_error);
}

// === 4️⃣ Create database if not exists ===
if (!$mysqli->select_db($dbName)) {
    echo "⚡ Database '{$dbName}' does not exist. Creating...\n";
    if ($mysqli->query("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        echo "✅ Database '{$dbName}' created successfully.\n";
    } else {
        die("❌ Failed to create database '{$dbName}': " . $mysqli->error . "\n");
    }
}

// === 5️⃣ Select the database ===
$mysqli->select_db($dbName);

// === 6️⃣ List available tables in backup folder ===
$tables = [];
foreach (glob("{$selectedBackup}/*.sql.gz") as $file) {
    $tables[] = basename($file, '.sql.gz');
}

if (empty($tables)) {
    die("⚠️ No tables found in selected backup.\n");
}

echo "📋 Available tables in this backup:\n";
foreach ($tables as $i => $t) {
    echo "[" . ($i + 1) . "] {$t}\n";
}
echo "-------------------------------------------\n";

// === 7️⃣ Choose which tables to restore ===
echo "✍️ Enter table numbers (comma-separated, e.g. 1,2,5): ";
$handle = fopen("php://stdin", "r");
$tableInput = trim(fgets($handle));
fclose($handle);

$selectedIndexes = array_map('intval', explode(',', $tableInput));
$tablesToRestore = [];

foreach ($selectedIndexes as $index) {
    if ($index > 0 && $index <= count($tables)) {
        $tablesToRestore[] = $tables[$index - 1];
    }
}

if (empty($tablesToRestore)) {
    die("⚠️ No valid tables selected.\n");
}

echo "\n🚀 Starting chunked restore for: " . implode(', ', $tablesToRestore) . "\n\n";

// === 8️⃣ Restore each selected table chunk-by-chunk ===
foreach ($tablesToRestore as $table) {
    $gzFile = "{$selectedBackup}/{$table}.sql.gz";

    if (!file_exists($gzFile)) {
        echo "⚠️ Backup file not found for table: {$table}\n";
        continue;
    }

    echo "📂 Restoring table: {$table}\n";
    $gz = gzopen($gzFile, 'rb');
    if (!$gz) {
        echo "❌ Failed to open {$gzFile}\n";
        continue;
    }

    $query = '';
    $lineCount = 0;
    $batchSize = 5000;

    while (!gzeof($gz)) {
        $line = gzgets($gz, 4096);
        if (substr($line, 0, 2) == '--' || trim($line) == '') continue;

        $query .= $line;
        $lineCount++;

        if (substr(trim($line), -1) == ';') {
            if (!$mysqli->query($query)) {
                echo "❌ Query error: " . $mysqli->error . "\n";
            }
            $query = '';
        }

        if ($lineCount % $batchSize == 0) {
            echo "   ✔️ Processed {$lineCount} lines...\n";
            $mysqli->ping();
        }
    }

    gzclose($gz);
    echo "✅ Table `$table` restored successfully.\n\n";
}

echo "🎉 All selected tables restored successfully into database: {$dbName}\n";
?>
