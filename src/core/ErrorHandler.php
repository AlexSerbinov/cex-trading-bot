<?php

declare(strict_types=1);

class ErrorHandler
{
    private static ?Logger $logger = null;
    private static bool $initialized = false;
    
    /**
     * Path to the default log file
     */
    public const DEFAULT_ERROR_LOG_PATH = '/../data/logs/%s/bots_error.log';

    /**
     * Checks if the error handler has already been initialized
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Initializes the error handler
     * 
     * @param string|null $errorLogFile Path to the error log file
     */
    public static function initialize(?string $errorLogFile = null): void
    {
        // If errorLogFile is not specified, generate the default path
        if ($errorLogFile === null) {
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $errorLogFile = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
        }
        
        // Make sure the log directory exists
        $logDir = dirname($errorLogFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logger = Logger::getInstance(true, $errorLogFile);
        self::$initialized = true;

        // Set error handler
        set_error_handler([self::class, 'handleError']);
        
        // Set exception handler
        set_exception_handler([self::class, 'handleException']);
        
        // Set handler for fatal errors
        register_shutdown_function([self::class, 'handleFatalError']);
        
        // Enable error display
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
        
        // Log the start of the error handler
        if (self::$logger) {
            self::$logger->log("ErrorHandler initialized. Errors will be logged to: {$errorLogFile}");
        }
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = match($errno) {
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED',
            default => 'UNKNOWN'
        };

        $message = sprintf(
            "%s: %s in %s on line %d\n",
            $errorType,
            $errstr,
            $errfile,
            $errline
        );

        // Log the error
        if (self::$logger) {
            self::$logger->error($message);
        } else {
            // If logger is not initialized, use error_log to write to disk
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $backupErrorLog = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
            
            // Make sure the log directory exists
            $logDir = dirname($backupErrorLog);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Write error to file
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[{$timestamp}] {$message}";
            error_log($formattedMessage, 3, $backupErrorLog);
        }

        // Output to console if it's CLI
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $message . "\n");
        }

        return true;
    }

    public static function handleException(\Throwable $exception): void
    {
        $message = sprintf(
            "Uncaught %s: %s in %s on line %d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // Log exception
        if (self::$logger) {
            self::$logger->critical($message);
        } else {
            // If logger is not initialized, use error_log to write to disk
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $backupErrorLog = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
            
            // Make sure the log directory exists
            $logDir = dirname($backupErrorLog);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Write error to file
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[{$timestamp}] [CRITICAL] {$message}";
            error_log($formattedMessage, 3, $backupErrorLog);
        }

        // Output to console if it's CLI
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $message . "\n");
            fwrite(STDERR, "--------------------------------\n");
        }

        exit(1);
    }

    public static function handleFatalError(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = sprintf(
                "FATAL ERROR: %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            );

            // Log fatal error
            if (self::$logger) {
                self::$logger->critical($message);
            } else {
                // If logger is not initialized, use error_log to write to disk
                $environment = getenv('ENVIRONMENT') ?: 'local';
                $backupErrorLog = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
                
                // Make sure the log directory exists
                $logDir = dirname($backupErrorLog);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                // Write error to file
                $timestamp = date('Y-m-d H:i:s');
                $formattedMessage = "[{$timestamp}] [CRITICAL] {$message}";
                error_log($formattedMessage, 3, $backupErrorLog);
            }

            // Output to console if it's CLI
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, $message . "\n");
            }
        }
    }
} 