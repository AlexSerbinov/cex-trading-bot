<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/core/Logger.php';

$logger = Logger::getInstance(false);
$logFile = $logger->getLogFile();

echo "Monitoring the log file: {$logFile}" . PHP_EOL;
echo "Press Ctrl+C to exit" . PHP_EOL;

$lastSize = 0;

while (true) {
    clearstatcache();
    
    if (file_exists($logFile)) {
        $currentSize = filesize($logFile);
        
        if ($currentSize > $lastSize) {
            $handle = fopen($logFile, 'r');
            fseek($handle, $lastSize);
            $newContent = fread($handle, $currentSize - $lastSize);
            fclose($handle);
            
            echo $newContent;
            $lastSize = $currentSize;
        }
    }
    
    sleep(1);
} 