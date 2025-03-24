<?php

namespace Utility;

use Utility\HttpClient;

final class Downloader
{
    const STORE_DIR = '/.pcomposer-store';

    public static function downloadPackage(string $package, array $versionData): bool
    {
        if (self::hasPackage($package, $versionData['version'])) {
            return false;
        }

        $downloadUrl = self::getDownloadUrl($versionData);
        list($filePath, $storeDir) = self::downloadRepo($downloadUrl, $package, $versionData['version']);
        self::extractFile($filePath, $storeDir);
        return true;
    }

    private static function getDownloadUrl(array $versionData): string
    {
        return $versionData['dist']['url'] ?? '';
    }

    private static function downloadRepo(string $url, string $package, string $version): array
    {
        $storeDir = $_SERVER['HOME'] . self::STORE_DIR . '/' . $package . '/' . $version;
        if (!is_dir($storeDir)) {
            mkdir($storeDir, 0755, true);
        }

        $filePath = "$storeDir/$version.zip";
        $fileContents = HttpClient::get($url, false);

        if ($fileContents === false) {
            throw new \RuntimeException("Failed to download the file from URL: $url");
        }

        file_put_contents($filePath, $fileContents);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Failed to save the downloaded file to: $filePath");
        }

        return [$filePath, $storeDir];
    }

    private static function extractFile(string $filePath, string $storeDir): void
    {
        $zip = new \ZipArchive;
        $res = $zip->open($filePath);
        if ($res === TRUE) {
            $skipFirstDir = true;
            $firstDir = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileInfo = $zip->statIndex($i);
                $fileName = $fileInfo['name'];

                // Identify the first directory
                if ($skipFirstDir && substr($fileName, -1) === '/') {
                    $firstDir = $fileName;
                    $skipFirstDir = false;
                    continue;
                }

                // Skip .github and .gitignore files
                if (strpos($fileName, '.github') !== false || strpos($fileName, '.gitignore') !== false) {
                    continue;
                }

                $fileContents = $zip->getFromIndex($i);
                $relativePath = ltrim(str_replace($firstDir, '', $fileName), '/');
                $destinationPath = $storeDir . '/' . $relativePath;

                $destinationDir = dirname($destinationPath);
                if (!is_dir($destinationDir)) {
                    mkdir($destinationDir, 0755, true);
                }

                // Ensure the destination path is not a directory
                if (substr($destinationPath, -1) !== '/') {
                    file_put_contents($destinationPath, $fileContents);
                }
            }
            $zip->close();

            // Remove the zip file after extraction
            unlink($filePath);
        } else {
            throw new \RuntimeException("Failed to open the zip file: $filePath. Error code: $res");
        }
    }

    public static function hasPackage(string $package, string $version): bool
    {
        $storeDir = $_SERVER['HOME'] . self::STORE_DIR . '/' . $package . '/' . $version;
        return is_dir($storeDir);
    }
}