<?php

namespace PComposer;

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
