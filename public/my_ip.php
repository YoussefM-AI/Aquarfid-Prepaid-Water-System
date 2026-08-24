<?php
$output = shell_exec('ipconfig');
echo "<pre>" . htmlspecialchars($output) . "</pre>";
