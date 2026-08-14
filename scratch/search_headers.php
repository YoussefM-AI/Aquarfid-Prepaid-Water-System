<?php
$filePath = 'c:\\laragon\\www\\water\\app\\Views\\layout\\header.php';
if (file_exists($filePath)) {
    $lines = file($filePath);
    foreach ($lines as $i => $line) {
        $lower = strtolower($line);
        if (strpos($lower, 'configuration') !== false || strpos($lower, 'personnel') !== false) {
            $ln = $i + 1;
            echo "Line $ln: " . trim($line) . "\n";
        }
    }
} else {
    echo "File not found.\n";
}
