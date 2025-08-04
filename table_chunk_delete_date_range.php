<?php
/**
 * Chunk delete script (fixed 10000 rows per batch)
 * Usage:
 * php table_chunk_delete.php <source_db> <main_table> <id_column> <date_column> <from_date> <to_date> [chunk_size]
 */

date_default_timezone_set('Asia/Dhaka');

if ($argc < 7) {
    echo "❌ Usage: php table_chunk_delete.php <source_db> <main_table> <id_column> <date_column> <from_date> <to_date> [chunk_size]\n";
    exit(1);
}

$sourceDb   = $argv[1];
$mainTable  = $argv[2];
$idColumn   = $argv[3];
$dateColumn = $argv[4];
$fromDate   = $argv[5];
$toDate     = $argv[6];
$chunkSize  = isset($argv[7]) ? (int)$argv[7] : 10000; // ✅ Default 10000

$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . PHP_EOL);
}
$conn->set_charset('utf8mb4');
$conn->autocommit(true); // ✅ Autocommit চালু করে দাও

try {
    // ✅ মোট কতগুলো রো আছে সেটা দেখানো হবে
    $countQuery = "
        SELECT COUNT(*) AS total
        FROM `$sourceDb`.`$mainTable`
        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
    ";
    $countResult = $conn->query($countQuery);
    $totalRows = (int)$countResult->fetch_assoc()['total'];
    echo "🔢 Total rows to delete: $totalRows\n";

    $totalDeleted = 0;

    while (true) {
        // ✅ ১০,০০০ করে ডিলিট
        $deleteQuery = "
            DELETE FROM `$sourceDb`.`$mainTable`
            WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
            ORDER BY `$idColumn` ASC
            LIMIT $chunkSize
        ";

        if (!$conn->query($deleteQuery)) {
            throw new Exception("❌ Error deleting chunk: " . $conn->error);
        }

        $deleted = $conn->affected_rows;
        if ($deleted === 0) {
            break; // ✅ আর কোনো রো নাই, লুপ বন্ধ
        }

        // $conn->commit();
        $totalDeleted += $deleted;

        $percent = $totalRows > 0 ? round(($totalDeleted / $totalRows) * 100, 2) : 100;
        echo "➡️  Deleted $totalDeleted / $totalRows rows ($percent%)\n";
    }

    echo "🗑 Total Deleted: $totalDeleted rows.\n";

    // ✅ টেবিল Optimizing REPAIR ANALYZE করা হচ্ছে
    // echo "\n 🛠 REPAIR `$sourceDb`.`$mainTable` ... \n";
    // if (!$conn->query("REPAIR TABLE `$sourceDb`.`$mainTable`")) {
    //     throw new Exception("❌ Error optimizing table: " . $conn->error);
    // }

    // echo "\n 🛠 Optimizing `$sourceDb`.`$mainTable` ... \n";
    // if (!$conn->query("OPTIMIZE TABLE `$sourceDb`.`$mainTable`")) {
    //     throw new Exception("❌ Error optimizing table: " . $conn->error);
    // }  

    // echo "\n 🛠 ANALYZE `$sourceDb`.`$mainTable` ... \n";
    // if (!$conn->query("ANALYZE TABLE `$sourceDb`.`$mainTable`")) {
    //     throw new Exception("❌ Error optimizing table: " . $conn->error);
    // }

    // echo "\n ✅ REPAIR Done!\n ✅ Optimization Done!\n ✅ ANALYZE Done!\n";

 

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
