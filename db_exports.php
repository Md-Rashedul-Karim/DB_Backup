<?php
date_default_timezone_set('Asia/Dhaka');

// run : php db_exports.php database_name table_name

// ----------[ CONFIGURATION ]----------
$host = '192.168.20.14';
$user = 'root';
$password = '351f0*57034e1a025#';
$outputDir = '/home/centos/auto_archive/';

// ----------[ GET DATABASE AND TABLE NAME ]----------
$database = null;
$table = null;

if (php_sapi_name() === 'cli') {
    if (isset($argv[1]) && isset($argv[2])) {
        $database = $argv[1];
        $table = $argv[2];
    } elseif (isset($argv[1])) {
        // Single argument - show usage
        echo "❌ Both database and table name required.\n";
        echo "Usage: php db_backup.php database_name table_name\n";
        echo "Example: php db_backup.php z_robi_sm_archive sdp_send_sms_log_202507_07_08\n";
        exit(1);
    }
}

// Web request (GET parameters)
elseif (isset($_GET['database']) && isset($_GET['table'])) {
    $database = $_GET['database'];
    $table = $_GET['table'];
}

// Check if both parameters are provided
if (!$database || !$table) {
    if (php_sapi_name() === 'cli') {
        echo "❌ Database and table name required.\n";
        echo "Usage: php db_backup.php database_name table_name\n";
        echo "Examples:\n";
        echo "  php db_backup.php z_robi_sm_archive sdp_send_sms_log_202507_07_08\n";
        echo "  php db_backup.php z_gp_global_archive renews_202507_01_02\n";
        echo "  php db_backup.php z_blink_dob_archive sdp_6d_callback_202507_01_02\n";
    } else {
        echo "❌ Database and table name required.\n";
        echo "Usage: ?database=database_name&table=table_name\n";
        echo "Example: ?database=z_robi_sm_archive&table=sdp_send_sms_log_202507_07_08\n";
    }
    exit(1);
}

// Validate database and table names (security check)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    exit("❌ Invalid database name. Only alphanumeric characters and underscores allowed.\n");
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    exit("❌ Invalid table name. Only alphanumeric characters and underscores allowed.\n");
}

// ----------[ DISPLAY EXECUTION INFO ]----------
echo "🔄 Starting backup process...\n";
echo "📊 Database: $database\n";
echo "📊 Table: $table\n";
echo "📊 Host: $host\n";

// ----------[ FILE PATHS ]----------
// $sqlFile = $outputDir . $database . '_' . $table . '.sql';
$sqlFile = $outputDir . $table . '.sql';
$gzipFile = $sqlFile . '.gz';

echo "📁 Output file: $gzipFile\n";
echo str_repeat('-', 50) . "\n";

// ----------[ STEP 1: Check if output directory exists ]----------
if (!is_dir($outputDir)) {
    echo "📁 Creating output directory: $outputDir\n";
    if (!mkdir($outputDir, 0755, true)) {
        exit("❌ Failed to create output directory.\n");
    }
}

// ----------[ STEP 2: mysqldump ]----------
$dumpCmd = sprintf(
    'mysqldump --single-transaction --routines --triggers --skip-extended-insert --skip-comments --complete-insert --no-tablespaces -u %s -p\'%s\' -h %s %s %s > %s 2>&1',
    escapeshellarg($user), 
    $password, 
    escapeshellarg($host), 
    escapeshellarg($database), 
    escapeshellarg($table), 
    escapeshellarg($sqlFile)
);

echo "🔄 Dumping SQL for table: $table from database: $database\n";
exec($dumpCmd, $output, $result);

if ($result !== 0) {
    echo "❌ mysqldump failed with exit code: $result\n";
    if (!empty($output)) {
        echo "Error output: " . implode("\n", $output) . "\n";
    }
    // Clean up partial file
    if (file_exists($sqlFile)) {
        unlink($sqlFile);
    }
    exit(1);
}

// Check if SQL file was created and has content
if (!file_exists($sqlFile) || filesize($sqlFile) == 0) {
    exit("❌ SQL file was not created or is empty. Check if table exists.\n");
}

$sqlFileSize = filesize($sqlFile);
echo "✅ SQL dump completed. File size: " . formatBytes($sqlFileSize) . "\n";

// ----------[ STEP 3: Remove DROP and CREATE TABLE ]----------
$sedCmd = sprintf(
    "sed -i -e '1d' -e '/CREATE TABLE/,/);/d' -e '/DROP TABLE IF EXISTS `%s`;/d' %s",
    $table, 
    escapeshellarg($sqlFile)
);

echo "🧹 Cleaning SQL file (removing CREATE/DROP statements)...\n";
exec($sedCmd, $sedOutput, $sedResult);

if ($sedResult !== 0) {
    echo "❌ sed cleanup failed with exit code: $sedResult\n";
    if (!empty($sedOutput)) {
        echo "Error output: " . implode("\n", $sedOutput) . "\n";
    }
    exit(1);
}

$cleanedSize = filesize($sqlFile);
echo "✅ SQL file cleaned. New size: " . formatBytes($cleanedSize) . "\n";

// ----------[ STEP 4: Compress SQL file with gzip ]----------
$gzipCmd = sprintf('gzip -f %s', escapeshellarg($sqlFile));

echo "📦 Compressing file with gzip...\n";
exec($gzipCmd, $gzipOutput, $gzipResult);

if ($gzipResult !== 0) {
    echo "❌ gzip compression failed with exit code: $gzipResult\n";
    if (!empty($gzipOutput)) {
        echo "Error output: " . implode("\n", $gzipOutput) . "\n";
    }
    exit(1);
}

// Check if compressed file exists
if (!file_exists($gzipFile)) {
    exit("❌ Compressed file was not created.\n");
}

$compressedSize = filesize($gzipFile);
$compressionRatio = round((1 - ($compressedSize / $cleanedSize)) * 100, 2);

echo "✅ Compression completed!\n";
echo "📊 Original size: " . formatBytes($cleanedSize) . "\n";
echo "📊 Compressed size: " . formatBytes($compressedSize) . "\n";
echo "📊 Compression ratio: {$compressionRatio}%\n";

echo str_repeat('-', 50) . "\n";
echo "🎉 SUCCESS! Backup completed successfully!\n";
echo "📁 Final file: $gzipFile\n";

// ----------[ HELPER FUNCTION ]----------
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>