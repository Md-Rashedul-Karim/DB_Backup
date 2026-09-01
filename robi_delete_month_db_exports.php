<?php

// Run command as: php /home/centos/robi_delete_month_db_exports.php

// =====================================================================
// REQUIRE
// =====================================================================
// ✅ PHPMailer-এর জন্য অবশ্যই Exception ফাইলটি সবার আগে লোড করতে হবে
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;


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
$database       = 'z_robi_sm_archive';
$baseOutputDir  = '/home/centos/'; // শেষে স্ল্যাশ নিশ্চিত করুন

$mysqldump      = '/usr/bin/mysqldump'; // বা পুরো পাথ দিন: /usr/bin/mysqldump
// $mysqldump      = 'mysqldump'; // বা পুরো পাথ দিন: /usr/bin/mysqldump
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
// TARGET Day
// =====================================================================

// Example:
// Today = 2026-05-01

// $tableSuffix = date("Y-m-d 23:59:59", strtotime("-1 days"));

// $targetMonthPattern = date('Y_m', strtotime('-1 month'));  // 1 month singular
// $targetMonthPattern = date('Y_m', strtotime('-2 months'));  // 2++ months plural

// $targetMonthPattern = date('Y_m', strtotime('first day of last month')); 

$targetMonthPattern = date('Y_m');  // $targetMonthPattern = date('Y_m', strtotime('first day of this month'));
$tableSuffix        = $database . '_' . $targetMonthPattern;

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
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug  = 0; // সমস্যা বুঝতে এখানে 2 দিতে পারেন
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        // ✅ আধুনিক PHPMailer-এ ৪৬৫ পোর্টের জন্য ENCRYPTION_SMTPS ব্যবহার করা ভালো
        $mail->SMTPSecure = (MAIL_PORT == 465) ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // ✅ FIX: SSL verify কিছু shared host-এ fail করে — disable করুন প্রয়োজনে
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

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

    } catch (\Exception $e) {
        $err = "❌ Email failed: " . $e->getMessage();
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

// ✅ FIX: added '%' before and after the pattern so it catches tables like 'table_name_2026_06'
// $searchPattern = '%' . date('Y_m', strtotime('-1 month')) . '%'; 
    $searchPattern = '%' . $targetMonthPattern . '%';

$result = $mysqli->query("SHOW TABLES LIKE " . "'" . $mysqli->real_escape_string($searchPattern) . "'");

while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

if (empty($tables)) {
    $msg = "No archive tables found for pattern: $searchPattern in database: $database";
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
    $maxId     = (int)($stats['max_val'] ?? 0);

    $exportSuccess = false;

    // --- মোড সিলেকশন ---
    // যদি ID ইন্টিজার না হয় বা অনেক গ্যাপ থাকে, mysqldump-ই ভালো। 
    // শুধুমাত্র মেমোরি ইস্যু এড়াতে বিগ ডাটা মোড ব্যবহার করুন।
    if ($totalRows > $bigTableThreshold && is_numeric($maxId)) {
        // ==========================================
        // BIG DATA MODE (Chunking using Dynamic PK)
        // ==========================================
        writeLog("⚠️ Big Table Detected ($totalRows rows). Mode: Chunking (PK: $primaryKey)");
        
        $fp = gzopen($gzFile, 'w9'); 
        $lastId = 0;
        
        while ($lastId < $maxId) {
            $query = "SELECT * FROM `$table` WHERE `$primaryKey` > $lastId ORDER BY `$primaryKey` ASC LIMIT $chunkSize";
            $result = $mysqli->query($query);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $values = array_map(function($val) use ($mysqli) {
                        return ($val === null) ? "NULL" : "'" . $mysqli->real_escape_string($val) . "'";
                    }, array_values($row));
                    gzwrite($fp, "INSERT INTO `$table` VALUES (" . implode(",", $values) . ");\n");
                    $lastId = $row[$primaryKey];
                }
                echo "Progress: ID $lastId / $maxId processed...\r";
            } else {
                break; // ডাটা না থাকলে লুপ শেষ
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