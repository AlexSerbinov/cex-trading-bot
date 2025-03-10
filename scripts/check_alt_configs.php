<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/core/Logger.php';

$logger = Logger::getInstance();
$logger->log("Checking alternative storage locations for configuration...");

// Check the main config
$mainConfig = __DIR__ . '/../config/bots_config.json';
if (file_exists($mainConfig)) {
    $content = file_get_contents($mainConfig);
    $config = json_decode($content, true);
    $logger->log("Main config: " . implode(", ", array_keys($config ?? [])));
}

// Search for any other JSON files in the project that may contain configuration
$command = "find " . __DIR__ . "/../ -name '*.json' | grep -v 'vendor' | grep -v 'composer'";
exec($command, $files);

foreach ($files as $file) {
    if ($file !== $mainConfig) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if (is_array($data) && !empty($data)) {
            $firstKey = array_key_first($data);
            $firstValue = $data[$firstKey];
            
            if (is_array($firstValue) && isset($firstValue['exchange'])) {
                $logger->log("WARNING: Possible config found in file: " . $file);
                $logger->log("Pairs: " . implode(", ", array_keys($data)));
            }
        }
    }
}

$logger->log("Check completed"); 