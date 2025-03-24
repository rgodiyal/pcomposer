<?php

namespace Utility;

use Utility\Msg;
use Utility\PcomposerJson;
use Utility\Downloader;

final class Package
{
    const PACKAGE_API_BASE_ENDPOINT = 'https://repo.packagist.org/p2/';

    public static function addPackage(string $package): void
    {
        list($name, $versionData) = self::getPackageNameAndVersionData($package);
        
        if (!self::hasPackage($name, $versionData['version'])) {
            Downloader::downloadPackage($name, $versionData);
            Msg::success("Package $package downloaded.");
            self::addPackageToPcomposerJson($name, $versionData['version']);
            self::linkPackageToVendor($name, $versionData['version']);
            Msg::success("Package $package added.");
            return;
        }
        
        Msg::info("Package $package already exists.");
    }

    private static function linkPackageToVendor(string $package, string $version): void
    {
        $storeDir = $_SERVER['HOME'] . Downloader::STORE_DIR . '/' . $package . '/' . $version;
        if (!is_dir($storeDir)) {
            Msg::error("Store directory $storeDir does not exist.");
            return;
        }
        $localVendorPath = "vendor/$package";
        
        if (file_exists($localVendorPath) && !is_link($localVendorPath)) {
            self::deleteDirectory($localVendorPath);
        }

        if (!file_exists($localVendorPath)) {
            if (!is_dir(dirname($localVendorPath))) {
                mkdir(dirname($localVendorPath), 0777, true);
            }
            symlink($storeDir, $localVendorPath);
            Msg::success("Linked $package to project vendor/");
            return;
        }
        
        Msg::info("Symlink already exists for $package, skipping...");
        return;
    }

    private static function deleteDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public static function getPackageNameAndVersionData(string $package): array
    {
        $parts = explode(':', $package);
        $name = $parts[0];
        $version = $parts[1] ?? '*';
        return [$name, self::getPackageData($name, $version)];
    }

    public static function addPackageToPcomposerJson(string $package, string $version): void
    {
        if (!PcomposerJson::isExists()) {
            $pcomposer = [
                'require' => [
                    $package => $version
                ]
            ];
            PcomposerJson::create($pcomposer);
            return;
        }

        if (PcomposerJson::hasPackage($package, $version)) {
            return;
        }
        
        $pcomposer = PcomposerJson::get();
        $pcomposer['require'][$package] = $version;
        PcomposerJson::update($pcomposer);
        return;
    }

    private static function getPackageData(string $package, string $version = ""): array
    {
        $packageData = HttpClient::get(self::getPackageApiUrl($package));
        $packageData = json_decode($packageData, true);

        if (!is_array($packageData)) {
            Msg::error("Package $package not found.");
            exit;
        }

        if (empty($version)) {
            return $packageData['packages'][$package];
        }

        if ($version === '*') {
            return $packageData['packages'][$package][0];
        }

        // Filter array based on version
        $result = array_filter($packageData['packages'][$package], function ($item) use ($version) {
            return isset($item['version']) && $item['version'] === $version;
        });

        if (empty($result)) {
            Msg::error("Version $version not found for package $package.");
            exit;
        }

        return array_values($result)[0];
    }

    private static function getPackageApiUrl(string $package): string
    {
        return self::PACKAGE_API_BASE_ENDPOINT . $package . '.json';
    }

    private static function hasPackage(string $package, string $version): bool
    {
        if (Downloader::hasPackage($package, $version) && PcomposerJson::hasPackage($package, $version) && is_link("vendor/$package")) {
            return true;
        }

        return false;
    }
}