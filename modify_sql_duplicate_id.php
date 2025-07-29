<?php
// ইনপুট এবং আউটপুট ফাইলের পথ সেট করুন
$input_file = "G:/z-db/gp_global/renews_202507_12.sql";
$output_file = "G:/z-db/gp_global/renews_202507_12_modified.sql";

$table_name = "renews_202507";

// ইনপুট ফাইল রিড এবং আউটপুট ফাইল রাইট মোডে ওপেন করা
$in = fopen($input_file, "r");
$out = fopen($output_file, "w");

if (!$in || !$out) {
    die("❌ Could not open input or output file");
}

while (($line = fgets($in)) !== false) {
    if (strpos($line, "INSERT INTO `$table_name`") === 0) {
        // লাইন শেষে সেমিকোলন রিপ্লেস করে UPDATE যোগ করা
        $line = preg_replace(
            "/;\s*$/",
            " ON DUPLICATE KEY UPDATE ".
            "`acr_key`=VALUES(`acr_key`),".
            "`referenceCode`=VALUES(`referenceCode`),".
            "`service_keyword`=VALUES(`service_keyword`),".
            "`subscription`=VALUES(`subscription`),".
            "`consentId`=VALUES(`consentId`),".
            "`response`=VALUES(`response`),".
            "`created_at`=VALUES(`created_at`),".
            "`updated_at`=VALUES(`updated_at`);",
            $line
        );
    }
    fwrite($out, $line);
}

fclose($in);
fclose($out);

echo "✅ Modified file created: $output_file\n";
?>
