<?php

namespace Utility;

use Utility\Msg;

final class PcomposerJson
{
    const P_COMPOSER_JSON = 'pcomposer.json';

    public static function get(): array
    {
        return json_decode(file_get_contents(self::P_COMPOSER_JSON), true);
    }

    public static function isExists(): bool
    {
        return file_exists(self::P_COMPOSER_JSON);
    }

    public static function create(array $data): void
    {
        file_put_contents(self::P_COMPOSER_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Msg::success(self::P_COMPOSER_JSON . " created.");
    }

    public static function update(array $data): void
    {
        file_put_contents(self::P_COMPOSER_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function hasPackage(string $package, string $version): bool
    {
        if (self::isExists()) {
            $pcomposer = self::get();
            return isset($pcomposer['require'][$package]) && $pcomposer['require'][$package] === $version;
        }
        
        return false;
    }
}