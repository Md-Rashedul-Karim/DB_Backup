<?php

/**
 * ============================================================
 * AUTO DATA ARCHIVE - MASTER RUNNER + EMAIL REPORT
 * ============================================================
 *
 * Run:
 *   php run_all_archive_email.php 

 *
 * Features:
 *   - Runs scripts sequentially
 *   - Prevents duplicate/concurrent master runs
 *   - Individual log files
 *   - Master log
 *   - Captures stdout + stderr
 *   - Detects exit code
 *   - Sends SUCCESS/FAILED email after all scripts finish
 *   - Includes summary + full master log in email
 *   - Keeps logs for 30 days
 */

date_default_timezone_set('Asia/Dhaka');

set_time_limit(0);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', '1');


/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
|
| Expected structure:
|
| /your/archive/folder/
|   run_all_archive_email.php
|   PHPMailer/
|       Exception.php
|       PHPMailer.php
|       SMTP.php
|
| FIX: if these files are missing, the original script died with a raw
| PHP fatal error *before* the log directory / master log existed, so
| nothing was ever recorded anywhere. We now check first and fail loudly
| with a normal exit(1) + message.
*/

$phpMailerFiles = [
    __DIR__ . '/PHPMailer/Exception.php',
    __DIR__ . '/PHPMailer/PHPMailer.php',
    __DIR__ . '/PHPMailer/SMTP.php',
];

foreach ($phpMailerFiles as $pmFile) {
    if (!file_exists($pmFile)) {
        echo "ERROR: Missing PHPMailer file: {$pmFile}" . PHP_EOL;
        echo "Make sure the PHPMailer/ folder sits next to this script." . PHP_EOL;
        exit(1);
    }
}

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

$baseDir = __DIR__;

$logDir = $baseDir . '/logs';

$lockFile = $baseDir . '/archive_runner.lock';

$logRetentionDays = 30;


/*
|--------------------------------------------------------------------------
| ARCHIVE SCRIPTS
|--------------------------------------------------------------------------
*/

$scripts = [
    'bulksms' => [
        'name' => 'bulksms',
        'file' => $baseDir . '/bulksms_b2mwap_auto_data_archive.php',
    ],
    'rd' => [
        'name' => 'rd',
        'file' => $baseDir . '/rd_b2mwap_auto_data_archive.php',
    ],
    'robidob' => [
        'name' => 'robidob',
        'file' => $baseDir . '/robidob_b2mwap_auto_data_archive.php',
    ],
    'bdg' => [
        'name' => 'bdg',
        'file' => $baseDir . '/bdg_auto_data_archive.php',
    ],
];

/*
|--------------------------------------------------------------------------
| EMAIL CONFIGURATION
|--------------------------------------------------------------------------
|
| FIX: getenv() only sees variables that are actually exported into the
| process that runs this script. A cron job almost never has the same
| environment as your interactive SSH shell — so if ARCHIVE_MAIL_PASSWORD
| was only ever `export`-ed in your terminal, cron will NOT see it and
| every run will silently fail to send mail.
|
| Safer options, in order of preference:
|   1. Put `export ARCHIVE_MAIL_PASSWORD='...'` directly inside the
|      crontab entry (or a wrapper shell script the cron job calls).
|   2. Or load it from a small local config file that is NOT web-accessible
|      and NOT committed to git (shown below as a fallback).
*/

$mailConfigFile = $baseDir . '/mail_configer.php';

$mailConfigFromFile = file_exists($mailConfigFile) ? require $mailConfigFile : [];

$mailHost = getenv('ARCHIVE_MAIL_HOST') ?: ($mailConfigFromFile['host'] ?? 'shared93.accountservergroup.com');
$mailPort = (int) (getenv('ARCHIVE_MAIL_PORT') ?: ($mailConfigFromFile['port'] ?? 465));
$mailUsername = getenv('ARCHIVE_MAIL_USERNAME') ?: ($mailConfigFromFile['username'] ?? 'rashedul.karim@b2m-tech.com');
$mailPassword = getenv('ARCHIVE_MAIL_PASSWORD') ?: ($mailConfigFromFile['password'] ?? '');

$mailFromAddress = getenv('ARCHIVE_MAIL_FROM') ?: ($mailConfigFromFile['from_address'] ?? $mailUsername);
$mailFromName = getenv('ARCHIVE_MAIL_FROM_NAME') ?: ($mailConfigFromFile['from_name'] ?? 'DB Export/Archive Report');

$emailTo = [
    ['rashedul.karim@b2m-tech.com', 'Rashedul Karim'],
    ['manager@b2m-tech.com', 'Manager'],
];

$emailCc = [
    ['admin@b2m-tech.com', 'Admin'],
    ['devops@b2m-tech.com', 'DevOps Team'],
];


/*
|--------------------------------------------------------------------------
| CREATE LOG DIRECTORY
|--------------------------------------------------------------------------
*/

function deleteDirectory($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

// পুরোনো log folder delete
if (is_dir($logDir)) {
    deleteDirectory($logDir);
}

// নতুন log folder তৈরি
if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
    die("ERROR: Failed to create log directory: {$logDir}" . PHP_EOL);
}


/*
|--------------------------------------------------------------------------
| LOG FUNCTION
|--------------------------------------------------------------------------
*/

function writeLog(string $message, string $masterLog): void
{
    $time = date('Y-m-d H:i:s');

    $line = "[{$time}] {$message}" . PHP_EOL;

    echo $line;

    file_put_contents(
        $masterLog,
        $line,
        FILE_APPEND | LOCK_EX
    );
}


/*
|--------------------------------------------------------------------------
| EMAIL FUNCTION
|--------------------------------------------------------------------------
|
| FIX: previously returned only true/false, so the *reason* a send failed
| (wrong password, host unreachable, auth rejected, etc.) was echoed to
| stdout only — which cron usually discards — and never reached the log
| file or you. Now it returns the error message too, and the caller
| writes it into the master log.
*/

function sendStatusEmail(
    string $subject,
    string $content,
    array $toList,
    array $ccList,
    string $mailHost,
    int $mailPort,
    string $mailUsername,
    string $mailPassword,
    string $mailFromAddress,
    string $mailFromName
): array {

    $mail = new PHPMailer(true);

    try {

        if ($mailPassword === '') {
            throw new RuntimeException(
                'ARCHIVE_MAIL_PASSWORD is not configured (not visible to this process). ' .
                'Set it in the crontab entry or in mail_config.php.'
            );
        }

        $mail->isSMTP();

        $mail->Host = $mailHost;
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $mail->Password = $mailPassword;

        if ($mailPort === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->Port = $mailPort;

        // Give slow/shared SMTP servers a reasonable connection timeout
        // instead of hanging the whole cron job.
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;

        /*
         * If your shared hosting SMTP certificate is valid,
         * remove this SMTPOptions block.
         *
         * It is kept here because some shared hosting servers
         * use certificates that PHP/OpenSSL cannot validate.
         */
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(
            $mailFromAddress,
            $mailFromName
        );

        if (empty($toList)) {
            $mail->addAddress(
                $mailFromAddress,
                $mailFromName
            );
        } else {
            foreach ($toList as $recipient) {
                $mail->addAddress(
                    $recipient[0],
                    $recipient[1] ?? ''
                );
            }
        }

        foreach ($ccList as $recipient) {
            $mail->addCC(
                $recipient[0],
                $recipient[1] ?? ''
            );
        }

        $mail->isHTML(false);

        $mail->CharSet = 'UTF-8';

        $mail->Subject = $subject;

        $mail->Body = $content;

        $mail->send();

        return [
            'success' => true,
            'error' => '',
        ];

    } catch (Throwable $e) {

        echo "EMAIL ERROR: " . $e->getMessage() . PHP_EOL;

        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}


/*
|--------------------------------------------------------------------------
| RUN ONE ARCHIVE SCRIPT
|--------------------------------------------------------------------------
*/

function runArchiveScript(
    string $key,
    string $name,
    string $script,
    string $logDir,
    string $masterLog,
    string $phpBinary
): array {

    writeLog(
        "------------------------------------------------------------",
        $masterLog
    );

    writeLog(
        "STARTING: {$name}",
        $masterLog
    );

    writeLog(
        "SCRIPT: {$script}",
        $masterLog
    );


    /*
    |--------------------------------------------------------------------------
    | Script existence
    |--------------------------------------------------------------------------
    */

    if (!file_exists($script)) {

        writeLog(
            "FAILED: Script not found.",
            $masterLog
        );

        return [
            'key' => $key,
            'name' => $name,
            'status' => 'FAILED',
            'exit_code' => 1,
            'duration' => 0,
            'log' => null,
            'error' => 'Script file not found.',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Individual log
    |--------------------------------------------------------------------------
    */

    $date = date('Y-m-d');

    $scriptLog = $logDir . "/{$key}_{$date}.log";


    file_put_contents(
        $scriptLog,
        PHP_EOL .
        "============================================================" . PHP_EOL .
        "SCRIPT : {$name}" . PHP_EOL .
        "START  : " . date('Y-m-d H:i:s') . PHP_EOL .
        "FILE   : {$script}" . PHP_EOL .
        "============================================================" . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );


    /*
    |--------------------------------------------------------------------------
    | Execute
    |--------------------------------------------------------------------------
    */

    $command =
        escapeshellarg($phpBinary) .
        ' ' .
        escapeshellarg($script) .
        ' 2>&1';


    writeLog(
        "COMMAND: {$command}",
        $masterLog
    );

    writeLog(
        "LOG FILE: {$scriptLog}",
        $masterLog
    );


    $startTime = microtime(true);

    $output = [];

    $exitCode = 0;


    exec(
        $command,
        $output,
        $exitCode
    );


    $duration = microtime(true) - $startTime;


    /*
    |--------------------------------------------------------------------------
    | Save output
    |--------------------------------------------------------------------------
    */

    if (!empty($output)) {

        file_put_contents(
            $scriptLog,
            implode(PHP_EOL, $output) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    $status = ($exitCode === 0)
        ? 'SUCCESS'
        : 'FAILED';


    $errorText = '';


    if ($exitCode !== 0 && !empty($output)) {
        $errorText = implode(PHP_EOL, array_slice($output, -30));
    }


    file_put_contents(
        $scriptLog,
        "============================================================" . PHP_EOL .
        "STATUS : {$status}" . PHP_EOL .
        "END    : " . date('Y-m-d H:i:s') . PHP_EOL .
        "EXIT   : {$exitCode}" . PHP_EOL .
        "TIME   : " . number_format($duration, 2) . " seconds" . PHP_EOL .
        "============================================================" . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );


    /*
    |--------------------------------------------------------------------------
    | Master log
    |--------------------------------------------------------------------------
    */

    writeLog(
        "{$name}: {$status}",
        $masterLog
    );

    writeLog(
        "{$name}: EXIT CODE = {$exitCode}",
        $masterLog
    );

    writeLog(
        "{$name}: EXECUTION TIME = " .
        number_format($duration, 2) .
        " seconds",
        $masterLog
    );


    /*
    |--------------------------------------------------------------------------
    | Console output
    |--------------------------------------------------------------------------
    */

    if (!empty($output)) {

        echo PHP_EOL;

        echo "================ {$name} OUTPUT ================" . PHP_EOL;

        foreach ($output as $line) {
            echo $line . PHP_EOL;
        }

        echo "==================================================" . PHP_EOL;
    }


    return [
        'key' => $key,
        'name' => $name,
        'status' => $status,
        'exit_code' => $exitCode,
        'duration' => $duration,
        'log' => $scriptLog,
        'error' => $errorText,
    ];
}


/*
|--------------------------------------------------------------------------
| LOCK
|--------------------------------------------------------------------------
*/

$lockHandle = fopen($lockFile, 'c');

if ($lockHandle === false) {

    echo "ERROR: Cannot create lock file." . PHP_EOL;

    exit(1);
}


if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {

    echo PHP_EOL;
    echo "============================================================" . PHP_EOL;
    echo "ARCHIVE PROCESS ALREADY RUNNING" . PHP_EOL;
    echo "============================================================" . PHP_EOL;
    echo PHP_EOL;
    echo "Another master archive process is already running." . PHP_EOL;
    echo PHP_EOL;

    fclose($lockHandle);

    exit(1);
}


/*
|--------------------------------------------------------------------------
| Release lock when process exits
|--------------------------------------------------------------------------
*/

register_shutdown_function(
    function () use ($lockHandle) {

        flock($lockHandle, LOCK_UN);

        fclose($lockHandle);
    }
);


/*
|--------------------------------------------------------------------------
| MASTER LOG
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');

$masterLog = $logDir . "/archive_master_{$today}.log";

$masterStart = microtime(true);


echo PHP_EOL;

echo "============================================================" . PHP_EOL;
echo "             AUTO DATA ARCHIVE MASTER RUNNER" . PHP_EOL;
echo "============================================================" . PHP_EOL;

echo "START TIME : " . date('Y-m-d H:i:s') . PHP_EOL;
echo "LOG DIR    : {$logDir}" . PHP_EOL;

echo "============================================================" . PHP_EOL;


writeLog(
    "MASTER PROCESS STARTED",
    $masterLog
);

writeLog(
    "PHP BINARY: " . PHP_BINARY,
    $masterLog
);

// FIX: flag right away (in the log itself) if the mail password is
// missing, instead of only finding out at send-time.
if ($mailPassword === '') {
    writeLog(
        "WARNING: ARCHIVE_MAIL_PASSWORD is empty for this process — the report email will fail to send.",
        $masterLog
    );
}


/*
|--------------------------------------------------------------------------
| RUN ALL
|--------------------------------------------------------------------------
*/

$results = [];

foreach ($scripts as $key => $scriptInfo) {

    $results[] = runArchiveScript(
        $key,
        $scriptInfo['name'],
        $scriptInfo['file'],
        $logDir,
        $masterLog,
        PHP_BINARY
    );
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$successCount = 0;

$failedCount = 0;

$summaryLines = [];

$failedDetails = [];


foreach ($results as $result) {

    if ($result['status'] === 'SUCCESS') {
        $successCount++;
    } else {
        $failedCount++;
    }


    $summaryLines[] =
        sprintf(
            "%-12s | %-7s | Exit Code: %-3d | Time: %s sec",
            $result['name'],
            $result['status'],
            $result['exit_code'],
            number_format($result['duration'], 2)
        );


    if ($result['status'] === 'FAILED') {

        $failedDetails[] =
            $result['name'] .
            " failed. Exit Code: " .
            $result['exit_code'] .
            PHP_EOL .
            (
                $result['error']
                ? $result['error']
                : 'Check individual log file: ' . ($result['log'] ?? 'N/A')
            );
    }
}


$totalDuration = microtime(true) - $masterStart;

$overallStatus = ($failedCount > 0)
    ? 'FAILED'
    : 'SUCCESS';


/*
|--------------------------------------------------------------------------
| FINAL CONSOLE SUMMARY
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo "============================================================" . PHP_EOL;
echo "                    FINAL SUMMARY" . PHP_EOL;
echo "============================================================" . PHP_EOL;

foreach ($summaryLines as $line) {
    echo $line . PHP_EOL;
}

echo "------------------------------------------------------------" . PHP_EOL;

echo "TOTAL       : " . count($results) . PHP_EOL;
echo "SUCCESS     : {$successCount}" . PHP_EOL;
echo "FAILED      : {$failedCount}" . PHP_EOL;
echo "TOTAL TIME  : " . number_format($totalDuration, 2) . " seconds" . PHP_EOL;
echo "FINAL STATUS: {$overallStatus}" . PHP_EOL;

echo "============================================================" . PHP_EOL;


/*
|--------------------------------------------------------------------------
| MASTER LOG FINAL RESULT
|--------------------------------------------------------------------------
*/

writeLog(
    "FINAL STATUS: {$overallStatus}",
    $masterLog
);

writeLog(
    "TOTAL: " . count($results) .
    " | SUCCESS: {$successCount}" .
    " | FAILED: {$failedCount}",
    $masterLog
);

writeLog(
    "TOTAL EXECUTION TIME: " .
    number_format($totalDuration, 2) .
    " seconds",
    $masterLog
);


/*
|--------------------------------------------------------------------------
| EMAIL REPORT
|--------------------------------------------------------------------------
*/

$emailSubject =
    "DB Archive " .
    $overallStatus .
    " - " .
    date('Y-m-d H:i:s');


$emailBody = '';

$emailBody .= "AUTO DATA ARCHIVE REPORT" . PHP_EOL;
$emailBody .= "============================================" . PHP_EOL;
$emailBody .= PHP_EOL;

$emailBody .= "Overall Status : {$overallStatus}" . PHP_EOL;

// FIX: the original expression `$masterStart + time() - time()` always
// reduces to just $masterStart — it did nothing but obscure the value.
$emailBody .= "Start Time     : " .
    date('Y-m-d H:i:s', (int) $masterStart) .
    PHP_EOL;

$emailBody .= "End Time       : " .
    date('Y-m-d H:i:s') .
    PHP_EOL;

$emailBody .= "Server         : " .
    php_uname('n') .
    PHP_EOL;

$emailBody .= "PHP            : " .
    PHP_VERSION .
    PHP_EOL;

$emailBody .= "Total Time     : " .
    number_format($totalDuration, 2) .
    " seconds" .
    PHP_EOL;

$emailBody .= PHP_EOL;

$emailBody .= "RESULT SUMMARY" . PHP_EOL;
$emailBody .= "============================================" . PHP_EOL;

$emailBody .= implode(
    PHP_EOL,
    $summaryLines
);

$emailBody .= PHP_EOL;
$emailBody .= PHP_EOL;


/*
|--------------------------------------------------------------------------
| Failed details
|--------------------------------------------------------------------------
*/

if (!empty($failedDetails)) {

    $emailBody .= "FAILED DETAILS" . PHP_EOL;
    $emailBody .= "============================================" . PHP_EOL;

    $emailBody .= implode(
        PHP_EOL . PHP_EOL,
        $failedDetails
    );

    $emailBody .= PHP_EOL;
    $emailBody .= PHP_EOL;
}


/*
|--------------------------------------------------------------------------
| Full master log
|--------------------------------------------------------------------------
*/

$emailBody .= "MASTER LOG" . PHP_EOL;
$emailBody .= "============================================" . PHP_EOL;

if (file_exists($masterLog)) {

    $masterLogContent = file_get_contents($masterLog);

    /*
     * Avoid an extremely large email if the master log becomes huge.
     * Keep the latest 30,000 characters.
     */
    if (strlen($masterLogContent) > 30000) {

        $masterLogContent =
            "... [older log truncated] ..." .
            PHP_EOL .
            substr($masterLogContent, -30000);
    }

    $emailBody .= $masterLogContent;

} else {

    $emailBody .= "Master log not found.";
}


$emailBody .= PHP_EOL;
$emailBody .= "============================================" . PHP_EOL;


/*
|--------------------------------------------------------------------------
| SEND EMAIL
|--------------------------------------------------------------------------
*/

$emailResult = sendStatusEmail(
    $emailSubject,
    $emailBody,
    $emailTo,
    $emailCc,
    $mailHost,
    $mailPort,
    $mailUsername,
    $mailPassword,
    $mailFromAddress,
    $mailFromName
);

$emailSent = $emailResult['success'];


if ($emailSent) {

    writeLog(
        "EMAIL SENT SUCCESSFULLY: {$emailSubject}",
        $masterLog
    );

    echo PHP_EOL;
    echo "EMAIL: SENT SUCCESSFULLY" . PHP_EOL;

} else {

    // FIX: the real reason is now written to the master log too,
    // not just echoed to a terminal nobody is watching during a cron run.
    writeLog(
        "EMAIL FAILED: " . $emailResult['error'],
        $masterLog
    );

    echo PHP_EOL;
    echo "EMAIL: FAILED - " . $emailResult['error'] . PHP_EOL;
}


/*
|--------------------------------------------------------------------------
| CLEAN OLD LOGS
|--------------------------------------------------------------------------
*/

$cutoffTime = time() - ($logRetentionDays * 86400);

$logFiles = glob($logDir . '/*.log');

if ($logFiles !== false) {

    foreach ($logFiles as $file) {

        if (!is_file($file)) {
            continue;
        }

        if (filemtime($file) < $cutoffTime) {
            @unlink($file);
        }
    }
}


/*
|--------------------------------------------------------------------------
| FINAL EXIT CODE
|--------------------------------------------------------------------------
|
| 0 = archive successful
| 1 = one or more archive scripts failed
|
| Email failure itself is logged but does not change the
| archive result because the database archive may have
| completed successfully.
|
*/

if ($failedCount > 0) {

    writeLog(
        "MASTER PROCESS COMPLETED WITH ERRORS.",
        $masterLog
    );

    exit(1);
}


writeLog(
    "MASTER PROCESS COMPLETED SUCCESSFULLY.",
    $masterLog
);

exit(0);