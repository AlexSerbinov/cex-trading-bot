<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/LogManager.php';

use App\helpers\LogManager;

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
    private LogManager $logManager;

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
        
        // Враховуємо оточення для визначення шляху до логів
        $environment = getenv('ENVIRONMENT') ?: 'local';
        
        // Якщо шлях до логу не вказано явно, використовуємо шлях за замовчуванням
        if ($logFile === null) {
            // Шлях до логу з урахуванням середовища
            $this->logFile = __DIR__ . '/../../data/logs/' . $environment . '/bot.log';
        } else {
            // Використовуємо вказаний шлях
            $this->logFile = $logFile;
        }
        
        $this->logLevel = $logLevel;
        $this->logManager = LogManager::getInstance();
        
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
        else if ($logFile !== null && self::$instance->logFile !== $logFile) {
            // Якщо вказано інший файл для логів, створюємо новий екземпляр
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
            
            // Додатково записуємо помилки в файл bots_error.log
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $errorLogFile = __DIR__ . '/../../data/logs/' . $environment . '/bots_error.log';
            
            // Якщо поточний файл логу не є файлом помилок, записуємо також у файл помилок
            if ($this->logFile !== $errorLogFile) {
                $errorMessage = '[' . date('Y-m-d H:i:s') . '] [ERROR] ' . $message;
                file_put_contents($errorLogFile, $errorMessage . PHP_EOL, FILE_APPEND);
            }
        }
    }
    
    /**
     * Logging a critical error message
     */
    public function critical(string $message): void
    {
        if ($this->logLevel <= self::CRITICAL) {
            $this->log('[CRITICAL] ' . $message);
            
            // Додатково записуємо критичні помилки в файл bots_error.log
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $errorLogFile = __DIR__ . '/../../data/logs/' . $environment . '/bots_error.log';
            
            // Якщо поточний файл логу не є файлом помилок, записуємо також у файл помилок
            if ($this->logFile !== $errorLogFile) {
                $errorMessage = '[' . date('Y-m-d H:i:s') . '] [CRITICAL] ' . $message;
                file_put_contents($errorLogFile, $errorMessage . PHP_EOL, FILE_APPEND);
            }
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
        try {
            // Get the base filename without path
            $baseLogFile = basename($this->logFile);
            
            // Check and rotate logs before writing
            $this->logManager->checkLogs();
            
            $logMessage = $includeTimestamp ? '[' . date('Y-m-d H:i:s') . '] ' . $message : $message;
            
            // Adding a message to the file
            file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
            
            // Always use echo in CLI mode to ensure consistent output to both console and file
            if (php_sapi_name() === 'cli') {
                echo $logMessage . PHP_EOL;
                
                // Примусове скидання буфера в CLI
                if (function_exists('ob_flush') && function_exists('flush')) {
                    @ob_flush();
                    @flush();
                }
            } 
            // In non-CLI mode, use error_log for Docker to capture
            else if ($this->consoleOutput) {
                error_log($logMessage);
            }
        } catch (\Throwable $e) {
            error_log("Error in Logger: " . $e->getMessage());
            error_log($e->getTraceAsString());
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