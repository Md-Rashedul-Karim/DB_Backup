<?php
/**
 * Optimized chunk archive script using WHERE id > lastId
 * 
 * Usage:
 * php table_chunk_archive_id.php <source_db> <target_db> <main_table> <id_column> <date_column> <from_date> <to_date> <table_suffix> [chunk_size]
 */

date_default_timezone_set('Asia/Dhaka');

// ✅ Check CLI arguments
if ($argc < 9) {
    echo "❌ Usage: php table_chunk_archive_id.php <source_db> <target_db> <main_table> <id_column> <date_column> <from_date> <to_date> <table_suffix> [chunk_size]\n";
    exit(1);
}

// ✅ Assign arguments properly
$sourceDb    = $argv[1];
$targetDb    = $argv[2];
$mainTable   = $argv[3];
$idColumn    = $argv[4];              // e.g., id or log_id
$dateColumn  = $argv[5];              // e.g., date_added or d_date
$fromDate    = $argv[6];
$toDate      = $argv[7];
$tableSuffix = $argv[8];
$chunkSize   = isset($argv[9]) ? (int)$argv[9] : 100;

$newTable = $mainTable . '_' . $tableSuffix;

// ✅ DB credentials
$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . PHP_EOL);
}
$conn->set_charset('utf8mb4');
$conn->autocommit(false);

try {
    // ✅ Step 1: Create target table
    echo "📦 Creating table `$targetDb`.`$newTable` ... ";
    $createQuery = "CREATE TABLE IF NOT EXISTS `$targetDb`.`$newTable` LIKE `$sourceDb`.`$mainTable`";
    if (!$conn->query($createQuery)) {
        throw new Exception("❌ Error creating table: " . $conn->error);
    }
    echo "✅\n";

    // ✅ Step 2: Count total rows
    $countQuery = "
        SELECT COUNT(*) AS total
        FROM `$sourceDb`.`$mainTable`
        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
    ";
    $countResult = $conn->query($countQuery);
    if (!$countResult) {
        throw new Exception("❌ Error counting rows: " . $conn->error);
    }
    $totalRows = (int)$countResult->fetch_assoc()['total'];
    echo "🔢 Total rows to archive: $totalRows\n";

    // ✅ Step 3: Get initial lastId (1 less than minimum)
    $minIdQuery = "
        SELECT MIN(`$idColumn`) AS firstId
        FROM `$sourceDb`.`$mainTable`
        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
    ";
    $minIdResult = $conn->query($minIdQuery);
    if (!$minIdResult) {
        throw new Exception("❌ Error finding first ID: " . $conn->error);
    }
    $firstId = (int)$minIdResult->fetch_assoc()['firstId'];
    $lastId  = $firstId - 1;

    // ✅ Step 4: Chunk loop
    $totalInserted = 0;
    while (true) {
        $insertQuery = "
            INSERT INTO `$targetDb`.`$newTable`
            SELECT *
            FROM `$sourceDb`.`$mainTable`
            WHERE `$idColumn` > $lastId
              AND `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
            ORDER BY `$idColumn` ASC
            LIMIT $chunkSize
        ";

        if (!$conn->query($insertQuery)) {
            throw new Exception("❌ Error inserting chunk: " . $conn->error);
        }

        $inserted = $conn->affected_rows;
        if ($inserted === 0) {
            break; // ✅ No more rows to insert
        }

        $conn->commit();
        $totalInserted += $inserted;

        // Update lastId
        $maxIdResult = $conn->query("SELECT MAX(`$idColumn`) AS maxId FROM `$targetDb`.`$newTable`");
        $lastId = (int)$maxIdResult->fetch_assoc()['maxId'];

        $percent = round(($totalInserted / $totalRows) * 100, 2);
        echo "➡️  Inserted $totalInserted / $totalRows rows ($percent%)\n";
    }

    echo "🎉 Archive complete: `$targetDb`.`$newTable` with $totalInserted rows.\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
