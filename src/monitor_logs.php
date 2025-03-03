<?php

declare(strict_types=1);

require_once __DIR__ . '/logger.php';

$logger = new Logger(false);
$logFile = $logger->getLogFile();

echo "Моніторинг файлу логів: {$logFile}" . PHP_EOL;
echo "Натисніть Ctrl+C для виходу" . PHP_EOL;

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