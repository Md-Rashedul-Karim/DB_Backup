<?php
/**
 * Chunk delete script (date range + id range)
 * Usage:
 * php table_chunk_delete.php <source_db> <main_table> <id_column> <date_column> <from_date> <to_date> <start_id> <end_id> [chunk_size]
 */

date_default_timezone_set('Asia/Dhaka');

if ($argc < 9) {
    echo "❌ Usage: php table_chunk_delete.php <source_db> <main_table> <id_column> <date_column> <from_date> <to_date> <start_id> <end_id> [chunk_size]\n";
    exit(1);
}

$sourceDb   = $argv[1];
$mainTable  = $argv[2];
$idColumn   = $argv[3];
$dateColumn = $argv[4];
$fromDate   = $argv[5];
$toDate     = $argv[6];
$startId    = (int)$argv[7];
$endId      = (int)$argv[8];
$chunkSize  = isset($argv[9]) ? (int)$argv[9] : 10000;

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
    // ✅ মোট কতগুলো রো ডিলিট হবে সেটা দেখানো হবে
    $countQuery = "
        SELECT COUNT(*) AS total
        FROM `$sourceDb`.`$mainTable`
        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
          AND `$idColumn` BETWEEN $startId AND $endId
    ";
    $countResult = $conn->query($countQuery);
    $totalRows = (int)$countResult->fetch_assoc()['total'];
    echo "🔢 Total rows to delete: $totalRows\n";

    $totalDeleted = 0;

    while (true) {
        // ✅ চাঙ্ক আকারে ডিলিট
        $deleteQuery = "
            DELETE FROM `$sourceDb`.`$mainTable`
            WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
              AND `$idColumn` BETWEEN $startId AND $endId
            ORDER BY `$idColumn` ASC
            LIMIT $chunkSize
        ";

        if (!$conn->query($deleteQuery)) {
            throw new Exception("❌ Error deleting chunk: " . $conn->error);
        }

        $deleted = $conn->affected_rows;
        if ($deleted === 0) {
            break; // ✅ সব ডিলিট হয়ে গেছে
        }

        $conn->commit();
        $totalDeleted += $deleted;

        $percent = $totalRows > 0 ? round(($totalDeleted / $totalRows) * 100, 2) : 100;
        echo "➡️  Deleted $totalDeleted / $totalRows rows ($percent%)\n";
    }

    echo "🗑 Total Deleted: $totalDeleted rows.\n";

    // ✅ টেবিল অপ্টিমাইজ
    echo "🛠 Optimizing `$sourceDb`.`$mainTable` ... ";
    if (!$conn->query("OPTIMIZE TABLE `$sourceDb`.`$mainTable`")) {
        throw new Exception("❌ Error optimizing table: " . $conn->error);
    }
    echo "✅ Optimization Done!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
