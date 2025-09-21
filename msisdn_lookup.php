<?php
/**
 * service, sub, unsub lookup script
 * Usage:
 * Run script:  php msisdn_lookup.php
 */

date_default_timezone_set('Asia/Dhaka');

$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';
$db = 'blink_dob'; 

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error . PHP_EOL);
    }
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$conn->set_charset('utf8mb4');
$conn->autocommit(false);


// MSISDN লোড করুন txt ফাইল থেকে
$msisdns_file = __DIR__ . '/msisdns.txt'; // একই ফোল্ডারে থাকলে
if (!file_exists($msisdns_file)) {
    die("MSISDN ফাইল পাওয়া যায়নি: $msisdns_file" . PHP_EOL);
}

// file কে array হিসেবে load করা
$msisdns = file($msisdns_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// unique করে নিন
$msisdns = array_unique($msisdns);

if (empty($msisdns)) {
    die("MSISDN লিস্ট ফাইল খালি।" . PHP_EOL);
}

// Make MSISDN unique
$msisdns = array_unique($msisdns);

// Prepare CSV file
$filename = "subscriptions_export_" . date('Ymd_His') . ".csv";
$file = fopen($filename, "w");
fputcsv($file, ['msisdn','service','status','subs_date','unsubs_date']);

// Loop through each unique MSISDN
foreach ($msisdns as $msisdn) {
    $msisdn_safe = $conn->real_escape_string($msisdn);
    $sql = "SELECT * FROM subscribers WHERE msisdn = '$msisdn_safe' GROUP BY msisdn ";
    $result = $conn->query($sql);

    if ($result === false) {
        echo "SQL Error for MSISDN $msisdn: " . $conn->error . PHP_EOL;
        continue;
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Display in terminal
            echo "MSISDN: {$msisdn} | Service: {$row['service']} | Status: {$row['status']} | Subscribed: {$row['subs_date']} | Unsubscribed: {$row['unsubs_date']}" . PHP_EOL;

            // Save to CSV
            fputcsv($file, [
                $msisdn,             
                $row['service'],
                $row['status'],
                $row['subs_date'],
                $row['unsubs_date'],
            ]);
        }
    } else {
        // যদি কোনো ডাটা না থাকে
        echo "MSISDN: {$msisdn} | No data found." . PHP_EOL;
        fputcsv($file, [$msisdn,'','','','']);
    }
}

fclose($file);
echo "CSV ফাইল তৈরি হয়েছে: $filename" . PHP_EOL;

$conn->close();