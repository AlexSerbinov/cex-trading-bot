<?php

declare(strict_types=1);

/**
 * Class for logging.
 */
class Logger
{
    private string $logFile;
    private bool $consoleOutput;
    private static ?Logger $instance = null;

    /**
     * Constructor
     * 
     * @param bool $consoleOutput Output logs to the console
     * @param string|null $logFile Path to the log file (if null, the default path is used)
     */
    private function __construct(bool $consoleOutput = true, ?string $logFile = null)
    {
        $this->consoleOutput = $consoleOutput;
        $this->logFile = $logFile ?? __DIR__ . '/../../data/logs/bot.log';
        
        // Creating the log directory if it does not exist
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Getting an instance of the class (Singleton)
     */
    public static function getInstance(bool $consoleOutput = true, ?string $logFile = null): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self($consoleOutput, $logFile);
        }
        return self::$instance;
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
        
        // Output to the console only if the option is enabled and NOT in the context of an API request
        if ($this->consoleOutput && php_sapi_name() === 'cli') {
            echo $logMessage . PHP_EOL;
        }
    }

    /**
     * Logs an error.
     *
     * @param string $message Error message
     */
    public function error(string $message): void
    {
        $this->log('ERROR: ' . $message);
    }
    
    /**
     * Logs a debug message.
     *
     * @param string $message Debug message to log
     */
    public function debug(string $message): void
    {
        $this->log('DEBUG: ' . $message);
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
    
    /**
     * Getting the content of the log file
     * 
     * @param int $lines The number of the last lines to get (0 - all)
     * @return string The content of the log file
     */
    public function getLogContent(int $lines = 0): string
    {
        if (!file_exists($this->logFile)) {
            return '';
        }
        
        $content = file_get_contents($this->logFile);
        
        if ($lines <= 0) {
            return $content;
        }
        
        // Getting the last N lines
        $logLines = explode(PHP_EOL, $content);
        $logLines = array_filter($logLines); // Removing empty lines
        $logLines = array_slice($logLines, -$lines);
        
        return implode(PHP_EOL, $logLines);
    }
} 