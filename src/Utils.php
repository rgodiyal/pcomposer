<?php

namespace PComposer;

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
