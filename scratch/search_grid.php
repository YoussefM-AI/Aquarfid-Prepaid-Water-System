<?php
$filePath = 'c:\\laragon\\www\\water\\app\\Views\\admin\\dashboard.php';
if (file_exists($filePath)) {
    $lines = file($filePath);
    foreach ($lines as $i => $line) {
        if (strpos($line, 'bornes-grid-container') !== false || strpos($line, 'api/bornes/status-live') !== false) {
            $ln = $i + 1;
            echo "Line $ln: " . trim($line) . "\n";
        }
    }
} else {
    echo "File not found.\n";
}
