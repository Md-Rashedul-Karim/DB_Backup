<?php
date_default_timezone_set('Asia/Dhaka');

## command ##
// php blinkdob_auto_data_archive.php

$sourceDb = "blink_dob";
$targetDb = "z_blink_dob_archive";

/**
 *  You can change this array to archive more tables *  
 *  "table name" => ["date column", "id column"]
 */
$tablesToArchive = [
    "sdp_6d_raw_subs_payment"       => ["d_date", "id"],
    "sdp_6d_callback"               => ["d_date", "id"],
    "sdp_6d_raw_callback"           => ["d_date", "id"],
    "sdp_6d_raw_consent"            => ["d_date", "id"],
    "charge_log"                    => ["d_date", "log_id"],
];

$batchSize = 10000;

$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass);
} catch (mysqli_sql_exception $e) {
    die("❌ DB connection failed: " . $e->getMessage() . "\n");
}

// ১ দিন আগের ডাটা আর্কাইভ হবে
$archiveBefore = date("Y-m-d 23:59:59", strtotime("-30 days"));

echo "Archive Data Before: $archiveBefore\n";

foreach ($tablesToArchive as $table => $config) {
    $dateColumn = $config[0]; // d_date বা date_added
    $idColumn   = $config[1]; // id বা ids

    echo "\n====================================\n";
    echo "Processing Table: $table (Using ID: $idColumn)\n";
    echo "====================================\n";

    while (true) {
         // ✅ Get the oldest unarchived month
        $monthQuery = "
            SELECT
                DATE_FORMAT(`$dateColumn`,'%Y-%m') as ym,
                MIN(`$dateColumn`) as min_date
            FROM `$sourceDb`.`$table`
            WHERE `$dateColumn` < '$archiveBefore'
            GROUP BY ym
            ORDER BY ym ASC
            LIMIT 1
        ";

        $res = $conn->query($monthQuery);

        if (! $res || $res->num_rows == 0) {
            echo "✅ All months processed for table: $table\n";
            break;
        }

        $row      = $res->fetch_assoc();
        $ym       = $row['ym'];
        $fromDate = date("Y-m-01 00:00:00", strtotime($row['min_date'])); 

        $lastDayOfMonth = date("Y-m-t 23:59:59", strtotime($fromDate));
        $toDate         = ($lastDayOfMonth < $archiveBefore) ? $lastDayOfMonth : $archiveBefore;

        $archiveTable = $table . "_" . str_replace("-", "_", $ym);
        echo "\n📂 Archiving Month: $ym [$fromDate to $toDate]\n";

        // ✅ আর্কাইভ ডাটাবেজ ও টেবিল তৈরি করা
        $conn->query("CREATE DATABASE IF NOT EXISTS `$targetDb` ");
        $conn->query("CREATE TABLE IF NOT EXISTS `$targetDb`.`$archiveTable` LIKE `$sourceDb`.`$table` ");

        // ✅ ব্যাচ লুপ (একই মাসের ডাটা সরানো)
        while (true) {
            $conn->begin_transaction();
            try {
                // ডাইনামিক আইডি কলাম ব্যবহার
                $idQuery = "SELECT `$idColumn` FROM `$sourceDb`.`$table`
                            WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
                            ORDER BY `$idColumn` ASC
                            LIMIT $batchSize";
                
                $idsResult = $conn->query($idQuery);
                $ids       = $idsResult->fetch_all(MYSQLI_ASSOC);

                if (empty($ids)) {
                    $conn->rollback();
                    break; 
                }

                $idList = implode(",", array_column($ids, $idColumn));

                // ইনসার্ট করা
                $insertSQL = "INSERT IGNORE INTO `$targetDb`.`$archiveTable`
                              SELECT * FROM `$sourceDb`.`$table` WHERE `$idColumn` IN ($idList)";
                $conn->query($insertSQL);
                $inserted = $conn->affected_rows;

                // ডিলিট করা
                $deleteSQL = "DELETE FROM `$sourceDb`.`$table` WHERE `$idColumn` IN ($idList)";
                $conn->query($deleteSQL);
                $deleted = $conn->affected_rows;

                $conn->commit();
                echo "✔️  Batch: Inserted $inserted | Deleted $deleted\n";                

            } catch (Exception $e) {
                $conn->rollback();
                echo "❌ Error during batch: " . $e->getMessage() . "\n";
                break 2;
            }
        }
        echo "🏁 Month Archive Completed: $archiveTable\n";
    }

    // ✅ টেবিল মেইনটেন্যান্স
    echo "\n🏁 Table Mainenance for: $table\n";

    try {
        // ✅ CHECK TABLE
        echo "\n🧪 CHECK `$sourceDb`.`$table` ...\n";
        if (! $conn->query("CHECK TABLE  `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error checking table: " . $conn->error);
        }

        // ✅ REPAIR TABLE
        echo "\n🛠 REPAIR `$sourceDb`.`$table` ... \n";
        if (! $conn->query("REPAIR TABLE `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error repairing table: " . $conn->error);
        }
        echo "✅ REPAIR Done!\n";

        // ✅ OPTIMIZE TABLE
        echo "\n🛠 Optimizing `$sourceDb`.`$table` ... \n";
        if (! $conn->query("OPTIMIZE TABLE `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error optimizing table: " . $conn->error);
        }
        echo "✅ Optimization Done!\n";

        // ✅ ANALYZE TABLE
        echo "\n🛠 ANALYZE `$sourceDb`.`$table` ... \n";
        if (! $conn->query("ANALYZE TABLE `$sourceDb`.`$table`")) {
            throw new Exception("❌ Error analyzing table: " . $conn->error);
        }
        echo "✅ ANALYZE Done!\n";

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

$conn->close();
echo "\n🚀 ALL PROCESS COMPLETED\n";