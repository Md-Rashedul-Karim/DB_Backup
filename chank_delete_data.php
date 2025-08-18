<?php
// Database connection info
$hostname = "127.0.0.1"; // অথবা localhost
$username = "root";      // MySQL user
$password = "";          // MySQL password থাকলে দিন
$database = "gp_global"; // Database name

// Date range for deletion
$startDate = "2025-07-27";
$endDate   = "2025-07-28 23:59:59";

// Batch size (একবারে কত রো ডিলিট হবে)
$batchSize = 5000;

// Connect to MySQL
$mysqli = new mysqli($hostname, $username, $password, $database);

// Check connection
if ($mysqli->connect_errno) {
    die("❌ Failed to connect to MySQL: " . $mysqli->connect_error . "\n");
}

echo "✅ Connected to MySQL\n";
echo "🚀 Starting batch deletion from '$startDate' to '$endDate'\n\n";

$totalDeleted = 0;
$iteration = 1;

do {
    // Delete query with LIMIT
    $query = "
        DELETE FROM renews_202507
        WHERE created_at BETWEEN '$startDate' AND '$endDate'
        LIMIT $batchSize
    ";

    $mysqli->query($query);

    // Number of rows deleted in this batch
    $deletedRows = $mysqli->affected_rows;
    $totalDeleted += $deletedRows;

    if ($deletedRows > 0) {
        echo "🗑 Batch $iteration: Deleted $deletedRows rows (Total: $totalDeleted)\n";
    }

    $iteration++;

    // Optional: ছোট delay রাখলে সার্ভারের লোড কম হবে
    usleep(200000); // 0.2 second

} while ($deletedRows > 0);

echo "\n✅ Batch deletion completed. Total deleted: $totalDeleted rows\n";

// Close connection
$mysqli->close();
