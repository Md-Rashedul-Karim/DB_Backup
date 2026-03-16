<?php

date_default_timezone_set('Asia/Dhaka');

## command ##
// php daily_archive.php

$sourceDb = "robi_sm";
$targetDb = "z_robi_sm_archive";


$tablesToArchive = [
    "sdp_send_sms_log"                  => "d_date",
    "sdp_broadcast_content"             => "date_added",
    "sdp_sequential_broadcast"          => "date_added",
];

$batchSize = 50000;

$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';
// $port = 3306;

// $conn = new mysqli($host, $user, $pass);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass);
    // $conn = new mysqli($host, $user, $pass, $port);
} catch (mysqli_sql_exception $e) {
    die("❌ DB connection failed: " . $e->getMessage() . "\n");
}

if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error . "\n");
}
// keep last 20 days
// $fromDate = "1970-01-01 00:00:00";
// $toDate   = date("Y-m-d 23:59:59", strtotime("-20 days"));

/* Archive Yesterday Data */
// $fromDate = date("Y-m-d 00:00:00", strtotime("yesterday"));
// $toDate   = date("Y-m-d 23:59:59", strtotime("yesterday"));

// custome date
$fromDate = "2026-03-12 00:00:00";
$toDate   = "2026-03-14 23:59:59";


$tableSuffix = date("Y_m", strtotime($toDate));


foreach ($tablesToArchive as $table => $dateColumn) {
    
    $archiveTable = $table . "_" . $tableSuffix;

    // ১. প্রথমে মোট কতগুলো রো আছে তা বের করে নেওয়া
    $countRes = $conn->query("SELECT COUNT(*) AS total FROM `$sourceDb`.`$table` WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'");
    $row = $countRes->fetch_assoc();
    $totalToProcess = $row['total'];

    echo "\n------------------------------------------------\n";
    echo "PROCESSING TABLE: $table\n";
    echo "Total Records Found: $totalToProcess\n";
    echo "------------------------------------------------\n";

    if ($totalToProcess == 0) {
        echo "No data found for this date range. Skipping...\n";
        continue;
    }

    /* Create archive table */
    $conn->query("CREATE TABLE IF NOT EXISTS `$targetDb`.`$archiveTable` LIKE `$sourceDb`.`$table` ");

    $totalArchived = 0;

    while (true) {
        // Insert Batch
        $insertSQL = "
            INSERT IGNORE INTO `$targetDb`.`$archiveTable`
            SELECT * FROM `$sourceDb`.`$table`
            WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
            LIMIT $batchSize
        ";

        $conn->query($insertSQL);
        $archived = $conn->affected_rows;
        

        if ($archived > 0) {
        // Archive-এ কিছু insert হয়নি মানে সব already আছে, এখন delete করো
            $conn->query("
                DELETE FROM `$sourceDb`.`$table`
                WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
                LIMIT $batchSize
            ");
            if ($conn->affected_rows == 0) break;
            continue;
        }

        // Delete Batch
        $conn->query("
            DELETE FROM `$sourceDb`.`$table`
            WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
            LIMIT $batchSize
        ");
        $deleted = $conn->affected_rows;

        if ($archived <= 0 && $deleted <= 0) break;

        $totalArchived += ($archived > 0) ? $archived : $deleted;
        
        // ২. প্রোগ্রেস পার্সেন্টেজ ক্যালকুলেশন
        $percentage = round(($totalArchived / $totalToProcess) * 100, 2);
        
        // টার্মিনালে এক লাইনে প্রোগ্রেস দেখানোর জন্য \r ব্যবহার করা হয়েছে
        echo "\rProgress: $percentage% | Archived: $totalArchived / $totalToProcess";
    }

    echo "\n------------------------------------------------\n";
    echo "DONE: $table archived successfully.\n";

        // $conn->query("OPTIMIZE TABLE `$sourceDb`.`$table` ");

        echo "⚙ Optimizing source table ... \n ";

      // ✅ CHECK TABLE
        echo "\n🧪 CHECK `$sourceDb`.`$table` ...\n";
        if (!$conn->query("CHECK TABLE  `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error checking table: " . $conn->error);
        }

        // ✅ REPAIR TABLE
        echo "\n🛠 REPAIR `$sourceDb`.`$table` ... \n";
        if (!$conn->query("REPAIR TABLE `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error repairing table: " . $conn->error);
        }
        echo "✅ REPAIR Done!\n";

        // ✅ OPTIMIZE TABLE
        echo "\n🛠 Optimizing `$sourceDb`.`$table` ... \n";
        if (!$conn->query("OPTIMIZE TABLE `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error optimizing table: " . $conn->error);
        }
        echo "✅ Optimization Done!\n";

        // ✅ ANALYZE TABLE
        echo "\n🛠 ANALYZE `$sourceDb`.`$table` ... \n";
        if (!$conn->query("ANALYZE TABLE `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error analyzing table: " . $conn->error);
        }
        echo "✅ ANALYZE Done!\n";
}

$conn->close();
echo "\nALL PROCESSES COMPLETED.\n";