<?php
date_default_timezone_set('Asia/Dhaka');
// ✅ চেক করুন সব argument দেওয়া হয়েছে কিনা
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

// ✅ Enable autocommit for better transaction handling
$conn->autocommit(FALSE);

try {
    // ✅ Step 1: টেবিল তৈরি করা (যদি না থাকে)
    echo "📦 Creating table `$targetDb`.`$newTable` ... ";
    $createQuery = "CREATE TABLE IF NOT EXISTS `$targetDb`.`$newTable` LIKE `$sourceDb`.`$mainTable`";
    if ($conn->query($createQuery) === TRUE) {
        echo "\n ✅ Create table Success\n";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }

    // ✅ Step 2: প্রথমে চেক করুন কতগুলো রেকর্ড কপি হবে
    $countQuery = "SELECT COUNT(*) as total FROM `$sourceDb`.`$mainTable` WHERE `$dateformate` BETWEEN '$fromDate' AND '$toDate'";
    $countResult = $conn->query($countQuery);
    $totalRows = $countResult->fetch_assoc()['total'];
    
    if ($totalRows == 0) {
        echo "⚠️  No records found for the given date range. Exiting.\n";
        $conn->close();
        exit(0);
    }
    
    echo "📊 Found $totalRows records to archive\n";

    // ✅ Step 3: ডেটা কপি করা নির্দিষ্ট তারিখ অনুযায়ী (INSERT IGNORE ব্যবহার করুন)
    echo "\n 📤 Inserting data from `$fromDate` to `$toDate` ... ";
    $insertQuery = "
        INSERT IGNORE INTO `$targetDb`.`$newTable`
        SELECT * FROM `$sourceDb`.`$mainTable`
        WHERE `$dateformate` BETWEEN '$fromDate' AND '$toDate'
    ";

    if ($conn->query($insertQuery) === TRUE) {
        $rowsCopied = $conn->affected_rows;
        echo " \n ✅ Data insert Success ($rowsCopied rows copied)\n";

        // ✅ Step 4: কপি সফল হলে ডেটা ডিলিট
        if ($rowsCopied > 0) {
            echo "\n 🗑️  Deleting copied rows from `$sourceDb`.`$mainTable` ... ";
            $deleteQuery = "
                DELETE FROM `$sourceDb`.`$mainTable`
                WHERE `$dateformate` BETWEEN '$fromDate' AND '$toDate'
            ";
            if ($conn->query($deleteQuery) === TRUE) {
                $rowsDeleted = $conn->affected_rows;
                echo "\n ✅ Deleted $rowsDeleted rows\n";
                
                // ✅ সফল হলে কমিট করুন
                $conn->commit();
                
            } else {
                throw new Exception("Error deleting data: " . $conn->error);
            }

            // ✅ Step 5: অপ্টিমাইজ
            echo "🛠️  Optimizing `$sourceDb`.`$mainTable` ... ";
            $optimizeQuery = "OPTIMIZE TABLE `$sourceDb`.`$mainTable`";
            if ($conn->query($optimizeQuery) === TRUE) {
                echo "\n ✅ Optimized\n";
            } else {
                echo "⚠️  Optimization failed: " . $conn->error . "\n";
            }
        } else {
            echo "⚠️  No rows copied. Delete skipped.\n";
        }

    } else {
        throw new Exception("Error copying data: " . $conn->error);
    }

} catch (Exception $e) {
    // ✅ Error হলে রোলব্যাক করুন
    $conn->rollback();
    die("❌ " . $e->getMessage() . PHP_EOL);
}

$conn->close();
echo "🎉 Archive complete: $targetDb.$newTable\n";
?>