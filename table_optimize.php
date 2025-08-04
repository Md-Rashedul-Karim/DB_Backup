<?php
/**
 * optimize script
 * Usage:
 * php table_optimize.php <source_db> <main_table> 
 */

date_default_timezone_set('Asia/Dhaka');

// ✅ Only 2 arguments required: <source_db> <main_table>
if ($argc < 3) {
    echo "❌ Usage: php table_optimize.php <source_db> <main_table>\n";
    exit(1);
}

$sourceDb   = $argv[1];
$mainTable  = $argv[2];

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
    // ✅ CHECK TABLE
    echo "\n🧪 CHECK `$sourceDb`.`$mainTable` ...\n";
    if (!$conn->query("CHECK TABLE  `$sourceDb`.`$mainTable`")) {
        throw new Exception("❌ Error checking table: " . $conn->error);
    }

    // ✅ REPAIR TABLE
    echo "\n🛠 REPAIR `$sourceDb`.`$mainTable` ... \n";
    if (!$conn->query("REPAIR TABLE `$sourceDb`.`$mainTable`")) {
        throw new Exception("❌ Error repairing table: " . $conn->error);
    }
    echo "✅ REPAIR Done!\n";

    // ✅ OPTIMIZE TABLE
    echo "\n🛠 Optimizing `$sourceDb`.`$mainTable` ... \n";
    if (!$conn->query("OPTIMIZE TABLE `$sourceDb`.`$mainTable`")) {
        throw new Exception("❌ Error optimizing table: " . $conn->error);
    }
    echo "✅ Optimization Done!\n";

    // ✅ ANALYZE TABLE
    echo "\n🛠 ANALYZE `$sourceDb`.`$mainTable` ... \n";
    if (!$conn->query("ANALYZE TABLE `$sourceDb`.`$mainTable`")) {
        throw new Exception("❌ Error analyzing table: " . $conn->error);
    }
    echo "✅ ANALYZE Done!\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
