<?php

namespace Utility;

final class Msg
{
    public static function error(string $msg): void
    {
        echo "\033[31m$msg\033[0m\n";
    }

    public static function success(string $msg): void
    {
        echo "\033[32m$msg\033[0m\n";
    }

    public static function info(string $msg): void
    {
        echo "\033[33m$msg\033[0m\n";
    }
}