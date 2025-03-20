<?php

declare(strict_types=1);

/**
 * Class for logging.
 */
class Logger
{
    // Константи для рівнів логування
    public const DEBUG = 0;
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;
    public const CRITICAL = 4;
    
    private string $logFile;
    private bool $consoleOutput;
    private int $logLevel;
    private static ?Logger $instance = null;

    /**
     * Constructor
     * 
     * @param bool $consoleOutput Output logs to the console
     * @param string|null $logFile Path to the log file (if null, the default path is used)
     * @param int $logLevel Minimum log level to record (default: INFO)
     */
    private function __construct(bool $consoleOutput = true, ?string $logFile = null, int $logLevel = self::WARNING)
    {
        $this->consoleOutput = $consoleOutput;
        $this->logFile = $logFile ?? __DIR__ . '/../../data/logs/bot.log';
        $this->logLevel = $logLevel;
        
        // Creating the log directory if it does not exist
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Getting an instance of the class (Singleton)
     */
    public static function getInstance(bool $consoleOutput = true, ?string $logFile = null, int $logLevel = self::WARNING): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self($consoleOutput, $logFile, $logLevel);
        }
        return self::$instance;
    }

    /**
     * Logging a debug message
     */
    public function debug(string $message): void
    {
        if ($this->logLevel <= self::DEBUG) {
            $this->log('[DEBUG] ' . $message);
        }
    }
    
    /**
     * Logging an info message
     */
    public function info(string $message): void
    {
        if ($this->logLevel <= self::INFO) {
            $this->log('[INFO] ' . $message);
        }
    }
    
    /**
     * Logging a warning message
     */
    public function warning(string $message): void
    {
        if ($this->logLevel <= self::WARNING) {
            $this->log('[WARNING] ' . $message);
        }
    }
    
    /**
     * Logging an error message
     */
    public function error(string $message): void
    {
        if ($this->logLevel <= self::ERROR) {
            $this->log('[ERROR] ' . $message);
        }
    }
    
    /**
     * Logging a critical error message
     */
    public function critical(string $message): void
    {
        if ($this->logLevel <= self::CRITICAL) {
            $this->log('[CRITICAL] ' . $message);
        }
    }
    
    /**
     * Logs a stack trace
     */
    public function logStackTrace(string $message = 'Stack trace:'): void
    {
        $stackTrace = debug_backtrace();
        $traceStr = $message . "\n";
        
        // Пропускаємо перший елемент, бо це сам виклик logStackTrace
        for ($i = 1; $i < count($stackTrace); $i++) {
            $frame = $stackTrace[$i];
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';
            $file = $frame['file'] ?? '(unknown file)';
            $line = $frame['line'] ?? '(unknown line)';
            
            $traceStr .= "#{$i}: {$class}{$type}{$function} called at [{$file}:{$line}]\n";
        }
        
        $this->log($traceStr, false); // Вже включає час, тому не додаємо часову мітку
    }

    /**
     * Logs a message.
     *
     * @param string $message Message to log
     * @param bool $includeTimestamp Whether to include timestamp
     */
    public function log(string $message, bool $includeTimestamp = true): void
    {
        $logMessage = $includeTimestamp ? '[' . date('Y-m-d H:i:s') . '] ' . $message : $message;
        
        // Adding a message to the file
        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
        
        if ($this->consoleOutput) {
            // Завжди використовуємо error_log для запису в журнал, який Docker може перехопити
            error_log($logMessage);
            
            // Виводимо через echo тільки в CLI-режимі, щоб уникнути потрапляння в API-відповідь
            if (php_sapi_name() === 'cli') {
                echo $logMessage . PHP_EOL;
                
                // Примусове скидання буфера в CLI
                if (function_exists('ob_flush') && function_exists('flush')) {
                    @ob_flush();
                    @flush();
                }
            }
        }
    }

    /**
     * Getting the path to the log file
     * 
     * @return string The path to the log file
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
    
    /**
     * Clearing the log file
     */
    public function clearLog(): void
    {
        file_put_contents($this->logFile, '');
    }
    

} 