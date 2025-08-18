<?php
set_time_limit(0); // Remove execution time limit
ini_set('memory_limit', '1G'); // Increase memory limit

$mysqlPath = 'D:\\xampp8\\mysql\\bin\\mysql.exe';

$hostname = '127.0.0.1';
$port     = 3306;
$dbUser    = 'root';
$dbName    = 'blink_dob';
$dbPass    = '';
$sqlFiles = [
    'G:\\z-db\\blink_dob\\sdp_6d_callback_202508_06.sql',
];

// 🚀 Performance optimizations
$chunkSize = 2000; // Increased chunk size for fewer MySQL calls
$bufferSize = 8192; // 8KB buffer for file reading

// MySQL optimization parameters (only valid mysql.exe options)
$mysqlOptimizations = [
    '--quick',
    '--max_allowed_packet=1G',
];

foreach ($sqlFiles as $file) {
    if (!file_exists($file)) {
        echo "⚠ File not found: $file\n";
        continue;
    }
    
    $startTime = microtime(true);
    echo "🚀 Fast importing: " . basename($file) . "\n";
    
    // 📊 Quick file analysis using optimized approach
    $totalLines = 0;
    $validLines = 0;
    $fileSize = filesize($file);
    
    echo "📁 File size: " . formatBytes($fileSize) . "\n";
    
    // Stream-based line counting for better memory usage
    $handle = fopen($file, 'r');
    if (!$handle) {
        echo "❌ Cannot open file: $file\n";
        continue;
    }
    
    // Use larger buffer for faster reading
    while (($buffer = fread($handle, $bufferSize * 100)) !== false && $buffer !== '') {
        $lines = substr_count($buffer, "\n");
        $totalLines += $lines;
        
        // Quick estimation of valid lines (avoid regex on every line during counting)
        $setNullCount = preg_match_all('/SET .*=\s*NULL;/i', $buffer, $matches);
        $validLines += ($lines - $setNullCount);
    }
    fclose($handle);
    
    $totalChunks = ceil($validLines / $chunkSize);
    
    echo "📊 Total lines: " . number_format($totalLines) . "\n";
    echo "📊 Valid lines: " . number_format($validLines) . "\n";
    echo "🚫 Estimated skipped: " . number_format($totalLines - $validLines) . "\n";
    echo "📦 Chunks: $totalChunks (size: " . number_format($chunkSize) . ")\n\n";
    
    // 🔥 Ultra-fast processing with optimizations
    $handle = fopen($file, 'r');
    if (!$handle) {
        echo "❌ Cannot reopen file: $file\n";
        continue;
    }
    
    $chunk = '';
    $chunkIndex = 0;
    $linesInChunk = 0;
    $processedLines = 0;
    $lastProgressUpdate = 0;
    
    // Create optimized MySQL command base
	$mysqlCmd = "\"$mysqlPath\" -h $hostname -P $port -u $dbUser" . 
            ($dbPass ? " -p$dbPass" : "") . 
            " " . implode(' ', $mysqlOptimizations) . 
            " $dbName";
			
	/*	
		$mysqlCmd = "\"$mysqlPath\" -u $dbUser -h $hostname -P $port" . 
        $dbPass ? " -p$dbPass" : "") . 
        " " . implode(' ', $mysqlOptimizations) . 
        " $dbName";
    */
	
	
    // MySQL performance optimizations via SQL commands
    $setupCommands = [
        "SET SESSION sql_log_bin = 0;",
        "SET SESSION foreign_key_checks = 0;",
        "SET SESSION unique_checks = 0;",
        "SET SESSION autocommit = 0;",
        "SET SESSION bulk_insert_buffer_size = 268435456;", // 256MB (--bulk_insert_buffer_size equivalent)
        "SET SESSION max_allowed_packet = 1073741824;", // 1GB (--max_allowed_packet equivalent)
        "SET SESSION low_priority_updates = 1;", // (--lock-tables=false equivalent)
        "SET SESSION sql_buffer_result = OFF;", // (--disable-keys equivalent behavior)
        "UNLOCK TABLES;" // Ensure no locked tables
    ];
    
    while (($line = fgets($handle, 4096)) !== false) {
        // Skip SET NULL lines efficiently
        if (strpos($line, 'SET ') === 0 && strpos($line, '= NULL;') !== false) {
            continue;
        }
        
        $chunk .= $line;
        $linesInChunk++;
        $processedLines++;
        
        // Process chunk when full
        if ($linesInChunk >= $chunkSize) {
            $chunkIndex++;
            
            // Create optimized SQL content
            $optimizedChunk = implode("\n", $setupCommands) . "\n" . 
                             "START TRANSACTION;\n" . 
                             $chunk . 
                             "COMMIT;\n";
            
            // Use memory-based temporary file (faster than disk)
            $tmpFile = "php://temp/maxmemory:" . (50 * 1024 * 1024); // 50MB in memory
            $tmpHandle = fopen($tmpFile, 'w+');
            fwrite($tmpHandle, $optimizedChunk);
            fclose($tmpHandle);
            
            // Execute with optimizations
            $realTmpFile = sys_get_temp_dir() . "/chunk_$chunkIndex.sql";
            file_put_contents($realTmpFile, $optimizedChunk);
            
            $command = "$mysqlCmd -e \"source $realTmpFile\" 2>&1";
            
            $execStart = microtime(true);
            exec($command, $output, $returnVar);
            $execTime = microtime(true) - $execStart;
            
            // Progress update (limit frequency for performance)
            $progress = ($processedLines / $validLines) * 100;
            if ($progress - $lastProgressUpdate >= 5 || $chunkIndex == $totalChunks) {
                $speed = $linesInChunk / $execTime;
                echo "⚡ Chunk $chunkIndex/$totalChunks (" . 
                     number_format($linesInChunk) . " lines, " . 
                     round($execTime, 2) . "s, " .
                     number_format($speed, 0) . " lines/sec, " .
                     round($progress, 1) . "%)\n";
                $lastProgressUpdate = $progress;
            }
            
            // Clean up
            unlink($realTmpFile);
            
            if ($returnVar !== 0) {
                echo "❌ Chunk $chunkIndex failed: " . implode(" ", array_slice($output, -2)) . "\n";
                // Continue processing other chunks
            }
            
            // Reset for next chunk
            $chunk = '';
            $linesInChunk = 0;
        }
    }
    
    // Process remaining lines
    if ($linesInChunk > 0) {
        $chunkIndex++;
        $optimizedChunk = implode("\n", $setupCommands) . "\n" . 
                         "START TRANSACTION;\n" . 
                         $chunk . 
                         "COMMIT;\n";
        
        $realTmpFile = sys_get_temp_dir() . "/chunk_final.sql";
        file_put_contents($realTmpFile, $optimizedChunk);
        
        $command = "$mysqlCmd -e \"source $realTmpFile\" 2>&1";
        exec($command, $output, $returnVar);
        
        echo "⚡ Final chunk: $linesInChunk lines\n";
        unlink($realTmpFile);
    }
    
    fclose($handle);
    
    // Re-enable MySQL settings to original state (only SESSION variables)
    $cleanupCommands = [
        "SET SESSION foreign_key_checks = 1;",
        "SET SESSION unique_checks = 1;",
        "SET SESSION autocommit = 1;",
        "SET SESSION sql_log_bin = 1;",
        "SET SESSION low_priority_updates = 0;",
        "SET SESSION sql_buffer_result = ON;"
    ];
    
    $cleanupSql = implode("\n", $cleanupCommands);
    $cleanupFile = sys_get_temp_dir() . "/cleanup.sql";
    file_put_contents($cleanupFile, $cleanupSql);
    exec("$mysqlCmd -e \"source $cleanupFile\"");
    unlink($cleanupFile);
    
    $totalTime = microtime(true) - $startTime;
    $avgSpeed = $processedLines / $totalTime;
    
    echo "\n🎉 Completed: " . basename($file) . "\n";
    echo "⏱️  Total time: " . formatTime($totalTime) . "\n";
    echo "⚡ Average speed: " . number_format($avgSpeed, 0) . " lines/second\n";
    echo "📊 Processed: " . number_format($processedLines) . " lines in $chunkIndex chunks\n\n";
}

echo "✅ All imports completed!\n";

// 🛠️ Helper functions
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

function formatTime($seconds) {
    if ($seconds < 60) return round($seconds, 2) . 's';
    if ($seconds < 3600) return floor($seconds/60) . 'm ' . round($seconds%60, 1) . 's';
    return floor($seconds/3600) . 'h ' . floor(($seconds%3600)/60) . 'm ' . round($seconds%60, 1) . 's';
}
?>