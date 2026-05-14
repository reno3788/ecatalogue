<?php
header('Content-Type: text/plain');
set_time_limit(60);

$projectRoot = realpath(__DIR__ . '/../');
echo "Project Root: " . $projectRoot . "\n";

function runCmd($cmd, $cwd) {
    echo "\n=== COMMAND: $cmd ===\n";
    $descriptorspec = [
       1 => ["pipe", "w"], // stdout
       2 => ["pipe", "w"]  // stderr
    ];
    $process = proc_open($cmd, $descriptorspec, $pipes, $cwd);
    if (is_resource($process)) {
        echo stream_get_contents($pipes[1]);
        echo stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    } else {
        echo "Failed to run command.\n";
    }
}

runCmd("git status", $projectRoot);
runCmd("git branch -a", $projectRoot);
runCmd("git log -n 5 --oneline", $projectRoot);
runCmd("git remote -v", $projectRoot);
?>
