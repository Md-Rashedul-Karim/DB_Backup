<?php

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

date_default_timezone_set('Asia/Dhaka');
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);

// ======================================================
// CONFIGURATION
// ======================================================
$host           = 'localhost';
$user           = 'root';
$password       = '968d413ffe75bf4a';
$database       = 'z_bdg_b2mwap_com_archive';
$baseOutputDir  = '/home/centos/'; 

$mysqldump      = '/usr/bin/mysqldump';
$gzipBin        = '/bin/gzip';

// ======================================================
// MAIL CONFIG (Constants)
// ======================================================
define('MAIL_HOST', 'shared93.accountservergroup.com');
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'rashedul.karim@b2m-tech.com');
define('MAIL_PASSWORD', 'IDM1RGZga');
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_FROM_ADDRESS', 'rashedul.karim@b2m-tech.com');
define('MAIL_FROM_NAME', 'DB Export/Archive Report');

// ======================================================
// RECIPIENTS (ভুল ইমেইলগুলো রিমুভ করা হয়েছে)
// ======================================================
$emailTo = [
    ['rashedul.karim@b2m-tech.com', 'Rashedul Karim'],
];

$emailCc = [
    ['admin@b2m-tech.com', 'Admin'],
];

// ======================================================
// HELPERS
// ======================================================
function writeLog($message, $outputDir = null) {
    $time = date('Y-m-d H:i:s');
    $logMsg = "[$time] $message\n";
    if ($outputDir) {
        file_put_contents($outputDir . 'export.log', $logMsg, FILE_APPEND);
    }
    echo $logMsg;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function sendStatusEmail($subject, $content, $toList, $ccList = []) {
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
        foreach ($toList as [$address, $name]) $mail->addAddress($address, $name);
        foreach ($ccList as [$address, $name]) $mail->addCC($address, $name);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $content;

        $mail->send();
        writeLog("📧 Report email sent successfully.");
    } catch (MailException $e) {
        writeLog("❌ Email failed: {$mail->ErrorInfo}");
    }
}

// ======================================================
// MAIN PROCESS
// ======================================================

$tableSuffix = date('Y_m', strtotime('-2 month'));
$outputDir = $baseOutputDir . $tableSuffix . '/';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

writeLog("🚀 MONTHLY ARCHIVE EXPORT STARTED");
writeLog("📅 Export Suffix : $tableSuffix");

$summary = [];
$summaryLines = [];

try {
    $mysqli = new mysqli($host, $user, $password, $database);
    if ($mysqli->connect_error) throw new Exception($mysqli->connect_error);
    $mysqli->set_charset("utf8mb4");
    writeLog("✅ Database Connected");

    // Get Tables
    $tables = [];
    $result = $mysqli->query("SHOW TABLES LIKE '%\\_$tableSuffix'");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    if (empty($tables)) {
        $msg = "No archive tables found for suffix: $tableSuffix";
        writeLog("⚠️ $msg", $outputDir);
        sendStatusEmail("DB Export Alert: No Tables Found", $msg, $emailTo, $emailCc);
        exit;
    }

    // Export Logic
    $bigTableThreshold = 100000;
    $chunkSize = 10000;

    foreach ($tables as $table) {
        writeLog("--- Processing: $table ---", $outputDir);
        $sqlFile = $outputDir . $table . '.sql';
        $gzFile  = $sqlFile . '.gz';

        if (file_exists($sqlFile)) unlink($sqlFile);
        if (file_exists($gzFile))  unlink($gzFile);

        $pkResult = $mysqli->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        $pkRow = $pkResult->fetch_assoc();
        $primaryKey = $pkRow['Column_name'] ?? 'id';

        $stats = $mysqli->query("SELECT COUNT(*) as total, MAX(`$primaryKey`) as max_val FROM `$table`")->fetch_assoc();
        $totalRows = (int)$stats['total'];
        $maxVal    = $stats['max_val'] ?? 0;

        $exportSuccess = false;

        if ($totalRows > $bigTableThreshold) {
            // Chunking Mode
            $fp = gzopen($gzFile, 'w9');
            $currentVal = 0;
            while ($currentVal <= $maxVal) {
                $query = "SELECT * FROM `$table` WHERE `$primaryKey` > $currentVal AND `$primaryKey` <= ($currentVal + $chunkSize)";
                $res = $mysqli->query($query);
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        $values = array_map(function($val) use ($mysqli) {
                            return ($val === null) ? "NULL" : "'" . $mysqli->real_escape_string($val) . "'";
                        }, array_values($row));
                        gzwrite($fp, "INSERT INTO `$table` VALUES (" . implode(",", $values) . ");\n");
                    }
                }
                $currentVal += $chunkSize;
            }
            gzclose($fp);
            $exportSuccess = (file_exists($gzFile) && filesize($gzFile) > 0);
        } else {
            // Single Query Mode
            $query = "SELECT * FROM `$table`";
            $res = $mysqli->query($query);
            if ($res && $res->num_rows > 0) {
                $fp = gzopen($gzFile, 'w9');
                while ($row = $res->fetch_assoc()) {
                    $values = array_map(function($val) use ($mysqli) {
                        return ($val === null) ? "NULL" : "'" . $mysqli->real_escape_string($val) . "'";
                    }, array_values($row));
                    gzwrite($fp, "INSERT INTO `$table` VALUES (" . implode(",", $values) . ");\n");
                }
                gzclose($fp);
                $exportSuccess = (file_exists($gzFile) && filesize($gzFile) > 0);
            }
        }

        if ($exportSuccess) {
            $summary[$table] = 'SUCCESS';
            writeLog("✅ Exported: $table", $outputDir);
        } else {
            $summary[$table] = 'FAILED';
            writeLog("❌ FAILED: $table", $outputDir);
        }        
    }

} catch (Exception $e) {
    $msg = "❌ Error: " . $e->getMessage();
    writeLog($msg);
    sendStatusEmail("CRITICAL: Archive Failed", $msg, $emailTo);
    exit;
}

// ======================================================
// SEND FINAL SUMMARY
// ======================================================
foreach ($summary as $tbl => $status) {
    $summaryLines[] = "  [$status] $tbl";
}

$emailBody  = "Hello,\n\nArchive export for suffix '$tableSuffix' finished.\n\n";
$emailBody .= "--- SUMMARY ---\n" . implode("\n", $summaryLines) . "\n\n";
$emailBody .= "Database: " . $database . "\n";
$emailBody .= "Output Directory: " . $outputDir . "\n";
$emailBody .= "Server: " . php_uname('n') . "\nTime: " . date('Y-m-d H:i:s');

sendStatusEmail("DB Archive Report: $tableSuffix", $emailBody, $emailTo, $emailCc);

$mysqli->close();
writeLog("🎉 ALL DONE");