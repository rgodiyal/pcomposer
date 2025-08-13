<?php

namespace PComposer;

/**
 * Main PComposer class that orchestrates all package management operations
 * 
 * @author Rahul Godiyal <rgodiyal482@gmail.com>
 * @version 1.0.0
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
