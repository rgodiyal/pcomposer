<?php

require __DIR__ . '/../vendor/autoload.php'; // Adjust path as needed

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a log channel
$log = new Logger('myapp');
$log->pushHandler(new StreamHandler('myapp.log', Logger::WARNING));

// Add records
$log->warning('This is a warning');
$log->error('This is an error');

echo "✅ Log written!\n";
