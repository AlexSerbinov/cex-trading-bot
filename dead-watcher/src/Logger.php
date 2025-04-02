<?php

namespace DeadWatcher;

class Logger
{
    private bool $consoleOutput;
    private string $logFile;

    public function __construct(bool $consoleOutput = true, string $logFile = __DIR__ . '/../logs/dead-watcher.log')
    {
        $this->consoleOutput = $consoleOutput;
        $this->logFile = $logFile;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [INFO]: $message";

        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);

        if ($this->consoleOutput) {
            error_log($logMessage);
            if (php_sapi_name() === 'cli') {
                echo $logMessage . PHP_EOL;
            }
        }
    }

    public function error(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [ERROR]: $message";

        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);

        if ($this->consoleOutput) {
            error_log($logMessage);
            if (php_sapi_name() === 'cli') {
                echo $logMessage . PHP_EOL;
            }
        }
    }
}