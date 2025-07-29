<?php
// ইনপুট এবং আউটপুট ফাইলের পথ সেট করুন
$input_file = "G:/z-db/gp_global/renews_202507_12.sql";
$output_file = "G:/z-db/gp_global/renews_202507_12_ignore.sql";

$table_name = "renews_202507";

// ইনপুট ফাইল রিড এবং আউটপুট ফাইল রাইট মোডে ওপেন করা
$in = fopen($input_file, "r");
$out = fopen($output_file, "w");

if (!$in || !$out) {
    die("❌ Could not open input or output file");
}

while (($line = fgets($in)) !== false) {
    // শুধুমাত্র renews_202507 টেবিলের INSERT কুয়েরি চেঞ্জ করা হবে
    if (strpos($line, "INSERT INTO `$table_name`") === 0) {
        // INSERT কে INSERT IGNORE এ রিপ্লেস করা
        $line = str_replace("INSERT INTO `$table_name`", "INSERT IGNORE INTO `$table_name`", $line);
    }
    fwrite($out, $line);
}

fclose($in);
fclose($out);

echo "✅ Modified file created with INSERT IGNORE: $output_file\n";
?>
