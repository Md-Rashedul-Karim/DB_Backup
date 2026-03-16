<?php
date_default_timezone_set('Asia/Dhaka');

if ($argc < 7) {
    echo "❌ Usage: php same_month_archive.php <source_db> <target_db> <main_table> <date_column> <from_date> <to_date>\n";
    echo "Example: php same_month_archive.php robi_sm z_robi_sm_archive sdp_send_sms_log d_date '2026-01-21' '2026-01-31 23:59:59'\n";
    exit(1);
}

$sourceDb   = $argv[1];
$targetDb   = $argv[2];
$mainTable  = $argv[3];
$dateColumn = $argv[4];
$fromDate   = $argv[5];
$toDate     = $argv[6];

if (!strtotime($fromDate) || !strtotime($toDate)) {
    die("❌ Invalid date format. Use YYYY-MM-DD\n");
}

$tableSuffix = date('Y_m', strtotime($fromDate));
$newTable = $mainTable . '_' . $tableSuffix;

$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . PHP_EOL);
}

$conn->autocommit(false);

try {

    // ---------------------------
    // STEP 1: Check source table
    // ---------------------------
    $checkSource = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM information_schema.tables 
        WHERE table_schema = '$sourceDb'
        AND table_name = '$mainTable'
    ");

    $sourceRow = $checkSource->fetch_assoc();

    if ($sourceRow['cnt'] == 0) {
        throw new Exception("Source table `$sourceDb.$mainTable` does NOT exist!");
    }

    // ---------------------------
    // STEP 2: Check archive table
    // ---------------------------
    $checkArchive = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM information_schema.tables 
        WHERE table_schema = '$targetDb'
        AND table_name = '$newTable'
    ");

    $archiveRow = $checkArchive->fetch_assoc();

    if ($archiveRow['cnt'] == 0) {

        echo "📦 Creating archive table `$targetDb.$newTable` ... ";

        $createQuery = "
            CREATE TABLE `$targetDb`.`$newTable`
            LIKE `$sourceDb`.`$mainTable`
        ";

        if (!$conn->query($createQuery)) {
            throw new Exception("Create failed: " . $conn->error);
        }

        echo "✅ Created\n";

    } else {
        echo "ℹ Archive table already exists. Skipping create.\n";
    }

    // ---------------------------
    // STEP 3: Insert archive data
    // ---------------------------
    echo "📤 Archiving data ($fromDate → $toDate) ... ";

    $insertQuery = "
        INSERT INTO `$targetDb`.`$newTable`
        SELECT *
        FROM `$sourceDb`.`$mainTable`
        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
    ";

    if (!$conn->query($insertQuery)) {
        throw new Exception("Insert failed: " . $conn->error);
    }

    $inserted = $conn->affected_rows;

    echo "✅ $inserted rows archived\n";

    // ---------------------------
    // STEP 4: Delete from source
    // ---------------------------
    
    echo "🗑 Deleting archived data from source table ... ";

    $deleteQuery = "
        DELETE FROM `$sourceDb`.`$mainTable`
        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
    ";

    if (!$conn->query($deleteQuery)) {
        throw new Exception("Delete failed: " . $conn->error);
    }

    $deleted = $conn->affected_rows;

    echo "✅ $deleted rows deleted\n";

    $conn->commit();

    // ---------------------------
    // STEP 5: Optimize source table
    // ---------------------------
    echo "⚙ Optimizing source table ... ";

       
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
    echo "❌ ERROR: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}

