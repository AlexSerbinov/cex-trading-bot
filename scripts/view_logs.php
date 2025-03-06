<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/core/Logger.php';

// Parse command line arguments
$options = getopt('n:c', ['lines:', 'clear']);

// Number of lines to display
$lines = isset($options['n']) ? (int)$options['n'] : (isset($options['lines']) ? (int)$options['lines'] : 0);

// Clear logs?
$clear = isset($options['c']) || isset($options['clear']);

$logger = Logger::getInstance(false);  // Use getInstance instead of new

if ($clear) {
    $logger->clearLog();
    echo "Log file cleared: {$logger->getLogFile()}" . PHP_EOL;
    exit;
}

$logContent = $logger->getLogContent($lines);

if (empty($logContent)) {
    echo "Log file is empty or does not exist: {$logger->getLogFile()}" . PHP_EOL;
    exit;
}

// Display logs
echo $logContent . PHP_EOL;

// Display information about the number of lines
$totalLines = substr_count($logContent, PHP_EOL) + 1;
echo "Displayed {$totalLines} lines from file: {$logger->getLogFile()}" . PHP_EOL; 