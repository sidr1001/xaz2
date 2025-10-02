#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

// Load env from config/.env (same as public bootstrap)
\Dotenv\Dotenv::createImmutable($root . '/config', '.env')->safeLoad();

// Run migration
\App\Console\MigrateHashAgentPasswords::run();

echo "Done.\n";

