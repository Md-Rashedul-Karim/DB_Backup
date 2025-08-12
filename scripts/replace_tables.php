<?php
// Folder path
$folderPath = "G:/z-db/gp_global/202507";

// Replacement patterns
$replacements = [
    [
        "old" => "consents_202507_21_22",
        "new" => "consents_202507",
        "output" => "G:/z-db/gp_global/consents_202507_22.sql"
    ],
    [
        "old" => "consents_202507_23_24",
        "new" => "consents_202507",
        "output" => "G:/z-db/gp_global/consents_202507_24.sql"
    ],
	[
        "old" => "consents_202507_25_26",
        "new" => "consents_202507",
        "output" => "G:/z-db/gp_global/consents_202507_26.sql"
    ],
	[
        "old" => "consents_202507_27_28",
        "new" => "consents_202507",
        "output" => "G:/z-db/gp_global/consents_202507_28.sql"
    ],
	[
        "old" => "consents_202507_29_31",
        "new" => "consents_202507",
        "output" => "G:/z-db/gp_global/consents_202507_31.sql"
    ],
   
];

// Loop through replacements
foreach ($replacements as $item) {
    // Find matching file
    $files = glob($folderPath . "/*" . $item['old'] . "*.sql");

    if (!empty($files)) {
        $file = $files[0]; // First matching file
        echo "Processing: {$file}\n";

        // Read file content
        $content = file_get_contents($file);

        // Replace old text with new
        $content = str_replace($item['old'], $item['new'], $content);

        // Save to new file
        file_put_contents($item['output'], $content);

        echo "Saved to: {$item['output']}\n";
    } else {
        echo "No file found for: {$item['old']}\n";
    }
}
?>
