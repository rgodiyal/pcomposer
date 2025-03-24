<?php
/**
 * Author: Rahul Godiyal
 * Email: rgodiyal482@gmail.com
 * Description: Performant Composer (Pcomposer) is way faster than Composer. It uses a global directory to install packages instead of storing them locally like Composer.
 */

// Autoload classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use Commands\RequireCmd;

// Check if running in CLI
if (php_sapi_name() !== 'cli') {
    exit("This script must be run from the command line.\n");
}

// Handle commands
if ($argc > 1) {
    switch ($argv[1]) {
        case '--version':
            echo "\033[32mPcomposer version 1.0.0\033[0m\n";
            break;
        case 'require':
            new RequireCmd($argv);
            break;
        default:
            echo "Unknown command.\n";
    }
} else {
    echo "Usage: pcomposer [command]\n";
}
