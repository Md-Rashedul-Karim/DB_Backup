<?php
date_default_timezone_set('Asia/Dhaka');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- কনফিগারেশন ---
$sourceHost = '192.168.20.14';
$sourceUser = 'root';
$sourcePass = '351f0*57034e1a025#';
$sourceDb   = "z_dob";

$targetHost = 'localhost';
$targetUser = 'root';
$targetPass = '968d413ffe75bf4a';
$targetDb   = "z_robi_sm_archive";

$tablesToArchive = [  
    "charge_logs_2026_01"               => ["created_at", "id"],  
    "sdp_broadcast_content_2026_03"     => ["date_added", "id"],
    "sdp_broadcast_content_202602"      => ["date_added", "id"],
    "sdp_sequential_broadcast_202602"   => ["date_added", "id"],
];


$batchSize = 5000; // INSERT...SELECT এ বড় batch safe

// ================= CONNECT =================
try {
    $sourceConn = new mysqli($sourceHost, $sourceUser, $sourcePass, $sourceDb);
    $targetConn = new mysqli($targetHost, $targetUser, $targetPass, $targetDb);

    $sourceConn->set_charset("utf8mb4");
    $targetConn->set_charset("utf8mb4");

    // Performance tuning for target connection
    $targetConn->query("SET foreign_key_checks = 0");
    $targetConn->query("SET unique_checks = 0");
    $targetConn->query("SET sql_log_bin = 0");

    // GLOBAL variable — permission থাকলে set হবে, না থাকলে skip
    try {
        $targetConn->query("SET GLOBAL innodb_flush_log_at_trx_commit = 2");
    } catch (mysqli_sql_exception $e) {
        echo "⚠️ Skipping innodb_flush_log_at_trx_commit: " . $e->getMessage() . "\n";
    }

    echo "✅ Connected Successfully\n";
} catch (mysqli_sql_exception $e) {
    die("❌ DB connection failed: " . $e->getMessage() . "\n");
}


// --- নির্দিষ্ট মাস এর কনফিগারেশন ---

$keepDays        = 10; // মাসের শেষ থেকে কত দিন আগ পর্যন্ত ডেটা রাখবে
$referenceMonths = ["2026-01", "2026-02", "2026-03"]; // Change this to your desired months

// ================= HELPER: Create Archive Table =================
function createArchiveTable(mysqli $sourceConn, mysqli $targetConn, string $sourceTable, string $archiveTable): void
{
    $res = $sourceConn->query("SHOW CREATE TABLE `$sourceTable`");
    $row = $res->fetch_assoc();
    $createSQL = $row['Create Table'];

    // Archive table নামে replace করো
    $createSQL = preg_replace(
        '/CREATE TABLE `' . preg_quote($sourceTable, '/') . '`/',
        "CREATE TABLE IF NOT EXISTS `$archiveTable`",
        $createSQL
    );

    $targetConn->query($createSQL);
    echo "🛠️ Archive table `$archiveTable` ready.\n";
}

// ================= HELPER: Get Columns =================
function getColumns(mysqli $conn, string $db, string $table): string
{
    $res = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' 
                         ORDER BY ORDINAL_POSITION");
    $cols = [];
    while ($row = $res->fetch_row()) {
        $cols[] = "`" . $row[0] . "`";
    }
    return implode(", ", $cols);
}


// ================= HELPER: Maintain Table =================
function maintainTable(mysqli $conn, string $db, string $table): void
{
    echo "\n🔧 Starting maintenance on `$db`.`$table`...\n";
    echo str_repeat("-", 50) . "\n";

    // ✅ CHECK TABLE
    echo "🧪 CHECK TABLE `$db`.`$table` ...\n";
    $res = $conn->query("CHECK TABLE `$db`.`$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "   [{$row['Msg_type']}] {$row['Msg_text']}\n";
        }
    } else {
        echo "   ❌ Error checking table: " . $conn->error . "\n";
    }

    // ✅ REPAIR TABLE
    echo "\n🛠️  REPAIR TABLE `$db`.`$table` ...\n";
    $res = $conn->query("REPAIR TABLE `$db`.`$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "   [{$row['Msg_type']}] {$row['Msg_text']}\n";
        }
        echo "   ✅ REPAIR Done!\n";
    } else {
        echo "   ❌ Error repairing table: " . $conn->error . "\n";
    }

    // ✅ OPTIMIZE TABLE
    echo "\n⚡ OPTIMIZE TABLE `$db`.`$table` ...\n";
    $res = $conn->query("OPTIMIZE TABLE `$db`.`$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "   [{$row['Msg_type']}] {$row['Msg_text']}\n";
        }
        echo "   ✅ OPTIMIZE Done!\n";
    } else {
        echo "   ❌ Error optimizing table: " . $conn->error . "\n";
    }

    // ✅ ANALYZE TABLE
    echo "\n📊 ANALYZE TABLE `$db`.`$table` ...\n";
    $res = $conn->query("ANALYZE TABLE `$db`.`$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "   [{$row['Msg_type']}] {$row['Msg_text']}\n";
        }
        echo "   ✅ ANALYZE Done!\n";
    } else {
        echo "   ❌ Error analyzing table: " . $conn->error . "\n";
    }

    echo str_repeat("-", 50) . "\n";
    echo "✅ Maintenance complete on `$db`.`$table`\n";
}
// ================= MAIN ARCHIVE LOOP =================

foreach ($referenceMonths as $referenceMonth) {
    $referenceDate = date("Y-m-t", strtotime($referenceMonth . "-01"));
    $archiveBefore = date("Y-m-d 00:00:00", strtotime("$referenceDate -{$keepDays} days"));

    echo "📅 Month           : $referenceMonth\n";
    echo "📅 Month last day  : $referenceDate\n";
    echo "📅 Archiving before: $archiveBefore\n";
    echo str_repeat("-", 40) . "\n";

    foreach ($tablesToArchive as $table => $config) {
        $dateColumn = $config[0];
        $idColumn   = $config[1];

        echo "\n====================================\n";
        echo "📋 Processing Table: $table\n";

        // Source table আছে কিনা চেক
        $checkTable = $sourceConn->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->num_rows === 0) {
            echo "⚠️ Table '$table' not found. Skipping...\n";
            continue;
        }

        // Source ও Target এর column list
        $columnList = getColumns($sourceConn, $sourceDb, $table);

        // Month-by-month loop
        while (true) {
            // সবচেয়ে পুরনো মাস খোঁজো
            $monthRes = $sourceConn->query("
                SELECT DATE_FORMAT(`$dateColumn`, '%Y-%m') AS ym
                FROM `$table`
                WHERE `$dateColumn` < '$archiveBefore'
                ORDER BY `$dateColumn` ASC
                LIMIT 1
            ");

            if (!$monthRes || $monthRes->num_rows === 0) {
                echo "✅ No more data to archive in '$table'.\n";
                break;
            }

            $ymRow        = $monthRes->fetch_assoc();
            $ym           = $ymRow['ym'];
            $fromDate     = "$ym-01 00:00:00";
            $lastDay      = date("Y-m-t", strtotime($fromDate));
            $toDate       = ("$lastDay 23:59:59" < $archiveBefore) ? "$lastDay 23:59:59" : $archiveBefore;
            $archiveTable = $table . "_" . str_replace("-", "_", $ym);

            echo "\n📂 Month: $ym | Range: $fromDate → $toDate\n";
            echo "🗄️ Target table: $archiveTable\n";

            // Archive table তৈরি করো
            createArchiveTable($sourceConn, $targetConn, $table, $archiveTable);

            $totalMoved = 0;

            // ===== BATCH LOOP (একই মাসের সব ডেটা batch-এ নাও) =====
            while (true) {
                // Batch এর জন্য ID range বের করো
                $rangeRes = $sourceConn->query("
                    SELECT MIN(`$idColumn`) AS min_id, MAX(`$idColumn`) AS max_id, COUNT(*) AS total
                    FROM (
                        SELECT `$idColumn` FROM `$table`
                        WHERE `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
                        ORDER BY `$idColumn` ASC
                        LIMIT $batchSize
                    ) AS batch
                ");

                $range = $rangeRes->fetch_assoc();

                if (!$range || $range['total'] == 0) {
                    echo "✅ Month $ym complete. Total moved: $totalMoved rows.\n";
                    break;
                }

                $minId = (int)$range['min_id'];
                $maxId = (int)$range['max_id'];

                // ===== TRANSACTION: INSERT then DELETE =====
                $targetConn->begin_transaction();
                $sourceConn->begin_transaction();

                try {
                    // INSERT INTO archive SELECT FROM source (সবচেয়ে দ্রুত পদ্ধতি)
                    // Cross-server হওয়ায় PHP দিয়ে করতে হবে — তবে SELECT/INSERT আলাদা রাখছি

                    // Source থেকে ডেটা fetch
                    $fetchRes = $sourceConn->query("
                        SELECT $columnList FROM `$table`
                        WHERE `$idColumn` BETWEEN $minId AND $maxId
                        AND `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
                    ");

                    $rows = $fetchRes->fetch_all(MYSQLI_NUM);

                    if (empty($rows)) {
                        $targetConn->rollback();
                        $sourceConn->rollback();
                        break;
                    }

                    // Multi-row INSERT তৈরি
                    $placeholders = [];
                    $colCount = count($rows[0]);
                    $singlePlaceholder = "(" . implode(",", array_fill(0, $colCount, "?")) . ")";

                    // Batch insert (৫০০টি করে sub-batch নিরাপত্তার জন্য)
                    $subBatchSize = 500;
                    $insertedCount = 0;

                    for ($i = 0; $i < count($rows); $i += $subBatchSize) {
                        $subRows = array_slice($rows, $i, $subBatchSize);
                        $valuePlaceholders = implode(", ", array_fill(0, count($subRows), $singlePlaceholder));

                        $insertSQL = "INSERT IGNORE INTO `$archiveTable` ($columnList) VALUES $valuePlaceholders";
                        $stmt = $targetConn->prepare($insertSQL);

                        // Type string তৈরি
                        $types = str_repeat("s", $colCount * count($subRows));
                        $flatValues = [];
                        foreach ($subRows as $r) {
                            foreach ($r as $val) {
                                $flatValues[] = $val;
                            }
                        }

                        $stmt->bind_param($types, ...$flatValues);
                        $stmt->execute();
                        $insertedCount += $targetConn->affected_rows;
                        $stmt->close();
                    }

                    // Source থেকে DELETE
                    $deleteStmt = $sourceConn->prepare("
                        DELETE FROM `$table`
                        WHERE `$idColumn` BETWEEN ? AND ?
                        AND `$dateColumn` BETWEEN '$fromDate' AND '$toDate'
                    ");
                    $deleteStmt->bind_param("ii", $minId, $maxId);
                    $deleteStmt->execute();
                    $deletedCount = $sourceConn->affected_rows;
                    $deleteStmt->close();

                    $targetConn->commit();
                    $sourceConn->commit();

                    $totalMoved += $insertedCount;
                    echo "  ✔️ Batch: inserted=$insertedCount, deleted=$deletedCount (ID: $minId → $maxId)\n";

                } catch (Exception $e) {
                    $targetConn->rollback();
                    $sourceConn->rollback();
                    echo "  ❌ Batch failed (ID: $minId → $maxId): " . $e->getMessage() . "\n";
                    break 2; // পুরো table skip করো
                }
            }
        }
        // ===== ✅ ARCHIVE শেষে SOURCE TABLE MAINTAIN করো =====
        maintainTable($sourceConn, $sourceDb, $table);
    }
}
// Restore settings
$targetConn->query("SET foreign_key_checks = 1");
$targetConn->query("SET unique_checks = 1");

$sourceConn->close();
$targetConn->close();

echo "\n🎉 Archive process completed.\n";