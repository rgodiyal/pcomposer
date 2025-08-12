<?php

namespace PComposer;

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
