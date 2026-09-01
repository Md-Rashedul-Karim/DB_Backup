<?php
date_default_timezone_set('Asia/Dhaka');

## command ##
// php rd_b2mwap_auto_data_archive.php

$sourceDb = "rd_b2mwap_com";
$targetDb = "z_rd_b2mwap_com_archive";

/**
 *  You can change this array to archive more tables *  
 *  "table name" => ["date column", "id column"]
 */
// $tablesToArchive = [
//     "charge_logs"                           => ["created_at", "id"],
//     "renew_subscriptions"                   => ["created_at", "id"],
//     "hit_logs"                              => ["date", "id"],
// ];

$tablesToArchive = [
    "charge_logs"          => ["date_column" => "created_at", "id_column" => "id"],
    "renew_subscriptions" => ["date_column" => "created_at", "id_column" => "id"],
    "hit_logs"             => ["date_column" => "date", "time_column" => "time", "id_column" => "id"],
];

$batchSize = 10000;

$host = 'localhost';
$user = 'root';
$pass = 'B2M@db(sdp';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass);
} catch (mysqli_sql_exception $e) {
    die("❌ DB connection failed: " . $e->getMessage() . "\n");
}

// ১ দিন আগের ডাটা আর্কাইভ হবে
$archiveBefore = date("Y-m-d 23:59:59", strtotime("-90 days"));

echo "Archive Data Before: $archiveBefore\n";

foreach ($tablesToArchive as $table => $config) {

    $dateColumn = $config['date_column'];
    $timeColumn = $config['time_column'] ?? null;
    $idColumn   = $config['id_column'];

    echo "\n====================================\n";
    echo "Processing Table: $table (Using ID: $idColumn)\n";
    echo "====================================\n";

    while (true) {

        // =========================================================
        // Find oldest month
        // =========================================================

        if ($timeColumn) {

            $monthQuery = "
                SELECT
                    DATE_FORMAT(
                        CONCAT(`$dateColumn`, ' ', `$timeColumn`),
                        '%Y-%m'
                    ) AS ym,

                    MIN(
                        CONCAT(`$dateColumn`, ' ', `$timeColumn`)
                    ) AS min_datetime

                FROM `$sourceDb`.`$table`

                WHERE CONCAT(`$dateColumn`, ' ', `$timeColumn`)
                      < '$archiveBefore'

                GROUP BY ym
                ORDER BY ym ASC
                LIMIT 1
            ";

        } else {

            $monthQuery = "
                SELECT
                    DATE_FORMAT(`$dateColumn`, '%Y-%m') AS ym,
                    MIN(`$dateColumn`) AS min_datetime

                FROM `$sourceDb`.`$table`

                WHERE `$dateColumn` < '$archiveBefore'

                GROUP BY ym
                ORDER BY ym ASC
                LIMIT 1
            ";
        }

        $res = $conn->query($monthQuery);

        if (!$res || $res->num_rows == 0) {

            echo "✅ All months processed for table: $table\n";
            break;
        }

        $row = $res->fetch_assoc();

        $ym = $row['ym'];

        // =========================================================
        // Month start
        // =========================================================

        $fromDate = date(
            "Y-m-01 00:00:00",
            strtotime($row['min_datetime'])
        );

        // =========================================================
        // Month end
        // =========================================================

        $lastDayOfMonth = date(
            "Y-m-t 23:59:59",
            strtotime($fromDate)
        );

        $toDate = ($lastDayOfMonth < $archiveBefore)
            ? $lastDayOfMonth
            : $archiveBefore;

        $archiveTable = $table . "_" . str_replace("-", "_", $ym);

        echo "\n📂 Archiving Month: $ym [$fromDate to $toDate]\n";


        // =========================================================
        // Create archive DB/table
        // =========================================================

        $conn->query(
            "CREATE DATABASE IF NOT EXISTS `$targetDb`"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS `$targetDb`.`$archiveTable`
             LIKE `$sourceDb`.`$table`"
        );


        // =========================================================
        // Batch archive
        // =========================================================

        while (true) {

            $conn->begin_transaction();

            try {

                // -------------------------------------------------
                // Date + Time condition
                // -------------------------------------------------

                if ($timeColumn) {

                    $dateCondition = "
                        CONCAT(`$dateColumn`, ' ', `$timeColumn`)
                        BETWEEN '$fromDate' AND '$toDate'
                    ";

                } else {

                    $dateCondition = "
                        `$dateColumn`
                        BETWEEN '$fromDate' AND '$toDate'
                    ";
                }


                // -------------------------------------------------
                // Get IDs
                // -------------------------------------------------

                $idQuery = "
                    SELECT `$idColumn`

                    FROM `$sourceDb`.`$table`

                    WHERE $dateCondition

                    ORDER BY `$idColumn` ASC

                    LIMIT $batchSize
                ";

                $idsResult = $conn->query($idQuery);

                $ids = $idsResult->fetch_all(MYSQLI_ASSOC);

                if (empty($ids)) {

                    $conn->rollback();
                    break;
                }


                // -------------------------------------------------
                // Create ID list
                // -------------------------------------------------

                $idList = implode(
                    ",",
                    array_map(
                        'intval',
                        array_column($ids, $idColumn)
                    )
                );


                // -------------------------------------------------
                // INSERT archive
                // -------------------------------------------------

                $insertSQL = "
                    INSERT IGNORE INTO `$targetDb`.`$archiveTable`

                    SELECT *
                    FROM `$sourceDb`.`$table`

                    WHERE `$idColumn` IN ($idList)
                ";

                $conn->query($insertSQL);

                $inserted = $conn->affected_rows;


                // -------------------------------------------------
                // DELETE source
                // -------------------------------------------------

                $deleteSQL = "
                    DELETE FROM `$sourceDb`.`$table`

                    WHERE `$idColumn` IN ($idList)
                ";

                $conn->query($deleteSQL);

                $deleted = $conn->affected_rows;


                // -------------------------------------------------
                // Commit
                // -------------------------------------------------

                $conn->commit();

                echo "✔️  $archiveTable: Inserted $inserted | ";
                echo "$table: Deleted $deleted\n";


            } catch (Exception $e) {

                $conn->rollback();

                echo "❌ Error during batch: "
                    . $e->getMessage()
                    . "\n";

                break 2;
            }
        }

        echo "🏁 Month Archive Completed: $archiveTable\n";
    }


    // =============================================================
    // Maintenance
    // =============================================================

    echo "\n🏁 Table Maintenance for: $table\n";

    try {

        echo "\n🧪 CHECK `$sourceDb`.`$table` ...\n";

        if (!$conn->query(
            "CHECK TABLE `$sourceDb`.`$table`"
        )) {
            throw new Exception(
                "❌ Error checking table: " . $conn->error
            );
        }


        echo "\n🛠 REPAIR `$sourceDb`.`$table` ...\n";

        if (!$conn->query(
            "REPAIR TABLE `$sourceDb`.`$table`"
        )) {
            throw new Exception(
                "❌ Error repairing table: " . $conn->error
            );
        }

        echo "✅ REPAIR Done!\n";


        echo "\n🛠 Optimizing `$sourceDb`.`$table` ...\n";

        if (!$conn->query(
            "OPTIMIZE TABLE `$sourceDb`.`$table`"
        )) {
            throw new Exception(
                "❌ Error optimizing table: " . $conn->error
            );
        }

        echo "✅ Optimization Done!\n";


        echo "\n🛠 ANALYZE `$sourceDb`.`$table` ...\n";

        if (!$conn->query(
            "ANALYZE TABLE `$sourceDb`.`$table`"
        )) {
            throw new Exception(
                "❌ Error analyzing table: " . $conn->error
            );
        }

        echo "✅ ANALYZE Done!\n";


    } catch (Exception $e) {

        echo "❌ Error: "
            . $e->getMessage()
            . "\n";
    }
}

$conn->close();
echo "\n🚀 ALL PROCESS COMPLETED\n";