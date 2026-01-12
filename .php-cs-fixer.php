<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/**
 * This is a shim to use Laravel Pint syntax on older versions of PHP.
 * 
 * @link https://github.com/laravel/pint
 */

$basePath = rtrim(realpath(__DIR__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$overridePath = $basePath . 'pint.override.json';

$configPath = file_exists($overridePath)
    ? $overridePath
    : $basePath . 'pint.json';

if (! file_exists($configPath)) {
    throw new RuntimeException("The configuration file [{$configPath}] is missing.");
}

$pintConfig = json_decode(file_get_contents($configPath), true);

if (! is_array($pintConfig)) {
    throw new RuntimeException("The configuration file [{$configPath}] is not valid JSON.");
}

$finder = Finder::create()
    ->in(getcwd())
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

if (isset($pintConfig['exclude'])) {
    $finder->exclude($pintConfig['exclude']);
}

if (isset($pintConfig['notPath'])) {
    $finder->notPath($pintConfig['notPath']);
}

if (isset($pintConfig['notName'])) {
    $finder->notName($pintConfig['notName']);
}

return (new Config)
    ->setFinder($finder)
    ->setRules(isset($pintConfig['rules']) ? $pintConfig['rules'] : [])
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
