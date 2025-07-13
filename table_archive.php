<?php
date_default_timezone_set('Asia/Dhaka');
// ✅ চেক করুন সব argument দেওয়া হয়েছে কিনা
if ($argc < 8) {
    echo "❌ Usage: php archive.php <source_db> <target_db> <main_table> <date_formate> <from_date> <to_date> <table_suffix>\n";
    exit(1);
}

// ✅ Arguments ইনপুট নিন
$sourceDb    = $argv[1];
$targetDb    = $argv[2];
$mainTable   = $argv[3];
$dateformate = $argv[4];
$fromDate    = $argv[5];
$toDate      = $argv[6];
$tableSuffix = $argv[7];

// ✅ ডাইনামিক টেবিল নাম
$newTable = $mainTable . '_' . $tableSuffix;

// ✅ MySQL credentials
$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';
// -------or------
// $host = 'localhost';
// $user = 'root';
// $pass = 'n0@ccess4U';


// ✅ MySQL কানেকশন
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . PHP_EOL);
}
try {
    // ✅ Step 1: টেবিল তৈরি করা
    echo "📦 Creating table `$targetDb`.`$newTable` ... ";
    $createQuery = "CREATE TABLE `$targetDb`.`$newTable` LIKE `$sourceDb`.`$mainTable`";
    if ($conn->query($createQuery) === TRUE) {
        echo "✅ Success\n";
    } else {
        die("❌ Error creating table: " . $conn->error . PHP_EOL);
    }

    // ✅ Step 2: ডেটা কপি করা নির্দিষ্ট তারিখ অনুযায়ী
    echo "📤 Inserting data from `$fromDate` to `$toDate` ... ";
    $insertQuery = "
        INSERT INTO `$targetDb`.`$newTable`
        SELECT * FROM `$sourceDb`.`$mainTable`
        WHERE `$dateformate` BETWEEN '$fromDate' AND '$toDate'
    ";
    if ($conn->query($insertQuery) === TRUE) {
        echo "✅ Success\n";
    } else {
        die("❌ Error inserting data: " . $conn->error . PHP_EOL);
    }

} catch (Exception $e) {
    // ✅ Error হলে রোলব্যাক করুন
    $conn->rollback();
    die("❌ " . $e->getMessage() . PHP_EOL);
}
$conn->close();
echo "🎉 Archive complete: $targetDb.$newTable\n\n";
?>
