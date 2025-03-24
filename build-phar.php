<?php

$buildDir = 'build';
$pharFile = 'pcomposer.phar';
$targetDir = "$buildDir/$pharFile";
$sourceDir = 'src';

// Create build directory if it does not exist
if (!is_dir($buildDir)) {
    mkdir($buildDir, 0777, true);
}

try {
    // Remove old PHAR file if exists
    if (file_exists($pharFile)) {
        unlink($pharFile);
    }

    // Create a new PHAR archive
    $phar = new Phar($targetDir, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $pharFile);

    // Add all files from the source directory
    $phar->buildFromDirectory($sourceDir);

    // Set the stub to enable direct execution
    $stub = <<<STUB
    #!/usr/bin/env php
    <?php
    Phar::mapPhar('pcomposer.phar');
    require 'phar://pcomposer.phar/index.php';
    __HALT_COMPILER();
    ?>
    STUB;

    $phar->setStub($stub);

    // Make the PHAR executable
    chmod($targetDir, 0755);

    echo "PHAR file '$targetDir' created successfully.\n";
} catch (Exception $e) {
    echo "Error creating PHAR: " . $e->getMessage() . "\n";
}

?>
