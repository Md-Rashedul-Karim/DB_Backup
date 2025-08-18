<?php
// Folder path
$folderPath = "G:/z-db/blink_dob/202508";

// Replacement patterns
$replacements = [
    [
        "old" => "sdp_6d_callback_202508_07_08",
        "new" => "sdp_6d_callback_202508",
        "output" => "G:/z-db/blink_dob/sdp_6d_callback_202508_08.sql"
    ],
	 [
        "old" => "sdp_6d_callback_202508_09_10",
        "new" => "sdp_6d_callback_202508",
        "output" => "G:/z-db/blink_dob/sdp_6d_callback_202508_10.sql"
    ],
 
];

// Buffer size (4MB chunks)
$chunkSize = 4 * 1024 * 1024;

foreach ($replacements as $item) {
    $files = glob($folderPath . "/*" . $item['old'] . "*.sql");

    if (!empty($files)) {
        $file = $files[0];
        echo "Processing: {$file}\n";

        $input = fopen($file, 'rb');
        if (!$input) {
            die("Cannot open input file: {$file}");
        }

        $output = fopen($item['output'], 'wb');
        if (!$output) {
            fclose($input);
            die("Cannot open output file: {$item['output']}");
        }

        $overlap = strlen($item['old']) - 1; // Keep last few bytes from previous chunk
        $buffer = '';

        while (!feof($input)) {
            // Read next chunk
            $chunk = fread($input, $chunkSize);
            if ($chunk === false) {
                echo "Error reading file.\n";
                break;
            }

            // Prepend leftover from last chunk
            $chunk = $buffer . $chunk;

            // Save last bytes for next loop
            $buffer = substr($chunk, -$overlap);

            // Remove overlap portion before replacing
            $processPart = substr($chunk, 0, strlen($chunk) - $overlap);

            // Replace text
            $processPart = str_replace($item['old'], $item['new'], $processPart);

            // Write processed part
            fwrite($output, $processPart);
        }

        // Process remaining buffer
        if ($buffer !== '') {
            $buffer = str_replace($item['old'], $item['new'], $buffer);
            fwrite($output, $buffer);
        }

        fclose($input);
        fclose($output);

        echo "✅ Saved to: {$item['output']}\n";
    } else {
        echo "❌ No file found for: {$item['old']}\n";
    }
}
