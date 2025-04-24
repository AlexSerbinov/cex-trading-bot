<?php

namespace App\helpers;

class LogRotator
{
    private const MAX_TOTAL_LOG_SIZE = 1 * 1024 * 1024 * 1024; // 1 GB in bytes
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB in bytes
    private const MAX_LOG_AGE_DAYS = 30;
    private string $logDir;

    public function __construct()
    {
        // Determine the log directory based on the environment
        $environment = getenv('ENVIRONMENT') ?: 'local';
        $this->logDir = __DIR__ . '/../../data/logs/' . $environment . '/';
        
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    public function getFileSize(string $logFile): int
    {
        $fullPath = $this->logDir . $logFile;
        return file_exists($fullPath) ? filesize($fullPath) : 0;
    }

    public function checkAndRotate(string $logFile): void
    {
        $fullPath = $this->logDir . $logFile;
        
        if (!file_exists($fullPath)) {
            return;
        }

        $fileSize = filesize($fullPath);
        $totalSize = $this->getTotalLogsSize();
        
        // Rotate if individual file size exceeds limit
        if ($fileSize > self::MAX_FILE_SIZE) {
            $this->rotateFile($logFile);
        }
        
        // If total size still exceeds limit, rotate the largest files
        if ($totalSize > self::MAX_TOTAL_LOG_SIZE) {
            $this->maintainSizeLimit();
        }

        // Clean old logs
        $this->cleanOldLogs($logFile);
    }

    private function rotateFile(string $logFile): void
    {
        $fullPath = $this->logDir . $logFile;
        $timestamp = date('Y-m-d_H-i-s');
        $newLogName = pathinfo($logFile, PATHINFO_FILENAME) . '_' . $timestamp . '.log';
        
        // Rotate the file
        rename($fullPath, $this->logDir . $newLogName);
        
        // Create new empty log file
        touch($fullPath);
        chmod($fullPath, 0666);
        
        // Log rotation event
        file_put_contents(
            $this->logDir . 'rotation.log',
            date('Y-m-d H:i:s') . " - Rotated {$logFile} (size: " . round(filesize($this->logDir . $newLogName) / (1024 * 1024), 2) . " MB)\n",
            FILE_APPEND
        );
    }

    private function maintainSizeLimit(): void
    {
        $files = glob($this->logDir . '*.log');
        $filesInfo = [];
        
        // Collect information about all log files
        foreach ($files as $file) {
            if (basename($file) === 'rotation.log') {
                continue; // Skip rotation log
            }
            
            $filesInfo[] = [
                'path' => $file,
                'size' => filesize($file),
                'time' => filemtime($file)
            ];
        }

        // Sort by size (largest first)
        usort($filesInfo, function($a, $b) {
            return $b['size'] - $a['size'];
        });

        $totalSize = array_sum(array_column($filesInfo, 'size'));
        $index = 0;

        // Rotate largest files until we're under the limit
        while ($totalSize > self::MAX_TOTAL_LOG_SIZE && $index < count($filesInfo)) {
            $file = $filesInfo[$index];
            $filename = basename($file['path']);
            
            // Skip if it's not a rotated file (no timestamp in name)
            if (strpos($filename, '_') === false) {
                $index++;
                continue;
            }
            
            // Delete old rotated file
            unlink($file['path']);
            
            // Log deletion
            file_put_contents(
                $this->logDir . 'rotation.log',
                date('Y-m-d H:i:s') . " - Deleted old log {$filename} (size: " . round($file['size'] / (1024 * 1024), 2) . " MB)\n",
                FILE_APPEND
            );

            $totalSize -= $file['size'];
            $index++;
        }
    }

    private function cleanOldLogs(string $baseLogName): void
    {
        $files = glob($this->logDir . pathinfo($baseLogName, PATHINFO_FILENAME) . '_*.log');
        
        foreach ($files as $file) {
            $fileTime = filemtime($file);
            $daysOld = (time() - $fileTime) / (60 * 60 * 24);
            
            if ($daysOld > self::MAX_LOG_AGE_DAYS) {
                $filename = basename($file);
                unlink($file);
                
                // Log deletion
                file_put_contents(
                    $this->logDir . 'rotation.log',
                    date('Y-m-d H:i:s') . " - Deleted old log {$filename} (age: {$daysOld} days)\n",
                    FILE_APPEND
                );
            }
        }
    }

    public function getTotalLogsSize(): int
    {
        $totalSize = 0;
        $files = glob($this->logDir . '*.log');
        
        foreach ($files as $file) {
            if (basename($file) !== 'rotation.log') {
                $totalSize += filesize($file);
            }
        }
        
        return $totalSize;
    }
} 