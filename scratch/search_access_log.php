<?php
$logPath = 'C:\\laragon\\bin\\apache\\httpd-2.4.66-260223-Win64-VS18\\logs\\access_log';
if (file_exists($logPath)) {
    $lines = file($logPath);
    echo "Occurrences of verify-pin in access_log:\n";
    foreach ($lines as $line) {
        if (strpos($line, 'verify-pin') !== false) {
            echo $line;
        }
    }
} else {
    echo "Log file not found.\n";
}
