<?php
/**
 * Multi-Database Chunked Backup (Separate Folder + Table .sql.gz)
 * Author: Md Rashedul Karim
 * Version: 3.0
 * run: php database_backup.php
 */

ini_set('memory_limit', '-1');
set_time_limit(0);
date_default_timezone_set('Asia/Dhaka');

// === Database Configuration ===
$dbHost = '192.168.20.14';
$dbUser = 'root';
$dbPass = '351f0*57034e1a025#'; 
$dbNames = ['bkashcms', 'blink_dob','blink_sdp']; // একাধিক ডাটাবেস

// === Backup Base Folder ===
$backupDir = __DIR__ . '/backups';
if (!file_exists($backupDir)) mkdir($backupDir, 0777, true);

$timestamp = date('Y-m-d_H-i-s');
$chunkSize = 50000; // প্রতি ব্যাচে রো সংখ্যা

// === Start Multi-Database Backup ===
foreach ($dbNames as $dbName) {

    echo "\n🚀 Starting backup of database: {$dbName}\n";

    // === Connect to Database ===
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) {
        echo "❌ Database connection failed for {$dbName}: " . $mysqli->connect_error . "\n";
        continue;
    }

    // === Database Backup Folder ===
    $dbFolder = "{$backupDir}/{$dbName}_backup_{$timestamp}";
    if (!file_exists($dbFolder)) mkdir($dbFolder, 0777, true);

    // === Get Tables ===
    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    while ($row = $result->fetch_row()) $tables[] = $row[0];

    foreach ($tables as $table) {
        echo "📦 Exporting table: {$table}\n";

        $file = "{$dbFolder}/{$table}.sql";
        $fp = fopen($file, 'w');

        // === Table Structure ===
        $createTableRes = $mysqli->query("SHOW CREATE TABLE `$table`");
        $createTableRow = $createTableRes->fetch_assoc();
        fwrite($fp, "-- -------------------------------------\n");
        fwrite($fp, "-- Table structure for table `$table`\n");
        fwrite($fp, "-- -------------------------------------\n\n");
        fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($fp, $createTableRow['Create Table'] . ";\n\n");

        // === Row Count ===
        $countRes = $mysqli->query("SELECT COUNT(*) AS total FROM `$table`");
        $countRow = $countRes->fetch_assoc();
        $totalRows = (int)$countRow['total'];
        echo "   ➜ Total rows: {$totalRows}\n";

        // === Dump Data in Chunks ===
        if ($totalRows > 0) {
            fwrite($fp, "-- Dumping data for table `$table`\n");
            for ($offset = 0; $offset < $totalRows; $offset += $chunkSize) {
                $query = "SELECT * FROM `$table` LIMIT $chunkSize OFFSET $offset";
                $dataRes = $mysqli->query($query);

                $values = [];
                while ($row = $dataRes->fetch_assoc()) {
                    $escaped = array_map([$mysqli, 'real_escape_string'], $row);
                    $values[] = "('" . implode("','", $escaped) . "')";
                }

                if (!empty($values)) {
                    $sql = "INSERT INTO `$table` VALUES " . implode(",\n", $values) . ";\n";
                    fwrite($fp, $sql);
                    echo "   ✔️ Wrote rows " . ($offset + 1) . " - " . min($offset + $chunkSize, $totalRows) . "\n";
                }
            }
        } else {
            echo "   ⚠️ No data found.\n";
        }

        fclose($fp);

        // === Compress Table File ===
        system("gzip -f \"{$file}\"");
        echo "   ✅ Table `$table` backup complete (.sql.gz)\n\n";
    }

    $mysqli->close();
    echo "✅ Database `$dbName` backup finished.\n";
}

echo "\n🎉 All databases backup completed successfully!\n";
?>
