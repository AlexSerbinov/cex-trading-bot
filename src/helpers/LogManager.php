<?php

namespace App\helpers;

require_once __DIR__ . '/LogRotator.php';

class LogManager
{
    private const CHECK_INTERVAL = 3; // 3 seconds
    private static ?LogManager $instance = null;
    private LogRotator $logRotator;
    private int $lastCheck = 0;
    private array $logFiles = [
        'bot.log',
        'router.log',
        'backend.log',
        'frontend.log',
        'BTCUSDC.log'
    ];

    private function __construct()
    {
        $this->logRotator = new LogRotator();
        $this->lastCheck = 0; // Set to 0 to force first check
        $this->log("LogManager initialized");
        $this->checkLogs(); // Initial check on startup
    }

    public static function getInstance(): LogManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function log(string $message): void
    {
        $logMessage = date('Y-m-d H:i:s') . " [LogManager] " . $message . "\n";
        $environment = getenv('ENVIRONMENT') ?: 'local';
        $logFile = __DIR__ . '/../../data/logs/' . $environment . '/manager.log';
        
        // Ensuring the log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(
            $logFile,
            $logMessage,
            FILE_APPEND
        );
    }

    public function checkLogs(): void
    {
        $currentTime = time();
        $timeSinceLastCheck = $currentTime - $this->lastCheck;
        
        $this->log("Checking logs... Time since last check: {$timeSinceLastCheck} seconds");
        
        // Check if it's time to rotate logs
        if ($timeSinceLastCheck >= self::CHECK_INTERVAL) {
            $this->log("Starting periodic log check");
            
            foreach ($this->logFiles as $logFile) {
                $this->log("Checking file: {$logFile}");
                $size = $this->logRotator->getFileSize($logFile);
                $this->log("Current size of {$logFile}: " . round($size / (1024 * 1024), 2) . " MB");
                $this->logRotator->checkAndRotate($logFile);
            }
            
            // Get total size after rotation
            $totalSize = $this->logRotator->getTotalLogsSize();
            $totalSizeGB = round($totalSize / (1024 * 1024), 2);
            
            $this->log("Periodic check completed. Total logs size: {$totalSizeGB} MB");
            
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $rotationLogPath = __DIR__ . '/../../data/logs/' . $environment . '/rotation.log';
            $logDir = dirname($rotationLogPath);
            
            // Ensure log directory exists
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Log the check in rotation.log
            file_put_contents(
                $rotationLogPath,
                date('Y-m-d H:i:s') . " - Periodic log check completed. Total logs size: {$totalSizeGB} MB\n",
                FILE_APPEND
            );
            
            $this->lastCheck = $currentTime;
        } else {
            $this->log("Skipping check - not enough time passed since last check");
        }
    }

    public function forceCheck(): void
    {
        $this->log("Starting forced log check");
        
        foreach ($this->logFiles as $logFile) {
            $this->log("Force checking file: {$logFile}");
            $size = $this->logRotator->getFileSize($logFile);
            $this->log("Current size of {$logFile}: " . round($size / (1024 * 1024), 2) . " MB");
            $this->logRotator->checkAndRotate($logFile);
        }
        
        $totalSize = $this->logRotator->getTotalLogsSize();
        $totalSizeGB = round($totalSize / (1024 * 1024), 2);
        
        $this->log("Forced check completed. Total logs size: {$totalSizeGB} MB");
        
        $environment = getenv('ENVIRONMENT') ?: 'local';
        $rotationLogPath = __DIR__ . '/../../data/logs/' . $environment . '/rotation.log';
        $logDir = dirname($rotationLogPath);
        
        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(
            $rotationLogPath,
            date('Y-m-d H:i:s') . " - Forced log check completed. Total logs size: {$totalSizeGB} MB\n",
            FILE_APPEND
        );
        
        $this->lastCheck = time();
    }
} 