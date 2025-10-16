<?php
/**
 * service, sub, unsub lookup script
 * Features:
 * - Batch processing for large MSISDN list
 * - Try-catch error handling
 * - Progress percentage display
 * - CSV export
 */

date_default_timezone_set('Asia/Dhaka');

$host = '192.168.20.14';
$user = 'root';
$pass = '351f0*57034e1a025#';
$db = 'blink_dob';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->autocommit(false);

    // =======================
    // MSISDN লোড করুন txt ফাইল থেকে
    $msisdns_file = __DIR__ . '/msisdns.txt';
    if (!file_exists($msisdns_file)) {
        throw new Exception("MSISDN ফাইল পাওয়া যায়নি: $msisdns_file");
    }

    $msisdns = file($msisdns_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $msisdns = array_unique($msisdns);

    if (empty($msisdns)) {
        throw new Exception("MSISDN লিস্ট ফাইল খালি।");
    }

    // Prepare CSV file
    $filename = "subscriptions_export_" . date('Ymd_His') . ".csv";
    $file = fopen($filename, "w");
    if (!$file) {
        throw new Exception("CSV ফাইল তৈরি করা যায়নি: $filename");
    }

    fputcsv($file, ['msisdn','service','status','subs_date','unsubs_date']);

    // Print total MSISDNs
    $total = count($msisdns);
    echo "Total MSISDNs: $total" . PHP_EOL;
    echo "Processing..." . PHP_EOL;

    // =======================
    // Batch-wise processing with progress
    $batch_size = 50;
    $processed_count = 0;

    for ($i = 0; $i < $total; $i += $batch_size) {
        $batch = array_slice($msisdns, $i, $batch_size);

        foreach ($batch as $msisdn) {
            try {
                $msisdn_safe = $conn->real_escape_string($msisdn);
                $sql = "SELECT * FROM subscribers WHERE msisdn = '$msisdn_safe' GROUP BY msisdn";
                $result = $conn->query($sql);

                if ($result === false) {
                    throw new Exception("SQL Error for MSISDN $msisdn: " . $conn->error);
                }

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "MSISDN: {$msisdn} | Service: {$row['service']} | Status: {$row['status']} | Subscribed: {$row['subs_date']} | Unsubscribed: {$row['unsubs_date']}" . PHP_EOL;

                        fputcsv($file, [
                            $msisdn,
                            $row['service'],
                            $row['status'],
                            $row['subs_date'],
                            $row['unsubs_date'],
                        ]);
                    }
                } else {
                    echo "MSISDN: {$msisdn} | No data found." . PHP_EOL;
                    fputcsv($file, [$msisdn,'','','','']);
                }

            } catch (Exception $e) {
                echo "Error processing MSISDN $msisdn: " . $e->getMessage() . PHP_EOL;
            }

            // =======================
            // Update progress
            $processed_count++;
            $percent = round(($processed_count / $total) * 100, 2);
            echo "Progress: $processed_count / $total ($percent%)" . PHP_EOL;
        }
    }

    fclose($file);
    echo "✅ CSV ফাইল তৈরি হয়েছে: $filename" . PHP_EOL;

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit;
}
