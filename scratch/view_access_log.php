<?php
$logPath = 'C:\\laragon\\bin\\apache\\httpd-2.4.66-260223-Win64-VS18\\logs\\access_log';
if (file_exists($logPath)) {
    $lines = file($logPath);
    $last_lines = array_slice($lines, -150);
    echo implode("", $last_lines);
} else {
    echo "Log file not found.\n";
}
