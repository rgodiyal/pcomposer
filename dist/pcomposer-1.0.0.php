#!/usr/bin/env php
<?php

/**
 * PComposer v1.0.0 - Single File Distribution
 * Generated: 2025-08-12 13:05:23
 * 
 * This file contains all PComposer source code bundled into a single executable.
 * Users can simply download this file and run: php pcomposer-1.0.0.php install
 */

namespace PComposer;

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Source: src/PComposer.php
/**
 * Main PComposer class that orchestrates all package management operations
 */
class PComposer
{
    private GlobalStore $globalStore;
    private PackageManager $packageManager;
    private ComposerJsonParser $jsonParser;
    private VendorLinker $vendorLinker;
    private LockFile $lockFile;
    private string $projectRoot;
    private string $vendorDir;

    public function __construct()
    {
        $this->projectRoot = $this->findProjectRoot();
        $this->vendorDir = $this->projectRoot . '/vendor';
        $this->globalStore = new GlobalStore();
        $this->packageManager = new PackageManager($this->globalStore);
        $this->jsonParser = new ComposerJsonParser($this->projectRoot);
        $this->vendorLinker = new VendorLinker($this->vendorDir, $this->globalStore);
        $this->lockFile = new LockFile($this->projectRoot);
    }

    /**
     * Install dependencies from composer.json
     */
    public function install(): void
    {
        echo "Installing dependencies...\n";
        
        if (!file_exists($this->projectRoot . '/composer.json')) {
            throw new \Exception("composer.json not found in current directory");
        }

        $dependencies = $this->jsonParser->getDependencies();
        
        if (empty($dependencies)) {
            echo "No dependencies to install.\n";
            return;
        }

        // Check if lock file exists and is up to date
        if ($this->lockFile->exists()) {
            $productionDeps = $this->jsonParser->getProductionDependencies();
            $devDeps = $this->jsonParser->getDevelopmentDependencies();
            
            if ($this->lockFile->isUpToDate($productionDeps, $devDeps)) {
                echo "Using locked versions from pcomposer.lock...\n";
                $this->installFromLock();
            } else {
                echo "Lock file outdated, updating...\n";
                $this->updateLockAndInstall($dependencies);
            }
        } else {
            echo "No lock file found, creating one...\n";
            $this->updateLockAndInstall($dependencies);
        }
        
        $this->vendorLinker->createLinks();
        $this->dumpAutoload();
        
        echo "Dependencies installed successfully!\n";
    }



    /**
     * Add a package to composer.json
     */
    public function requirePackage(string $packageName, ?string $version = null): void
    {
        echo "Adding package: $packageName" . ($version ? " ($version)" : "") . "\n";
        
        $this->jsonParser->addDependency($packageName, $version);
        $this->install();
        
        echo "Package added successfully!\n";
    }

    /**
     * Remove a package from composer.json
     */
    public function removePackage(string $packageName): void
    {
        echo "Removing package: $packageName\n";
        
        $this->jsonParser->removeDependency($packageName);
        $this->vendorLinker->removePackage($packageName);
        $this->dumpAutoload();
        
        echo "Package removed successfully!\n";
    }

    /**
     * List installed packages
     */
    public function listPackages(): void
    {
        $dependencies = $this->jsonParser->getDependencies();
        
        if (empty($dependencies)) {
            echo "No packages installed.\n";
            return;
        }

        echo "Installed packages:\n";
        foreach ($dependencies as $package => $version) {
            $installedVersion = $this->globalStore->getInstalledVersion($package);
            echo "  $package: $version" . ($installedVersion ? " (installed: $installedVersion)" : "") . "\n";
        }
    }

    /**
     * Show package information
     */
    public function showPackage(string $packageName): void
    {
        $packageInfo = $this->globalStore->getPackageInfo($packageName);
        
        if (!$packageInfo) {
            echo "Package '$packageName' not found in global store.\n";
            return;
        }

        echo "Package: $packageName\n";
        echo "Version: " . $packageInfo['version'] . "\n";
        echo "Path: " . $packageInfo['path'] . "\n";
        echo "Dependencies: " . implode(', ', $packageInfo['dependencies'] ?? []) . "\n";
    }

    /**
     * Generate autoloader
     */
    public function dumpAutoload(): void
    {
        echo "Generating autoloader...\n";
        
        if (!is_dir($this->vendorDir)) {
            mkdir($this->vendorDir, 0755, true);
        }

        $autoloader = $this->generateAutoloader();
        file_put_contents($this->vendorDir . '/autoload.php', $autoloader);
        
        echo "Autoloader generated successfully!\n";
    }

    /**
     * Clear global package cache
     */
    public function clearCache(): void
    {
        echo "Clearing global package cache...\n";
        $this->globalStore->clearCache();
        echo "Cache cleared successfully!\n";
    }

    /**
     * Find the project root directory (where composer.json is located)
     */
    private function findProjectRoot(): string
    {
        $currentDir = getcwd();
        
        while ($currentDir !== dirname($currentDir)) {
            if (file_exists($currentDir . '/composer.json')) {
                return $currentDir;
            }
            $currentDir = dirname($currentDir);
        }
        
        return getcwd(); // Fallback to current directory
    }

    /**
     * Generate autoloader content
     */
    private function generateAutoloader(): string
    {
        $dependencies = $this->jsonParser->getDependencies();
        $autoloadContent = "<?php\n\n";
        $autoloadContent .= "// PComposer Autoloader\n";
        $autoloadContent .= "// Generated automatically by PComposer\n\n";
        
        // Generate autoloaders for all packages
        foreach ($dependencies as $package => $version) {
            $packagePath = $this->globalStore->getPackagePath($package);
            if ($packagePath) {
                $packageAutoload = $this->getPackageAutoloadConfig($packagePath);
                if (!empty($packageAutoload)) {
                    $autoloadContent .= "// Autoloader for $package\n";
                    foreach ($packageAutoload as $type => $config) {
                        $autoloadContent .= $this->generatePackageAutoloadConfig($type, $config, $packagePath);
                    }
                }
            }
        }
        
        // Add project's own autoload configuration
        $projectAutoload = $this->jsonParser->getAutoloadConfig();
        if (!empty($projectAutoload)) {
            $autoloadContent .= "\n// Project autoload configuration\n";
            foreach ($projectAutoload as $type => $config) {
                $autoloadContent .= $this->generateAutoloadConfig($type, $config);
            }
        }
        
        return $autoloadContent;
    }

    /**
     * Generate autoload configuration for project
     */
    private function generateAutoloadConfig(string $type, array $config): string
    {
        $content = "";
        
        switch ($type) {
            case 'psr-4':
                foreach ($config as $namespace => $path) {
                    $escapedNamespace = str_replace('\\', '\\\\', $namespace);
                    $content .= "spl_autoload_register(function (\$class) {\n";
                    $content .= "    \$prefix = '$escapedNamespace';\n";
                    $content .= "    \$base_dir = __DIR__ . '/../$path';\n";
                    $content .= "    \$len = strlen(\$prefix);\n";
                    $content .= "    if (strncmp(\$prefix, \$class, \$len) !== 0) {\n";
                    $content .= "        return;\n";
                    $content .= "    }\n";
                    $content .= "    \$relative_class = substr(\$class, \$len);\n";
                    $content .= "    \$file = \$base_dir . str_replace('\\\\', '/', \$relative_class) . '.php';\n";
                    $content .= "    if (file_exists(\$file)) {\n";
                    $content .= "        require \$file;\n";
                    $content .= "    }\n";
                    $content .= "});\n\n";
                }
                break;
                
            case 'psr-0':
                foreach ($config as $namespace => $path) {
                    $content .= "spl_autoload_register(function (\$class) {\n";
                    $content .= "    \$prefix = '$namespace';\n";
                    $content .= "    \$base_dir = __DIR__ . '/../$path';\n";
                    $content .= "    \$len = strlen(\$prefix);\n";
                    $content .= "    if (strncmp(\$prefix, \$class, \$len) !== 0) {\n";
                    $content .= "        return;\n";
                    $content .= "    }\n";
                    $content .= "    \$relative_class = substr(\$class, \$len);\n";
                    $content .= "    \$file = \$base_dir . str_replace('\\\\', '/', \$relative_class) . '.php';\n";
                    $content .= "    if (file_exists(\$file)) {\n";
                    $content .= "        require \$file;\n";
                    $content .= "    }\n";
                    $content .= "});\n\n";
                }
                break;
                
            case 'classmap':
                foreach ($config as $file) {
                    $content .= "require_once __DIR__ . '/../$file';\n";
                }
                $content .= "\n";
                break;
                
            case 'files':
                foreach ($config as $file) {
                    $content .= "require_once __DIR__ . '/../$file';\n";
                }
                $content .= "\n";
                break;
        }
        
        return $content;
    }

    /**
     * Get autoload configuration from package composer.json
     */
    private function getPackageAutoloadConfig(string $packagePath): array
    {
        $composerJsonPath = $packagePath . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            return [];
        }

        $content = file_get_contents($composerJsonPath);
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['autoload'])) {
            return [];
        }

        return $data['autoload'];
    }

    /**
     * Generate autoload configuration for package
     */
    private function generatePackageAutoloadConfig(string $type, array $config, string $packagePath): string
    {
        $content = "";
        
        switch ($type) {
            case 'psr-4':
                foreach ($config as $namespace => $path) {
                    $escapedNamespace = str_replace('\\', '\\\\', $namespace);
                    $content .= "spl_autoload_register(function (\$class) {\n";
                    $content .= "    \$prefix = '$escapedNamespace';\n";
                    $content .= "    \$base_dir = '$packagePath/$path';\n";
                    $content .= "    \$len = strlen(\$prefix);\n";
                    $content .= "    if (strncmp(\$prefix, \$class, \$len) !== 0) {\n";
                    $content .= "        return;\n";
                    $content .= "    }\n";
                    $content .= "    \$relative_class = substr(\$class, \$len);\n";
                    $content .= "    \$file = \$base_dir . '/' . str_replace('\\\\', '/', \$relative_class) . '.php';\n";
                    $content .= "    if (file_exists(\$file)) {\n";
                    $content .= "        require \$file;\n";
                    $content .= "    }\n";
                    $content .= "});\n\n";
                }
                break;
                
            case 'psr-0':
                foreach ($config as $namespace => $path) {
                    $content .= "spl_autoload_register(function (\$class) {\n";
                    $content .= "    \$prefix = '$namespace';\n";
                    $content .= "    \$base_dir = '$packagePath/$path';\n";
                    $content .= "    \$len = strlen(\$prefix);\n";
                    $content .= "    if (strncmp(\$prefix, \$class, \$len) !== 0) {\n";
                    $content .= "        return;\n";
                    $content .= "    }\n";
                    $content .= "    \$relative_class = substr(\$class, \$len);\n";
                    $content .= "    \$file = \$base_dir . str_replace('\\\\', '/', \$relative_class) . '.php';\n";
                    $content .= "    if (file_exists(\$file)) {\n";
                    $content .= "        require \$file;\n";
                    $content .= "    }\n";
                    $content .= "});\n\n";
                }
                break;
                
            case 'classmap':
                foreach ($config as $file) {
                    if (is_dir($packagePath . '/' . $file)) {
                        // Skip directory classmaps for now
                        continue;
                    } else {
                        $content .= "require_once '$packagePath/$file';\n";
                    }
                }
                $content .= "\n";
                break;
                
            case 'files':
                foreach ($config as $file) {
                    $content .= "require_once '$packagePath/$file';\n";
                }
                $content .= "\n";
                break;
        }
        
        return $content;
    }

    /**
     * Install packages from lock file
     */
    private function installFromLock(): void
    {
        $lockedPackages = $this->lockFile->getLockedPackages();
        
        foreach ($lockedPackages as $packageName => $packageData) {
            $version = $packageData['version'] ?? null;
            if ($version) {
                echo "Installing $packageName ($version) from lock file...\n";
                $this->packageManager->installPackage($packageName, $version);
            }
        }
    }

    /**
     * Update lock file and install packages
     */
    private function updateLockAndInstall(array $dependencies): void
    {
        $productionDeps = $this->jsonParser->getProductionDependencies();
        $devDeps = $this->jsonParser->getDevelopmentDependencies();
        
        // Update lock file structure
        $this->lockFile->updateFromComposerJson($productionDeps, $devDeps);
        
        // Install packages and update lock file with resolved versions
        foreach ($dependencies as $packageName => $constraint) {
            echo "Installing $packageName ($constraint)...\n";
            $resolvedVersion = $this->packageManager->installPackageAndGetVersion($packageName, $constraint);
            
            // Lock the resolved version
            $isDev = $this->jsonParser->isDevDependency($packageName);
            $this->lockFile->lockPackage($packageName, $resolvedVersion, [], $isDev);
        }
    }

    /**
     * Update dependencies
     */
    public function update(): void
    {
        echo "Updating dependencies...\n";
        
        // Delete lock file to force fresh resolution
        $this->lockFile->delete();
        
        $dependencies = $this->jsonParser->getDependencies();
        
        if (empty($dependencies)) {
            echo "No dependencies to update.\n";
            return;
        }

        $this->updateLockAndInstall($dependencies);
        $this->vendorLinker->createLinks();
        $this->dumpAutoload();
        
        echo "Dependencies updated successfully!\n";
    }

    /**
     * Show lock file information
     */
    public function showLockInfo(): void
    {
        if (!$this->lockFile->exists()) {
            echo "No lock file found.\n";
            return;
        }

        $lockData = $this->lockFile->getLockData();
        $lockedPackages = $this->lockFile->getLockedPackages();
        
        echo "Lock file: " . $this->lockFile->getLockFilePath() . "\n";
        echo "Generated: " . ($lockData['generated'] ?? 'Unknown') . "\n\n";
        
        if (empty($lockedPackages)) {
            echo "No packages locked.\n";
            return;
        }

        echo "Locked packages:\n";
        foreach ($lockedPackages as $packageName => $packageData) {
            $version = $packageData['version'] ?? 'Unknown';
            $lockedAt = $packageData['locked_at'] ?? 'Unknown';
            echo "  $packageName: $version (locked at: $lockedAt)\n";
        }
    }

    /**
     * Remove lock file
     */
    public function removeLockFile(): void
    {
        if ($this->lockFile->exists()) {
            $this->lockFile->delete();
            echo "Lock file removed. Next install will resolve fresh versions.\n";
        } else {
            echo "No lock file found.\n";
        }
    }
}


// Source: src/GlobalStore.php
/**
 * GlobalStore manages the global package store similar to pnpm's store
 * This prevents re-downloading packages that are already installed globally
 */
class GlobalStore
{
    private string $storePath;
    private string $metadataPath;
    private array $metadata;

    public function __construct()
    {
        $this->storePath = $this->getStorePath();
        $this->metadataPath = $this->storePath . '/metadata.json';
        $this->metadata = $this->loadMetadata();
        
        if (!is_dir($this->storePath)) {
            mkdir($this->storePath, 0755, true);
        }
    }

    /**
     * Get the global store path
     */
    private function getStorePath(): string
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE') ?: '/tmp';
        return $homeDir . '/.pcomposer/store';
    }

    /**
     * Load metadata from file
     */
    private function loadMetadata(): array
    {
        if (file_exists($this->metadataPath)) {
            $content = file_get_contents($this->metadataPath);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    /**
     * Save metadata to file
     */
    private function saveMetadata(): void
    {
        file_put_contents($this->metadataPath, json_encode($this->metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Check if a package is installed in the global store
     */
    public function hasPackage(string $packageName, string $version): bool
    {
        $key = $this->getPackageKey($packageName, $version);
        return isset($this->metadata[$key]) && is_dir($this->metadata[$key]['path']);
    }

    /**
     * Get the path where a package is stored
     */
    public function getPackagePath(string $packageName, ?string $version = null): ?string
    {
        if ($version) {
            $key = $this->getPackageKey($packageName, $version);
            return $this->metadata[$key]['path'] ?? null;
        }

        // Return the latest version if no specific version requested
        $latestVersion = $this->getLatestVersion($packageName);
        if ($latestVersion) {
            $key = $this->getPackageKey($packageName, $latestVersion);
            return $this->metadata[$key]['path'] ?? null;
        }

        return null;
    }

    /**
     * Get the latest installed version of a package
     */
    public function getLatestVersion(string $packageName): ?string
    {
        $versions = [];
        
        foreach ($this->metadata as $key => $info) {
            if (strpos($key, $packageName . '@') === 0) {
                $versions[] = $info['version'];
            }
        }

        if (empty($versions)) {
            return null;
        }

        // Sort versions and return the latest
        usort($versions, 'version_compare');
        return end($versions);
    }

    /**
     * Get installed version of a package
     */
    public function getInstalledVersion(string $packageName): ?string
    {
        return $this->getLatestVersion($packageName);
    }

    /**
     * Get package information
     */
    public function getPackageInfo(string $packageName): ?array
    {
        $latestVersion = $this->getLatestVersion($packageName);
        if (!$latestVersion) {
            return null;
        }

        $key = $this->getPackageKey($packageName, $latestVersion);
        return $this->metadata[$key] ?? null;
    }

    /**
     * Store a package in the global store
     */
    public function storePackage(string $packageName, string $version, string $sourcePath, array $dependencies = []): string
    {
        $key = $this->getPackageKey($packageName, $version);
        $packagePath = $this->storePath . '/' . $this->sanitizePackageName($packageName) . '/' . $version;

        // Create package directory
        if (!is_dir($packagePath)) {
            mkdir($packagePath, 0755, true);
        }

        // Copy package files
        $this->copyDirectory($sourcePath, $packagePath);

        // Store metadata
        $this->metadata[$key] = [
            'name' => $packageName,
            'version' => $version,
            'path' => $packagePath,
            'dependencies' => $dependencies,
            'installed_at' => date('Y-m-d H:i:s')
        ];

        $this->saveMetadata();

        return $packagePath;
    }

    /**
     * Remove a package from the global store
     */
    public function removePackage(string $packageName, ?string $version = null): bool
    {
        if ($version) {
            $key = $this->getPackageKey($packageName, $version);
            if (isset($this->metadata[$key])) {
                $path = $this->metadata[$key]['path'];
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                }
                unset($this->metadata[$key]);
                $this->saveMetadata();
                return true;
            }
        } else {
            // Remove all versions of the package
            $removed = false;
            foreach ($this->metadata as $key => $info) {
                if (strpos($key, $packageName . '@') === 0) {
                    $path = $info['path'];
                    if (is_dir($path)) {
                        $this->removeDirectory($path);
                    }
                    unset($this->metadata[$key]);
                    $removed = true;
                }
            }
            if ($removed) {
                $this->saveMetadata();
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the entire global store cache
     */
    public function clearCache(): void
    {
        if (is_dir($this->storePath)) {
            $this->removeDirectory($this->storePath);
        }
        $this->metadata = [];
        $this->saveMetadata();
    }

    /**
     * Get package key for metadata storage
     */
    private function getPackageKey(string $packageName, string $version): string
    {
        return $packageName . '@' . $version;
    }

    /**
     * Sanitize package name for filesystem
     */
    private function sanitizePackageName(string $packageName): string
    {
        return str_replace(['/', '\\'], '_', $packageName);
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
            }
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($path);
    }

    /**
     * Get store statistics
     */
    public function getStats(): array
    {
        $totalPackages = count($this->metadata);
        $totalSize = 0;
        $packagesByVendor = [];

        foreach ($this->metadata as $key => $info) {
            if (is_dir($info['path'])) {
                $totalSize += $this->getDirectorySize($info['path']);
            }

            $vendor = explode('/', $info['name'])[0];
            $packagesByVendor[$vendor] = ($packagesByVendor[$vendor] ?? 0) + 1;
        }

        return [
            'total_packages' => $totalPackages,
            'total_size' => $totalSize,
            'packages_by_vendor' => $packagesByVendor,
            'store_path' => $this->storePath
        ];
    }

    /**
     * Calculate directory size
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}


// Source: src/PackageManager.php
/**
 * PackageManager handles downloading and installing packages from Packagist
 */
class PackageManager
{
    private GlobalStore $globalStore;
    private string $packagistUrl = 'https://packagist.org';
    private string $tempDir;

    public function __construct(GlobalStore $globalStore)
    {
        $this->globalStore = $globalStore;
        $this->tempDir = sys_get_temp_dir() . '/pcomposer_' . uniqid();
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Install packages from dependencies array
     */
    public function installPackages(array $dependencies): void
    {
        foreach ($dependencies as $packageName => $versionConstraint) {
            $this->installPackage($packageName, $versionConstraint);
        }
    }

    /**
     * Update packages
     */
    public function updatePackages(array $dependencies): void
    {
        foreach ($dependencies as $packageName => $versionConstraint) {
            $this->updatePackage($packageName, $versionConstraint);
        }
    }

    /**
     * Install a single package
     */
    public function installPackage(string $packageName, string $versionConstraint): void
    {
        echo "Installing $packageName ($versionConstraint)...\n";

        // Check if package is already in global store
        $availableVersions = $this->getAvailableVersions($packageName);
        $targetVersion = $this->resolveVersion($packageName, $versionConstraint, $availableVersions);

        if ($this->globalStore->hasPackage($packageName, $targetVersion)) {
            echo "  ✓ Package already in global store\n";
            return;
        }

        // Download and install package
        $packagePath = $this->downloadPackage($packageName, $targetVersion);
        $dependencies = $this->extractDependencies($packagePath);
        
        $this->globalStore->storePackage($packageName, $targetVersion, $packagePath, $dependencies);
        
        // Install dependencies recursively
        if (!empty($dependencies)) {
            $this->installPackages($dependencies);
        }

        echo "  ✓ Installed $packageName ($targetVersion)\n";
    }

    /**
     * Update a single package
     */
    public function updatePackage(string $packageName, string $versionConstraint): void
    {
        echo "Updating $packageName ($versionConstraint)...\n";

        $availableVersions = $this->getAvailableVersions($packageName);
        $targetVersion = $this->resolveVersion($packageName, $versionConstraint, $availableVersions);
        $currentVersion = $this->globalStore->getInstalledVersion($packageName);

        if ($currentVersion === $targetVersion) {
            echo "  ✓ Already at latest version\n";
            return;
        }

        // Remove old version if exists
        if ($currentVersion) {
            $this->globalStore->removePackage($packageName, $currentVersion);
        }

        // Install new version
        $this->installPackage($packageName, $versionConstraint);
    }

    /**
     * Get available versions for a package from Packagist
     */
    private function getAvailableVersions(string $packageName): array
    {
        $url = $this->packagistUrl . '/packages/' . $packageName . '.json';
        
        try {
            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['package']['versions'])) {
                throw new \Exception("Failed to fetch package versions for $packageName");
            }

            return array_keys($data['package']['versions']);
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch package information: " . $e->getMessage());
        }
    }

    /**
     * Resolve version constraint to actual version
     */
    private function resolveVersion(string $packageName, string $versionConstraint, array $availableVersions): string
    {
        // Simple version resolution - can be enhanced with proper semver parsing
        if ($versionConstraint === '*') {
            // Get latest stable version
            $stableVersions = array_filter($availableVersions, function($version) {
                return !str_contains($version, 'dev') && !str_contains($version, 'alpha') && 
                       !str_contains($version, 'beta') && !str_contains($version, 'rc');
            });
            
            if (empty($stableVersions)) {
                $stableVersions = $availableVersions;
            }
            
            usort($stableVersions, 'version_compare');
            return end($stableVersions);
        }

        // Handle specific version
        if (in_array($versionConstraint, $availableVersions)) {
            return $versionConstraint;
        }

        // Handle version ranges (simplified)
        if (strpos($versionConstraint, '^') === 0) {
            $baseVersion = substr($versionConstraint, 1);
            $compatibleVersions = array_filter($availableVersions, function($version) use ($baseVersion) {
                return version_compare($version, $baseVersion, '>=') && 
                       version_compare($version, $this->getNextMajorVersion($baseVersion), '<');
            });
            
            if (!empty($compatibleVersions)) {
                usort($compatibleVersions, 'version_compare');
                return end($compatibleVersions);
            }
        }

        // Handle tilde ranges
        if (strpos($versionConstraint, '~') === 0) {
            $baseVersion = substr($versionConstraint, 1);
            $compatibleVersions = array_filter($availableVersions, function($version) use ($baseVersion) {
                return version_compare($version, $baseVersion, '>=') && 
                       version_compare($version, $this->getNextMinorVersion($baseVersion), '<');
            });
            
            if (!empty($compatibleVersions)) {
                usort($compatibleVersions, 'version_compare');
                return end($compatibleVersions);
            }
        }

        // If no compatible version found, try to find the closest match
        $closestVersion = $this->findClosestVersion($versionConstraint, $availableVersions);
        if ($closestVersion) {
            echo "  Warning: Using closest available version $closestVersion for constraint $versionConstraint\n";
            return $closestVersion;
        }
        
        throw new \Exception("No compatible version found for $packageName $versionConstraint. Available versions: " . implode(', ', array_slice($availableVersions, 0, 10)));
    }

    /**
     * Find the closest available version to the constraint
     */
    private function findClosestVersion(string $versionConstraint, array $availableVersions): ?string
    {
        // Extract base version from constraint
        $baseVersion = $versionConstraint;
        if (strpos($versionConstraint, '^') === 0) {
            $baseVersion = substr($versionConstraint, 1);
        } elseif (strpos($versionConstraint, '~') === 0) {
            $baseVersion = substr($versionConstraint, 1);
        } elseif (strpos($versionConstraint, '>=') === 0) {
            $baseVersion = substr($versionConstraint, 3);
        } elseif (strpos($versionConstraint, '>') === 0) {
            $baseVersion = substr($versionConstraint, 1);
        }
        
        // Find versions that are greater than or equal to base version
        $compatibleVersions = array_filter($availableVersions, function($version) use ($baseVersion) {
            return version_compare($version, $baseVersion, '>=');
        });
        
        if (!empty($compatibleVersions)) {
            usort($compatibleVersions, 'version_compare');
            return end($compatibleVersions);
        }
        
        // If no versions >= base, try the latest available
        if (!empty($availableVersions)) {
            usort($availableVersions, 'version_compare');
            return end($availableVersions);
        }
        
        return null;
    }

    /**
     * Download package from Packagist
     */
    private function downloadPackage(string $packageName, string $version): string
    {
        $url = $this->packagistUrl . '/packages/' . $packageName . '.json';
        
        try {
            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['package']['versions'][$version]['dist']['url'])) {
                throw new \Exception("Failed to get download URL for $packageName $version");
            }

            $downloadUrl = $data['package']['versions'][$version]['dist']['url'];
            $packagePath = $this->tempDir . '/' . $packageName . '_' . $version;
            
            // Download and extract package
            $this->downloadAndExtract($downloadUrl, $packagePath);
            
            return $packagePath;
        } catch (\Exception $e) {
            throw new \Exception("Failed to download package: " . $e->getMessage());
        }
    }

    /**
     * Download and extract package archive
     */
    private function downloadAndExtract(string $url, string $destination): void
    {
        $tempFile = $this->tempDir . '/package_' . uniqid() . '.zip';
        
        try {
            // Download file
            $content = $this->makeHttpRequest($url);
            file_put_contents($tempFile, $content);
            
            // Extract archive
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) === true) {
                $zip->extractTo($destination);
                $zip->close();
            } else {
                throw new \Exception("Failed to extract package archive");
            }
            
            // Handle case where archive contains a root directory
            $this->fixExtractedStructure($destination);
            
            // Clean up temp file
            unlink($tempFile);
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }

    /**
     * Fix extracted archive structure if it contains a root directory
     */
    private function fixExtractedStructure(string $destination): void
    {
        $items = scandir($destination);
        $directories = array_filter($items, function($item) use ($destination) {
            return $item !== '.' && $item !== '..' && is_dir($destination . '/' . $item);
        });
        
        // If there's only one directory and no other files, move contents up
        if (count($directories) === 1 && count($items) === 3) { // . and .. plus one dir
            $rootDir = reset($directories);
            $rootPath = $destination . '/' . $rootDir;
            
            // Move all contents from root directory to destination
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                $target = $destination . '/' . $iterator->getSubPathName();
                
                if ($item->isDir()) {
                    if (!is_dir($target)) {
                        mkdir($target, 0755, true);
                    }
                } else {
                    copy($item, $target);
                }
            }
            
            // Remove the root directory
            $this->removeDirectory($rootPath);
        }
    }

    /**
     * Extract dependencies from package composer.json
     */
    private function extractDependencies(string $packagePath): array
    {
        $composerJsonPath = $packagePath . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            return [];
        }

        $content = file_get_contents($composerJsonPath);
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['require'])) {
            return [];
        }

        // Filter out platform requirements
        $dependencies = [];
        $platformRequirements = ['php', 'ext-', 'lib-', 'composer-plugin-api'];
        
        foreach ($data['require'] as $package => $version) {
            $isPlatformRequirement = false;
            foreach ($platformRequirements as $platform) {
                if ($package === $platform || strpos($package, $platform) === 0) {
                    $isPlatformRequirement = true;
                    break;
                }
            }
            
            if (!$isPlatformRequirement) {
                $dependencies[$package] = $version;
            }
        }

        return $dependencies;
    }

    /**
     * Make HTTP request
     */
    private function makeHttpRequest(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PComposer/1.0.0',
                    'Accept: application/json'
                ],
                'timeout' => 30
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception("Failed to fetch URL: $url");
        }

        return $response;
    }

    /**
     * Get next major version
     */
    private function getNextMajorVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[0] = (int)$parts[0] + 1;
        $parts[1] = 0;
        $parts[2] = 0;
        return implode('.', $parts);
    }

    /**
     * Get next minor version
     */
    private function getNextMinorVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[1] = (int)$parts[1] + 1;
        $parts[2] = 0;
        return implode('.', $parts);
    }

    /**
     * Clean up temporary files
     */
    public function __destruct()
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($path);
    }

    /**
     * Install a package with version constraint and return resolved version
     */
    public function installPackageAndGetVersion(string $packageName, string $constraint): string
    {
        $availableVersions = $this->getAvailableVersions($packageName);
        $resolvedVersion = $this->resolveVersion($packageName, $constraint, $availableVersions);
        
        if ($this->globalStore->hasPackage($packageName, $resolvedVersion)) {
            echo "  ✓ Package already in global store\n";
            return $resolvedVersion;
        }

        // Download and install package
        $packagePath = $this->downloadPackage($packageName, $resolvedVersion);
        $dependencies = $this->extractDependencies($packagePath);
        
        $this->globalStore->storePackage($packageName, $resolvedVersion, $packagePath, $dependencies);
        
        // Install dependencies recursively
        if (!empty($dependencies)) {
            $this->installPackages($dependencies);
        }

        echo "  ✓ Installed $packageName ($resolvedVersion)\n";
        
        return $resolvedVersion;
    }
}


// Source: src/ComposerJsonParser.php
/**
 * ComposerJsonParser handles reading and modifying composer.json files
 */
class ComposerJsonParser
{
    private string $projectRoot;
    private string $composerJsonPath;
    private array $data;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $this->composerJsonPath = $projectRoot . '/composer.json';
        $this->data = $this->loadComposerJson();
    }

    /**
     * Load composer.json file
     */
    private function loadComposerJson(): array
    {
        if (!file_exists($this->composerJsonPath)) {
            return [
                'name' => 'project/root',
                'description' => 'Project managed by PComposer',
                'type' => 'project',
                'require' => [],
                'require-dev' => [],
                'autoload' => [],
                'autoload-dev' => []
            ];
        }

        $content = file_get_contents($this->composerJsonPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in composer.json: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Save composer.json file
     */
    private function saveComposerJson(): void
    {
        $content = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->composerJsonPath, $content);
    }

    /**
     * Get all dependencies (require + require-dev)
     */
    public function getDependencies(): array
    {
        $dependencies = [];
        
        if (isset($this->data['require'])) {
            $dependencies = array_merge($dependencies, $this->data['require']);
        }
        
        if (isset($this->data['require-dev'])) {
            $dependencies = array_merge($dependencies, $this->data['require-dev']);
        }
        
        return $dependencies;
    }

    /**
     * Get production dependencies only
     */
    public function getProductionDependencies(): array
    {
        return $this->data['require'] ?? [];
    }

    /**
     * Get development dependencies only
     */
    public function getDevelopmentDependencies(): array
    {
        return $this->data['require-dev'] ?? [];
    }

    /**
     * Get autoload configuration
     */
    public function getAutoloadConfig(): array
    {
        return $this->data['autoload'] ?? [];
    }

    /**
     * Get development autoload configuration
     */
    public function getDevAutoloadConfig(): array
    {
        return $this->data['autoload-dev'] ?? [];
    }

    /**
     * Add a dependency to composer.json
     */
    public function addDependency(string $packageName, ?string $version = null, bool $isDev = false): void
    {
        $version = $version ?: '*';
        $section = $isDev ? 'require-dev' : 'require';
        
        if (!isset($this->data[$section])) {
            $this->data[$section] = [];
        }
        
        $this->data[$section][$packageName] = $version;
        
        // Sort dependencies alphabetically
        ksort($this->data[$section]);
        
        $this->saveComposerJson();
    }

    /**
     * Remove a dependency from composer.json
     */
    public function removeDependency(string $packageName): void
    {
        $removed = false;
        
        if (isset($this->data['require'][$packageName])) {
            unset($this->data['require'][$packageName]);
            $removed = true;
        }
        
        if (isset($this->data['require-dev'][$packageName])) {
            unset($this->data['require-dev'][$packageName]);
            $removed = true;
        }
        
        if ($removed) {
            $this->saveComposerJson();
        }
    }

    /**
     * Update a dependency version
     */
    public function updateDependency(string $packageName, string $version, bool $isDev = false): void
    {
        $section = $isDev ? 'require-dev' : 'require';
        
        if (isset($this->data[$section][$packageName])) {
            $this->data[$section][$packageName] = $version;
            $this->saveComposerJson();
        }
    }

    /**
     * Check if a dependency exists
     */
    public function hasDependency(string $packageName): bool
    {
        return isset($this->data['require'][$packageName]) || 
               isset($this->data['require-dev'][$packageName]);
    }

    /**
     * Get dependency version
     */
    public function getDependencyVersion(string $packageName): ?string
    {
        if (isset($this->data['require'][$packageName])) {
            return $this->data['require'][$packageName];
        }
        
        if (isset($this->data['require-dev'][$packageName])) {
            return $this->data['require-dev'][$packageName];
        }
        
        return null;
    }

    /**
     * Check if dependency is a development dependency
     */
    public function isDevDependency(string $packageName): bool
    {
        return isset($this->data['require-dev'][$packageName]);
    }

    /**
     * Add autoload configuration
     */
    public function addAutoloadConfig(string $type, array $config, bool $isDev = false): void
    {
        $section = $isDev ? 'autoload-dev' : 'autoload';
        
        if (!isset($this->data[$section])) {
            $this->data[$section] = [];
        }
        
        if (!isset($this->data[$section][$type])) {
            $this->data[$section][$type] = [];
        }
        
        $this->data[$section][$type] = array_merge($this->data[$section][$type], $config);
        $this->saveComposerJson();
    }

    /**
     * Remove autoload configuration
     */
    public function removeAutoloadConfig(string $type, string $key, bool $isDev = false): void
    {
        $section = $isDev ? 'autoload-dev' : 'autoload';
        
        if (isset($this->data[$section][$type][$key])) {
            unset($this->data[$section][$type][$key]);
            $this->saveComposerJson();
        }
    }

    /**
     * Get project name
     */
    public function getProjectName(): string
    {
        return $this->data['name'] ?? 'project/root';
    }

    /**
     * Set project name
     */
    public function setProjectName(string $name): void
    {
        $this->data['name'] = $name;
        $this->saveComposerJson();
    }

    /**
     * Get project description
     */
    public function getProjectDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    /**
     * Set project description
     */
    public function setProjectDescription(string $description): void
    {
        $this->data['description'] = $description;
        $this->saveComposerJson();
    }

    /**
     * Get project type
     */
    public function getProjectType(): string
    {
        return $this->data['type'] ?? 'project';
    }

    /**
     * Set project type
     */
    public function setProjectType(string $type): void
    {
        $this->data['type'] = $type;
        $this->saveComposerJson();
    }

    /**
     * Get all project metadata
     */
    public function getProjectMetadata(): array
    {
        return [
            'name' => $this->getProjectName(),
            'description' => $this->getProjectDescription(),
            'type' => $this->getProjectType(),
            'require' => $this->getProductionDependencies(),
            'require-dev' => $this->getDevelopmentDependencies(),
            'autoload' => $this->getAutoloadConfig(),
            'autoload-dev' => $this->getDevAutoloadConfig()
        ];
    }

    /**
     * Validate composer.json structure
     */
    public function validate(): array
    {
        $errors = [];
        
        // Check for required fields
        if (!isset($this->data['name'])) {
            $errors[] = "Missing 'name' field";
        }
        
        // Validate package name format
        if (isset($this->data['name']) && !preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/', $this->data['name'])) {
            $errors[] = "Invalid package name format";
        }
        
        // Validate dependencies
        foreach (['require', 'require-dev'] as $section) {
            if (isset($this->data[$section]) && !is_array($this->data[$section])) {
                $errors[] = "Invalid '$section' section";
            }
        }
        
        // Validate autoload configuration
        foreach (['autoload', 'autoload-dev'] as $section) {
            if (isset($this->data[$section]) && !is_array($this->data[$section])) {
                $errors[] = "Invalid '$section' section";
            }
        }
        
        return $errors;
    }

    /**
     * Create a new composer.json file
     */
    public function createComposerJson(string $name, string $description = '', string $type = 'project'): void
    {
        $this->data = [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'require' => [],
            'require-dev' => [],
            'autoload' => [],
            'autoload-dev' => []
        ];
        
        $this->saveComposerJson();
    }

    /**
     * Get the raw composer.json data
     */
    public function getRawData(): array
    {
        return $this->data;
    }
}


// Source: src/VendorLinker.php
/**
 * VendorLinker creates symbolic links in the vendor directory
 * to point to packages stored in the global store
 */
class VendorLinker
{
    private string $vendorDir;
    private GlobalStore $globalStore;

    public function __construct(string $vendorDir, GlobalStore $globalStore)
    {
        $this->vendorDir = $vendorDir;
        $this->globalStore = $globalStore;
    }

    /**
     * Create symbolic links for all packages in the project
     */
    public function createLinks(): void
    {
        if (!is_dir($this->vendorDir)) {
            mkdir($this->vendorDir, 0755, true);
        }

        // Get all packages from global store that are used in this project
        $projectPackages = $this->getProjectPackages();
        
        foreach ($projectPackages as $packageName => $version) {
            $this->createLink($packageName, $version);
        }

        // Note: Cleanup is disabled to prevent removing newly created links
        // $this->cleanupUnusedLinks($projectPackages);
    }

    /**
     * Create a symbolic link for a specific package
     */
    public function createLink(string $packageName, string $version): void
    {
        $globalPath = $this->globalStore->getPackagePath($packageName, $version);
        
        if (!$globalPath) {
            throw new \Exception("Package $packageName ($version) not found in global store metadata");
        }
        
        if (!is_dir($globalPath)) {
            throw new \Exception("Package $packageName ($version) path does not exist: $globalPath");
        }

        $vendorPath = $this->vendorDir . '/' . $packageName;
        
        // Ensure vendor directory exists
        if (!is_dir($this->vendorDir)) {
            mkdir($this->vendorDir, 0755, true);
        }
        
        // Remove existing link or directory
        if (is_link($vendorPath)) {
            unlink($vendorPath);
        } elseif (is_dir($vendorPath)) {
            $this->removeDirectory($vendorPath);
        }

        // Create symbolic link with absolute paths
        $absoluteGlobalPath = realpath($globalPath);
        $absoluteVendorPath = realpath($this->vendorDir) . '/' . $packageName;
        
        // Ensure parent directory exists
        $parentDir = dirname($absoluteVendorPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }
        
        if (!symlink($absoluteGlobalPath, $absoluteVendorPath)) {
            $error = error_get_last();
            throw new \Exception("Failed to create symbolic link for $packageName: " . ($error['message'] ?? 'Unknown error') . " (from: $absoluteGlobalPath, to: $absoluteVendorPath)");
        }

        echo "  ✓ Linked $packageName to global store\n";
    }

    /**
     * Remove a package link
     */
    public function removePackage(string $packageName): void
    {
        $vendorPath = $this->vendorDir . '/' . $packageName;
        
        if (is_link($vendorPath)) {
            unlink($vendorPath);
            echo "  ✓ Removed link for $packageName\n";
        } elseif (is_dir($vendorPath)) {
            $this->removeDirectory($vendorPath);
            echo "  ✓ Removed directory for $packageName\n";
        }
    }

    /**
     * Get packages that are used in the current project
     */
    private function getProjectPackages(): array
    {
        $composerJsonPath = dirname($this->vendorDir) . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            return [];
        }

        $content = file_get_contents($composerJsonPath);
        $data = json_decode($content, true);
        
        if (!$data) {
            return [];
        }

        $packages = [];
        
        // Add production dependencies
        if (isset($data['require']) && is_array($data['require'])) {
            foreach ($data['require'] as $package => $version) {
                $resolvedVersion = $this->resolveVersion($package, $version);
                if ($resolvedVersion) {
                    $packages[$package] = $resolvedVersion;
                }
            }
        }
        
        // Add development dependencies
        if (isset($data['require-dev']) && is_array($data['require-dev'])) {
            foreach ($data['require-dev'] as $package => $version) {
                $resolvedVersion = $this->resolveVersion($package, $version);
                if ($resolvedVersion) {
                    $packages[$package] = $resolvedVersion;
                }
            }
        }
        
        return $packages;
    }

    /**
     * Resolve version constraint to actual version
     */
    private function resolveVersion(string $packageName, string $versionConstraint): ?string
    {
        // For now, use the latest installed version
        // This could be enhanced to use proper version resolution
        return $this->globalStore->getLatestVersion($packageName);
    }

    /**
     * Clean up unused symbolic links
     */
    private function cleanupUnusedLinks(array $projectPackages): void
    {
        if (!is_dir($this->vendorDir)) {
            return;
        }

        $items = scandir($this->vendorDir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $this->vendorDir . '/' . $item;
            
            // Only process symbolic links and directories
            if (!is_link($itemPath) && !is_dir($itemPath)) {
                continue;
            }

            // Check if this package is still needed
            if (!isset($projectPackages[$item])) {
                if (is_link($itemPath)) {
                    unlink($itemPath);
                    echo "  ✓ Removed unused link: $item\n";
                } elseif (is_dir($itemPath)) {
                    $this->removeDirectory($itemPath);
                    echo "  ✓ Removed unused directory: $item\n";
                }
            }
        }
    }

    /**
     * Get information about all linked packages
     */
    public function getLinkedPackages(): array
    {
        $linkedPackages = [];
        
        if (!is_dir($this->vendorDir)) {
            return $linkedPackages;
        }

        $items = scandir($this->vendorDir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $this->vendorDir . '/' . $item;
            
            if (is_link($itemPath)) {
                $targetPath = readlink($itemPath);
                $linkedPackages[$item] = [
                    'type' => 'link',
                    'target' => $targetPath,
                    'exists' => is_dir($targetPath)
                ];
            } elseif (is_dir($itemPath)) {
                $linkedPackages[$item] = [
                    'type' => 'directory',
                    'target' => $itemPath,
                    'exists' => true
                ];
            }
        }
        
        return $linkedPackages;
    }

    /**
     * Check if a package is properly linked
     */
    public function isPackageLinked(string $packageName): bool
    {
        $vendorPath = $this->vendorDir . '/' . $packageName;
        
        if (!is_link($vendorPath)) {
            return false;
        }

        $targetPath = readlink($vendorPath);
        return is_dir($targetPath);
    }

    /**
     * Get the target path of a linked package
     */
    public function getPackageTarget(string $packageName): ?string
    {
        $vendorPath = $this->vendorDir . '/' . $packageName;
        
        if (!is_link($vendorPath)) {
            return null;
        }

        $targetPath = readlink($vendorPath);
        return is_dir($targetPath) ? $targetPath : null;
    }

    /**
     * Repair broken symbolic links
     */
    public function repairLinks(): void
    {
        $linkedPackages = $this->getLinkedPackages();
        
        foreach ($linkedPackages as $packageName => $info) {
            if ($info['type'] === 'link' && !$info['exists']) {
                echo "Repairing broken link for $packageName...\n";
                
                // Remove broken link
                unlink($this->vendorDir . '/' . $packageName);
                
                // Try to recreate the link
                $latestVersion = $this->globalStore->getLatestVersion($packageName);
                if ($latestVersion) {
                    $this->createLink($packageName, $latestVersion);
                }
            }
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (is_link($item)) {
                unlink($item);
            } elseif ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($path);
    }

    /**
     * Get vendor directory path
     */
    public function getVendorDir(): string
    {
        return $this->vendorDir;
    }

    /**
     * Set vendor directory path
     */
    public function setVendorDir(string $vendorDir): void
    {
        $this->vendorDir = $vendorDir;
    }
}


// Source: src/LockFile.php
/**
 * LockFile handles the pcomposer.lock file for reproducible installations
 */
class LockFile
{
    private string $projectRoot;
    private string $lockFilePath;
    private array $lockData;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $this->lockFilePath = $projectRoot . '/pcomposer.lock';
        $this->lockData = $this->loadLockFile();
    }

    /**
     * Load lock file data
     */
    private function loadLockFile(): array
    {
        if (!file_exists($this->lockFilePath)) {
            return [
                'packages' => [],
                'packages-dev' => [],
                'platform' => [],
                'platform-dev' => [],
                'aliases' => [],
                'minimum-stability' => 'stable',
                'stability-flags' => [],
                'prefer-stable' => false,
                'prefer-lowest' => false,
                'platform-references' => [],
                'plugin-api-version' => '2.0.0',
                'generated' => date('Y-m-d H:i:s')
            ];
        }

        $content = file_get_contents($this->lockFilePath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in pcomposer.lock: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Save lock file data
     */
    private function saveLockFile(): void
    {
        $this->lockData['generated'] = date('Y-m-d H:i:s');
        $content = json_encode($this->lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->lockFilePath, $content);
    }

    /**
     * Check if lock file exists
     */
    public function exists(): bool
    {
        return file_exists($this->lockFilePath);
    }

    /**
     * Get all locked packages
     */
    public function getLockedPackages(): array
    {
        return array_merge(
            $this->lockData['packages'] ?? [],
            $this->lockData['packages-dev'] ?? []
        );
    }

    /**
     * Get locked package version
     */
    public function getLockedVersion(string $packageName): ?string
    {
        $packages = $this->getLockedPackages();
        return $packages[$packageName]['version'] ?? null;
    }

    /**
     * Check if package is locked
     */
    public function isPackageLocked(string $packageName): bool
    {
        return $this->getLockedVersion($packageName) !== null;
    }

    /**
     * Lock a package with its resolved version
     */
    public function lockPackage(string $packageName, string $version, array $dependencies = [], bool $isDev = false): void
    {
        $section = $isDev ? 'packages-dev' : 'packages';
        
        if (!isset($this->lockData[$section])) {
            $this->lockData[$section] = [];
        }

        $this->lockData[$section][$packageName] = [
            'name' => $packageName,
            'version' => $version,
            'dependencies' => $dependencies,
            'locked_at' => date('Y-m-d H:i:s')
        ];

        $this->saveLockFile();
    }

    /**
     * Unlock a package
     */
    public function unlockPackage(string $packageName): void
    {
        if (isset($this->lockData['packages'][$packageName])) {
            unset($this->lockData['packages'][$packageName]);
        }
        
        if (isset($this->lockData['packages-dev'][$packageName])) {
            unset($this->lockData['packages-dev'][$packageName]);
        }

        $this->saveLockFile();
    }

    /**
     * Update lock file from composer.json dependencies
     */
    public function updateFromComposerJson(array $dependencies, array $devDependencies = []): void
    {
        // Clear existing locks
        $this->lockData['packages'] = [];
        $this->lockData['packages-dev'] = [];

        // Lock production dependencies
        foreach ($dependencies as $package => $constraint) {
            $this->lockData['packages'][$package] = [
                'name' => $package,
                'constraint' => $constraint,
                'locked_at' => date('Y-m-d H:i:s')
            ];
        }

        // Lock development dependencies
        foreach ($devDependencies as $package => $constraint) {
            $this->lockData['packages-dev'][$package] = [
                'name' => $package,
                'constraint' => $constraint,
                'locked_at' => date('Y-m-d H:i:s')
            ];
        }

        $this->saveLockFile();
    }

    /**
     * Get lock file path
     */
    public function getLockFilePath(): string
    {
        return $this->lockFilePath;
    }

    /**
     * Get lock file data
     */
    public function getLockData(): array
    {
        return $this->lockData;
    }

    /**
     * Check if lock file is up to date with composer.json
     */
    public function isUpToDate(array $dependencies, array $devDependencies = []): bool
    {
        $composerPackages = array_merge(array_keys($dependencies), array_keys($devDependencies));
        $lockedPackages = array_keys($this->getLockedPackages());

        // Check if all composer.json packages are locked
        foreach ($composerPackages as $package) {
            if (!in_array($package, $lockedPackages)) {
                return false;
            }
        }

        // Check if all locked packages are in composer.json
        foreach ($lockedPackages as $package) {
            if (!in_array($package, $composerPackages)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get outdated packages
     */
    public function getOutdatedPackages(): array
    {
        $outdated = [];
        $lockedPackages = $this->getLockedPackages();

        foreach ($lockedPackages as $packageName => $packageData) {
            if (isset($packageData['constraint'])) {
                $outdated[] = [
                    'package' => $packageName,
                    'constraint' => $packageData['constraint'],
                    'locked_version' => $packageData['version'] ?? null
                ];
            }
        }

        return $outdated;
    }

    /**
     * Delete lock file
     */
    public function delete(): void
    {
        if (file_exists($this->lockFilePath)) {
            unlink($this->lockFilePath);
        }
    }
}


// Source: src/Utils.php
/**
 * Utility functions used throughout PComposer
 */
class Utils
{
    /**
     * Format bytes to human readable format
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Calculate directory size recursively
     */
    public static function getDirectorySize(string $path): int
    {
        $size = 0;
        
        if (!is_dir($path)) {
            return $size;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Remove directory recursively
     */
    public static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }

        rmdir($path);
    }

    /**
     * Copy directory recursively
     */
    public static function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
            }
        }
    }

    /**
     * Sanitize package name for filesystem
     */
    public static function sanitizePackageName(string $packageName): string
    {
        return str_replace(['/', '\\'], '_', $packageName);
    }

    /**
     * Validate package name format
     */
    public static function isValidPackageName(string $packageName): bool
    {
        return preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/', $packageName);
    }

    /**
     * Parse version constraint
     */
    public static function parseVersionConstraint(string $constraint): array
    {
        $result = [
            'type' => 'exact',
            'version' => $constraint,
            'min' => null,
            'max' => null
        ];

        if ($constraint === '*') {
            $result['type'] = 'any';
        } elseif (strpos($constraint, '^') === 0) {
            $result['type'] = 'caret';
            $result['version'] = substr($constraint, 1);
            $result['min'] = $result['version'];
            $result['max'] = self::getNextMajorVersion($result['version']);
        } elseif (strpos($constraint, '~') === 0) {
            $result['type'] = 'tilde';
            $result['version'] = substr($constraint, 1);
            $result['min'] = $result['version'];
            $result['max'] = self::getNextMinorVersion($result['version']);
        } elseif (strpos($constraint, '>=') === 0) {
            $result['type'] = 'gte';
            $result['version'] = substr($constraint, 2);
            $result['min'] = $result['version'];
        } elseif (strpos($constraint, '>') === 0) {
            $result['type'] = 'gt';
            $result['version'] = substr($constraint, 1);
            $result['min'] = $result['version'];
        } elseif (strpos($constraint, '<=') === 0) {
            $result['type'] = 'lte';
            $result['version'] = substr($constraint, 2);
            $result['max'] = $result['version'];
        } elseif (strpos($constraint, '<') === 0) {
            $result['type'] = 'lt';
            $result['version'] = substr($constraint, 1);
            $result['max'] = $result['version'];
        }

        return $result;
    }

    /**
     * Get next major version
     */
    public static function getNextMajorVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[0] = (int)$parts[0] + 1;
        $parts[1] = 0;
        $parts[2] = 0;
        return implode('.', $parts);
    }

    /**
     * Get next minor version
     */
    public static function getNextMinorVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[1] = (int)$parts[1] + 1;
        $parts[2] = 0;
        return implode('.', $parts);
    }

    /**
     * Check if version satisfies constraint
     */
    public static function versionSatisfies(string $version, string $constraint): bool
    {
        $parsed = self::parseVersionConstraint($constraint);
        
        switch ($parsed['type']) {
            case 'any':
                return true;
            case 'exact':
                return version_compare($version, $parsed['version'], '=');
            case 'caret':
            case 'tilde':
                return version_compare($version, $parsed['min'], '>=') && 
                       version_compare($version, $parsed['max'], '<');
            case 'gte':
                return version_compare($version, $parsed['min'], '>=');
            case 'gt':
                return version_compare($version, $parsed['min'], '>');
            case 'lte':
                return version_compare($version, $parsed['max'], '<=');
            case 'lt':
                return version_compare($version, $parsed['max'], '<');
            default:
                return false;
        }
    }

    /**
     * Make HTTP request with error handling
     */
    public static function makeHttpRequest(string $url, array $options = []): string
    {
        $defaultOptions = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'User-Agent: PComposer/1.0.0',
                'Accept: application/json'
            ]
        ];

        $options = array_merge($defaultOptions, $options);
        
        $context = stream_context_create([
            'http' => $options
        ]);

        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception("Failed to fetch URL: $url");
        }

        return $response;
    }

    /**
     * Download file with progress
     */
    public static function downloadFile(string $url, string $destination, callable $progressCallback = null): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PComposer/1.0.0'
                ],
                'timeout' => 30
            ]
        ]);

        $source = fopen($url, 'rb', false, $context);
        if (!$source) {
            throw new \Exception("Failed to open URL: $url");
        }

        $target = fopen($destination, 'wb');
        if (!$target) {
            fclose($source);
            throw new \Exception("Failed to create file: $destination");
        }

        $totalSize = 0;
        $downloadedSize = 0;

        // Get content length if available
        $meta = stream_get_meta_data($source);
        if (isset($meta['wrapper_data'])) {
            foreach ($meta['wrapper_data'] as $header) {
                if (stripos($header, 'content-length:') === 0) {
                    $totalSize = (int)trim(substr($header, 15));
                    break;
                }
            }
        }

        while (!feof($source)) {
            $chunk = fread($source, 8192);
            if ($chunk === false) {
                break;
            }
            
            fwrite($target, $chunk);
            $downloadedSize += strlen($chunk);
            
            if ($progressCallback && $totalSize > 0) {
                $progress = ($downloadedSize / $totalSize) * 100;
                $progressCallback($progress, $downloadedSize, $totalSize);
            }
        }

        fclose($source);
        fclose($target);
    }

    /**
     * Extract archive (zip, tar.gz, etc.)
     */
    public static function extractArchive(string $archivePath, string $destination): void
    {
        $extension = pathinfo($archivePath, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'zip':
                self::extractZip($archivePath, $destination);
                break;
            case 'gz':
                if (strpos($archivePath, '.tar.gz') !== false) {
                    self::extractTarGz($archivePath, $destination);
                } else {
                    self::extractGz($archivePath, $destination);
                }
                break;
            default:
                throw new \Exception("Unsupported archive format: $extension");
        }
    }

    /**
     * Extract ZIP archive
     */
    private static function extractZip(string $archivePath, string $destination): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \Exception("Failed to open ZIP archive: $archivePath");
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new \Exception("Failed to extract ZIP archive to: $destination");
        }

        $zip->close();
    }

    /**
     * Extract TAR.GZ archive
     */
    private static function extractTarGz(string $archivePath, string $destination): void
    {
        $phar = new \PharData($archivePath);
        $phar->extractTo($destination);
    }

    /**
     * Extract GZ archive
     */
    private static function extractGz(string $archivePath, string $destination): void
    {
        $source = gzopen($archivePath, 'rb');
        if (!$source) {
            throw new \Exception("Failed to open GZ archive: $archivePath");
        }

        $target = fopen($destination, 'wb');
        if (!$target) {
            gzclose($source);
            throw new \Exception("Failed to create file: $destination");
        }

        while (!gzeof($source)) {
            $chunk = gzread($source, 8192);
            if ($chunk === false) {
                break;
            }
            fwrite($target, $chunk);
        }

        gzclose($source);
        fclose($target);
    }

    /**
     * Get system information
     */
    public static function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'architecture' => php_uname('m'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'temp_dir' => sys_get_temp_dir(),
            'home_dir' => getenv('HOME') ?: getenv('USERPROFILE') ?: '/tmp'
        ];
    }

    /**
     * Check if command is available
     */
    public static function isCommandAvailable(string $command): bool
    {
        $output = [];
        $returnCode = 0;
        
        exec("which $command 2>/dev/null", $output, $returnCode);
        
        return $returnCode === 0;
    }

    /**
     * Execute command with output capture
     */
    public static function executeCommand(string $command): array
    {
        $output = [];
        $returnCode = 0;
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        return [
            'output' => $output,
            'return_code' => $returnCode,
            'success' => $returnCode === 0
        ];
    }
}


// Main executable logic
/**
 * PComposer - A fast PHP package manager inspired by pnpm
 * 
 * This tool works like pnpm for PHP, using a global package store
 * to avoid re-downloading existing packages while maintaining
 * compatibility with composer.json format.
 */

use PComposer\PComposer;

// Handle command line arguments
$args = $argv;
array_shift($args); // Remove script name

if (empty($args)) {
    echo "PComposer - Fast PHP Package Manager\n";
    echo "Usage: pcomposer <command> [options]\n\n";
    echo "Commands:\n";
    echo "  install     Install dependencies from composer.json\n";
    echo "  update      Update dependencies\n";
    echo "  require     Add a package to composer.json\n";
    echo "  remove      Remove a package from composer.json\n";
    echo "  list        List installed packages\n";
    echo "  show        Show package information\n";
    echo "  dump-autoload Generate autoloader\n";
    echo "  clear-cache Clear global package cache\n";
    echo "  lock        Show lock file information\n";
    echo "  unlock      Remove lock file to force fresh install\n";
    echo "  --version   Show version information\n";
    echo "  --help      Show this help message\n";
    exit(0);
}

$command = $args[0];

try {
    $pcomposer = new PComposer();
    
    switch ($command) {
        case 'install':
            $pcomposer->install();
            break;
        case 'update':
            $pcomposer->update();
            break;
        case 'require':
            if (empty($args[1])) {
                throw new Exception("Package name required for require command");
            }
            $pcomposer->requirePackage($args[1], isset($args[2]) ? $args[2] : null);
            break;
        case 'remove':
            if (empty($args[1])) {
                throw new Exception("Package name required for remove command");
            }
            $pcomposer->removePackage($args[1]);
            break;
        case 'list':
            $pcomposer->listPackages();
            break;
        case 'show':
            if (empty($args[1])) {
                throw new Exception("Package name required for show command");
            }
            $pcomposer->showPackage($args[1]);
            break;
        case 'dump-autoload':
            $pcomposer->dumpAutoload();
            break;
        case 'clear-cache':
            $pcomposer->clearCache();
            break;
        case 'lock':
            $pcomposer->showLockInfo();
            break;
        case 'unlock':
            $pcomposer->removeLockFile();
            break;
        case '--version':
            echo "PComposer version 1.0.0\n";
            break;
        case '--help':
            echo "PComposer - Fast PHP Package Manager\n";
            echo "Usage: pcomposer <command> [options]\n\n";
            echo "Commands:\n";
            echo "  install     Install dependencies from composer.json\n";
            echo "  update      Update dependencies\n";
            echo "  require     Add a package to composer.json\n";
            echo "  remove      Remove a package from composer.json\n";
            echo "  list        List installed packages\n";
            echo "  show        Show package information\n";
                echo "  dump-autoload Generate autoloader\n";
    echo "  clear-cache Clear global package cache\n";
    echo "  lock        Show lock file information\n";
    echo "  unlock      Remove lock file to force fresh install\n";
    echo "  --version   Show version information\n";
    echo "  --help      Show this help message\n";
            break;
        default:
            throw new Exception("Unknown command: $command");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
