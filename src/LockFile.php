<?php

namespace PComposer;

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
