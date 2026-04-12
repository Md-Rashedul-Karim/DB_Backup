<?php
date_default_timezone_set('Asia/Dhaka');

// run :  php full_db_exports.php database_name


// ----------[ CONFIGURATION ]----------
$host      = '192.168.20.14';
$user      = 'root';
$password  = '351f0*57034e1a025#';
$outputDir = '/home/centos/auto_archive/'; // শেষে স্ল্যাশ নিশ্চিত করুন

// ----------[ GET DATABASE NAME ]----------
$database = null;

if (php_sapi_name() === 'cli') {
    $database = isset($argv[1]) ? $argv[1] : null;
} elseif (isset($_GET['database'])) {
    $database = $_GET['database'];
}

if (! $database) {
    exit("❌ Database name required.\nUsage: php full_db_exports.php database_name\n");
}

// Security check for database name
if (! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    exit("❌ Invalid database name.\n");
}

// ----------[ STEP 1: Connect to MySQL to fetch tables ]----------
$mysqli = new mysqli($host, $user, $password, $database);

if ($mysqli->connect_error) {
    exit("❌ Connection failed: " . $mysqli->connect_error . "\n");
}

$tables = [];
$result = $mysqli->query("SHOW TABLES");

while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

if (empty($tables)) {
    exit("❌ No tables found in database: $database\n");
}

echo "📂 Found " . count($tables) . " tables in $database. Starting bulk backup...\n";
echo str_repeat('=', 50) . "\n";

// ----------[ STEP 2: Loop through each table ]----------
foreach ($tables as $table) {
    echo "▶️ Processing table: $table\n";

    $sqlFile  = $outputDir . $table . '.sql';
    $gzipFile = $sqlFile . '.gz';

    // 1. Create directory if not exists
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    // 2. mysqldump
    $dumpCmd = sprintf(
        'mysqldump --single-transaction --routines --triggers --skip-extended-insert --skip-comments --complete-insert --no-tablespaces -u %s -p\'%s\' -h %s %s %s > %s 2>&1',
        escapeshellarg($user), $password, escapeshellarg($host),
        escapeshellarg($database), escapeshellarg($table), escapeshellarg($sqlFile)
    );

    exec($dumpCmd, $output, $resCode);

    if ($resCode === 0 && file_exists($sqlFile) && filesize($sqlFile) > 0) {

        // 3. Clean SQL (Remove CREATE/DROP)
        $sedCmd = sprintf(
            "sed -i -e '1d' -e '/CREATE TABLE/,/);/d' -e '/DROP TABLE IF EXISTS `%s`;/d' %s",
            $table, escapeshellarg($sqlFile)
        );
        exec($sedCmd);

        // 4. Gzip
        $gzipCmd = sprintf('gzip -f %s', escapeshellarg($sqlFile));
        exec($gzipCmd);

        echo "✅ Backup success: $table.gz (" . formatBytes(filesize($gzipFile)) . ")\n";
    } else {
        echo "❌ Failed to backup table: $table\n";
    }
    echo str_repeat('-', 30) . "\n";
}
// ----------[ FILE PATHS ]----------
echo "📁 Output directory: $outputDir\n";
echo str_repeat('-', 50) . "\n";

// ----------[ DONE ]----------

echo "🎉 ALL DONE! All tables from '$database' have been processed.\n";

// ----------[ HELPER FUNCTION ]----------
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {$bytes /= 1024;}
    return round($bytes, $precision) . ' ' . $units[$i];
}
