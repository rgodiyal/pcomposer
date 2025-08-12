<?php

namespace PComposer;

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
