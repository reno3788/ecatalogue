<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "<pre>";
$status = $kernel->call('route:clear');
echo "route:clear exit code: {$status}\n";
echo $kernel->output();

$status2 = $kernel->call('cache:clear');
echo "cache:clear exit code: {$status2}\n";
echo $kernel->output();

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCache reset!\n";
}
echo "</pre>";
