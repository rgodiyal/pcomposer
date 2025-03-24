<?php

namespace Utility;

use Utility\Msg;

final class HttpClient
{
    public static function get(string $url, bool $json = true): string
    {
        try {
            return self::sendRequest($url, $json);
        } catch (\Exception $e) {
            Msg::error($e->getMessage());
            exit;
        }
    }

    private static function sendRequest(string $url, bool $json = true): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0"); // GitHub requires a User-Agent

        if ($json) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json', // Set headers
            ]);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Accept: application/vnd.github.v3+json" // Ensures correct response format
            ]);
        }
        
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}