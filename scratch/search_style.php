<?php
$filePath = 'c:\\laragon\\www\\water\\public\\kiosk.html';
if (file_exists($filePath)) {
    $lines = file($filePath);
    foreach ($lines as $i => $line) {
        if (strpos($line, '</style>') !== false) {
            $ln = $i + 1;
            echo "Line $ln: " . trim($line) . "\n";
        }
    }
} else {
    echo "File not found.\n";
}
