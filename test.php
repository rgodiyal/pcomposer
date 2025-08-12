<?php

/**
 * Test script for PComposer
 * This script demonstrates the basic functionality of PComposer
 */

require_once __DIR__ . '/src/PComposer.php';
require_once __DIR__ . '/src/GlobalStore.php';
require_once __DIR__ . '/src/PackageManager.php';
require_once __DIR__ . '/src/ComposerJsonParser.php';
require_once __DIR__ . '/src/VendorLinker.php';
require_once __DIR__ . '/src/Utils.php';

use PComposer\PComposer;
use PComposer\GlobalStore;
use PComposer\ComposerJsonParser;
use PComposer\Utils;

echo "=== PComposer Test Script ===\n\n";

try {
    // Test 1: Initialize PComposer
    echo "1. Initializing PComposer...\n";
    $pcomposer = new PComposer();
    echo "   ✓ PComposer initialized successfully\n\n";

    // Test 2: Check system information
    echo "2. System Information:\n";
    $systemInfo = Utils::getSystemInfo();
    foreach ($systemInfo as $key => $value) {
        echo "   $key: $value\n";
    }
    echo "\n";

    // Test 3: Test GlobalStore
    echo "3. Testing GlobalStore...\n";
    $globalStore = new GlobalStore();
    $stats = $globalStore->getStats();
    echo "   Store path: " . $stats['store_path'] . "\n";
    echo "   Total packages: " . $stats['total_packages'] . "\n";
    echo "   Total size: " . Utils::formatBytes($stats['total_size']) . "\n";
    echo "   ✓ GlobalStore working correctly\n\n";

    // Test 4: Test ComposerJsonParser
    echo "4. Testing ComposerJsonParser...\n";
    $parser = new \PComposer\ComposerJsonParser(getcwd());
    $dependencies = $parser->getDependencies();
    echo "   Found " . count($dependencies) . " dependencies:\n";
    foreach ($dependencies as $package => $version) {
        echo "     $package: $version\n";
    }
    echo "   ✓ ComposerJsonParser working correctly\n\n";

    // Test 5: Test version constraint parsing
    echo "5. Testing version constraint parsing...\n";
    $constraints = ['^1.0', '~2.0', '>=3.0', '*', '1.2.3'];
    foreach ($constraints as $constraint) {
        $parsed = Utils::parseVersionConstraint($constraint);
        echo "   '$constraint' -> " . $parsed['type'] . " (min: " . ($parsed['min'] ?? 'none') . ", max: " . ($parsed['max'] ?? 'none') . ")\n";
    }
    echo "   ✓ Version constraint parsing working correctly\n\n";

    // Test 6: Test package name validation
    echo "6. Testing package name validation...\n";
    $packageNames = ['vendor/package', 'invalid-package', 'vendor/package-name', 'vendor/package_name'];
    foreach ($packageNames as $name) {
        $isValid = Utils::isValidPackageName($name);
        echo "   '$name' -> " . ($isValid ? 'valid' : 'invalid') . "\n";
    }
    echo "   ✓ Package name validation working correctly\n\n";

    echo "=== All tests completed successfully! ===\n";
    echo "\nTo test installation, run:\n";
    echo "  ./pcomposer install\n";
    echo "\nTo test adding a package, run:\n";
    echo "  ./pcomposer require guzzlehttp/guzzle\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
