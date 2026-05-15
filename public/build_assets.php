<?php
header('Content-Type: text/plain');
set_time_limit(300); 

echo "Initiating frontend asset compilation for PO attachments...\n";
$output = [];
$result_code = 0;

$projectRoot = realpath(__DIR__ . '/../');
exec("cd " . escapeshellarg($projectRoot) . " && npm run build 2>&1", $output, $result_code);

echo implode("\n", $output) . "\n";
echo "\nCompilation complete. Exit status: " . $result_code . "\n";
?>
