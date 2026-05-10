<?php

// Run command as: php /home/centos/bdg_b2mwap_delete_month_db_exports.php

// =====================================================================
// REQUIRE
// =====================================================================
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

date_default_timezone_set('Asia/Dhaka');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1); // 0 = false, 1 = true
ini_set('max_execution_time', 0); // 0 = unlimited




// =====================================================================
// CONFIG
// =====================================================================

$host           = 'localhost';
$user           = 'root';
$password       = '968d413ffe75bf4a';
$database       = 'z_bdgamers_club_archive';
$baseOutputDir  = '/home/centos/'; // শেষে স্ল্যাশ নিশ্চিত করুন

$mysqldump      = '/usr/bin/mysqldump';
$gzipBin        = '/bin/gzip';


// ======================================================
// MAIL CONFIG
// ======================================================
define('MAIL_HOST', 'shared93.accountservergroup.com');
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'rashedul.karim@b2m-tech.com');
define('MAIL_PASSWORD', 'IDM1RGZga');
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_FROM_ADDRESS', 'rashedul.karim@b2m-tech.com');
define('MAIL_FROM_NAME', 'DB Export/Archive Report');

$emailTo = [
    ['rashedul.karim@b2m-tech.com',  'Rashedul Karim'],
    ['manager@b2m-tech.com',         'Manager'],
];

$emailCc = [
    ['admin@b2m-tech.com',           'Admin'],
    ['devops@b2m-tech.com',          'DevOps Team'],
];
// $emailBcc = [
//     ['rashedul.karim@b2m-tech.com',  'Rashedul Karim'],
// ];

// =====================================================================
// TARGET MONTH (2 MONTH AGO)
// =====================================================================

// Example:
// Today = 2026-05-01
// Target = 2026_03
// $tableSuffix = date("Y-m-d 23:59:59", strtotime("-1 days"));
$tableSuffix = date('Y_m', strtotime('-2 month'));

echo "====================================================\n";
echo "🚀 EXPORT + DELETE PROCESS STARTED\n";
echo "====================================================\n";
echo "📅 TARGET SUFFIX : $tableSuffix\n";


// =====================================================================
// OUTPUT DIRECTORY
// =====================================================================

$outputDir = $baseOutputDir . $tableSuffix . '/';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
echo "📁 OUTPUT DIRECTORY : $outputDir\n";


// =====================================================================
// LOG FILE
// =====================================================================

$logFile = $outputDir . 'export_delete.log';

function writeLog($message)
{
    global $logFile;
    $time = date('Y-m-d H:i:s');
    file_put_contents(
        $logFile,
        "[$time] $message\n",
        FILE_APPEND
    );
}


// ======================================================
// EMAIL HELPER
// ======================================================

function sendStatusEmail(string $subject, string $content, array $toList = [], array $ccList = []): void
// function sendStatusEmail(string $subject, string $content, array $toList, array $ccList = [], array $bccList = []): void
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

         if (empty($toList)) {
            $mail->addAddress(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        } else {         
            // ✅ TO — multiple recipients
            foreach ($toList as [$address, $name]) {
                $mail->addAddress($address, $name);
            }
        }
        

        // ✅ CC — multiple recipients
        foreach ($ccList as [$address, $name]) {
            $mail->addCC($address, $name);
        }
        // ✅ BCC
        // foreach ($bccList as [$address, $name]) {
        //     $mail->addBCC($address, $name);
        // }

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $content;

        $mail->send();
        echo "📧 Report email sent successfully.\n";
        writeLog("Email sent: $subject");

    } catch (MailException $e) {
        $err = "❌ Email failed: {$mail->ErrorInfo}";
        echo $err . "\n";
        writeLog($err);
    }
}


// =====================================================================
// HELPER
// =====================================================================

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}


// ======================================================
// DB CONNECTION 
// ======================================================
try {
    $mysqli = new mysqli($host, $user, $password, $database);
    if ($mysqli->connect_errno) {
        throw new \Exception($mysqli->connect_error); // ✅ global \Exception
    }
    $mysqli->set_charset('utf8mb4');
    echo "✅ Database Connected\n";
} catch (\Exception $e) { // ✅ global \Exception
    $msg = "❌ DB Connection Failed: " . $e->getMessage();
    writeLog($msg);
    sendStatusEmail("CRITICAL: DB Connection Failed", $msg);
    die($msg . "\n");
}

// ======================================================
// GET TARGET TABLES
// ======================================================
$tables = [];
$result = $mysqli->query("SHOW TABLES LIKE '%\\_$tableSuffix'");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

if (empty($tables)) {
    $msg = "No archive tables found for suffix: $tableSuffix";
    writeLog($msg);
    sendStatusEmail("DB Export Alert: No Tables Found", $msg);
    $mysqli->close();
    exit("❌ $msg\n");
}

echo "📂 Found " . count($tables) . " archive tables\n";
writeLog("Found " . count($tables) . " archive tables.");

// ======================================================
// EXPORT LOOP
// ======================================================
$summary = [];
$bigTableThreshold = 100000; // ১০০,০০০ এর বেশি রো থাকলে Chunking মোড চালু হবে
$chunkSize = 10000;        // প্রতিবারে কতগুলো রো প্রসেস হবে

echo "📂 FOUND " . count($tables) . " TABLES\n";

foreach ($tables as $table) {
    echo "\n--- Processing: $table ---\n";
    writeLog("Processing: $table");

    $sqlFile = $outputDir . $table . '.sql';
    $gzFile  = $sqlFile . '.gz';

    // ১. পুরনো ফাইল ডিলিট (Cleanup)
    if (file_exists($sqlFile)) unlink($sqlFile);
    if (file_exists($gzFile))  unlink($gzFile);

    // ২. ডাইনামিক ভাবে Primary Key খুঁজে বের করা
    $pkResult = $mysqli->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    $pkRow = $pkResult->fetch_assoc();
    $primaryKey = isset($pkRow['Column_name']) ? $pkRow['Column_name'] : 'id';

    // ৩. টেবিল স্ট্যাটাস চেক (Row Count & Max Value of PK)
    $stats = $mysqli->query("SELECT COUNT(*) as total, MAX(`$primaryKey`) as max_val FROM `$table`")->fetch_assoc();
    $totalRows = (int)$stats['total'];
    $maxVal    = $stats['max_val'] ?? 0;

    $exportSuccess = false;

    // --- মোড সিলেকশন ---
    if ($totalRows > $bigTableThreshold) {
        // ==========================================
        // BIG DATA MODE (Chunking using Dynamic PK)
        // ==========================================
        writeLog("⚠️ Big Table Detected ($totalRows rows). Mode: Chunking (PK: $primaryKey)");
        
        $fp = gzopen($gzFile, 'w9'); 
        $currentVal = 0;
        
        while ($currentVal <= $maxVal) {
            $query = "SELECT * FROM `$table` WHERE `$primaryKey` > $currentVal AND `$primaryKey` <= ($currentVal + $chunkSize)";
            $result = $mysqli->query($query);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $values = array_map(function($val) use ($mysqli) {
                        return ($val === null) ? "NULL" : "'" . $mysqli->real_escape_string($val) . "'";
                    }, array_values($row));
                    gzwrite($fp, "INSERT INTO `$table` VALUES (" . implode(",", $values) . ");\n");
                }
                $currentVal += $chunkSize;
                echo "Progress: $currentVal / $maxVal rows processed...\r";
            } else {
                $currentVal += $chunkSize;
            }
        }
        gzclose($fp);
        $exportSuccess = (file_exists($gzFile) && filesize($gzFile) > 0);

    } else {
        // ==========================================
        // STANDARD MODE (mysqldump)
        // ==========================================
        writeLog("Standard Mode: Exporting using mysqldump...");
        
        $dumpCmd = sprintf(
            '%s --single-transaction --quick --lock-tables=false --skip-comments --no-tablespaces --complete-insert --default-character-set=utf8mb4 -h%s -u%s -p%s %s %s > %s 2>> %s',
            escapeshellcmd($mysqldump),
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($table),
            escapeshellarg($sqlFile),
            escapeshellarg($logFile)
        );
        
        exec($dumpCmd, $dumpOutput, $dumpCode);

        if ($dumpCode === 0 && file_exists($sqlFile) && filesize($sqlFile) > 0) {
            echo "✅ SQL Export OK\n";
            exec(sprintf('%s -f %s', escapeshellcmd($gzipBin), escapeshellarg($sqlFile)));
            $exportSuccess = (file_exists($gzFile) && filesize($gzFile) > 0);
        }
    }

    // --- ৪. ভেরিফিকেশন এবং সেফ ডিলিট ---
    if ($exportSuccess) {
        // Gzip Integrity Test
        exec("gzip -t " . escapeshellarg($gzFile), $verOut, $verCode);

        if ($verCode === 0) {
            $size = formatBytes(filesize($gzFile));
            echo "✅ Export & GZIP OK: $size\n";
            
            // Safe Delete Logic
            $mysqli->query("TRUNCATE TABLE `$table` "); 
            if ($mysqli->query("DROP TABLE `$table` ")) {
                echo "🗑️ Table Deleted: $table\n";
                writeLog("SUCCESS: $table ($size) archived and dropped.");
                $summary[$table] = "OK ($size)";
            } else {
                echo "⚠️ Drop Failed: $table\n";
                writeLog("ERROR: $table exported but DROP failed: " . $mysqli->error);
                $summary[$table] = "EXPORTED ONLY";
            }
        } else {
            echo "❌ GZIP Corrupted: $table\n";
            writeLog("CRITICAL: Gzip verification failed for $table. Table preserved.");
            $summary[$table] = "CORRUPTED";
        }
    } else {
        echo "❌ Export FAILED: $table\n";
        writeLog("FAILED: Export process failed for $table.");
        $summary[$table] = "FAILED";
    }
}

// ======================================================
// CLOSE DB
// ======================================================
$mysqli->close();
writeLog("EXPORT PROCESS COMPLETED");

// ======================================================
// EMAIL SUMMARY
// ======================================================
$summaryLines = [];
foreach ($summary as $tbl => $status) {
    $summaryLines[] = "  [$status] $tbl";
}

$emailBody  = "Hello,\n\n";
$emailBody .= "Archive export for suffix '$tableSuffix' has finished.\n\n";
$emailBody .= "--- RESULT SUMMARY ---\n";
$emailBody .= implode("\n", $summaryLines) . "\n";
$emailBody .= "Database name : $database ---\n";
$emailBody .= "Archive path    : $baseOutputDir ---\n";
$emailBody .= "----------------------\n\n";
$emailBody .= "--- FULL LOG ---\n";
$emailBody .= (file_exists($logFile) ? file_get_contents($logFile) : "Log not found.");
$emailBody .= "\n----------------\n";
$emailBody .= "Server: " . php_uname('n') . "\n";
$emailBody .= "Time  : " . date('Y-m-d H:i:s') . "\n";

sendStatusEmail(
    "DB Archive Report: $tableSuffix (" . date('Y-m-d') . ")",
    $emailBody,
    $emailTo,  // TO
    $emailCc,   // CC
    // $emailBcc  // BCC
);

echo "\n==================================================\n";
echo "🎉 ALL DONE\n";
echo "==================================================\n";