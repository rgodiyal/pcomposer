<?php

namespace Commands;

use Utility\Msg;
use Utility\Package;

final class RequireCmd
{
    public function __construct(array $argv)
    {
        if (count($argv) < 3) {
            Msg::error("Usage: pcomposer require [vendor/package]");
            exit;
        }
        
        Package::addPackage($argv[2]);
    }
}