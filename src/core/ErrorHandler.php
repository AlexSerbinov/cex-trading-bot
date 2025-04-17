<?php

declare(strict_types=1);

class ErrorHandler
{
    private static ?Logger $logger = null;
    private static bool $initialized = false;
    
    /**
     * Шлях до файлу логів за замовчуванням
     */
    public const DEFAULT_ERROR_LOG_PATH = '/../data/logs/%s/bots_error.log';

    /**
     * Перевіряє, чи обробник помилок вже ініціалізовано
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Ініціалізує обробник помилок
     * 
     * @param string|null $errorLogFile Шлях до файлу логів помилок
     */
    public static function initialize(?string $errorLogFile = null): void
    {
        // Якщо errorLogFile не вказано, генеруємо шлях за замовчуванням
        if ($errorLogFile === null) {
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $errorLogFile = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
        }
        
        // Переконуємося, що директорія для логів існує
        $logDir = dirname($errorLogFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logger = Logger::getInstance(true, $errorLogFile);
        self::$initialized = true;

        // Встановлюємо обробник помилок
        set_error_handler([self::class, 'handleError']);
        
        // Встановлюємо обробник виключень
        set_exception_handler([self::class, 'handleException']);
        
        // Встановлюємо обробник для фатальних помилок
        register_shutdown_function([self::class, 'handleFatalError']);
        
        // Включаємо відображення помилок
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
        
        // Зафіксуємо початок роботи обробника помилок
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

        // Логуємо помилку
        if (self::$logger) {
            self::$logger->error($message);
        } else {
            // Якщо логгер не ініціалізовано, використовуємо error_log для запису на диск
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $backupErrorLog = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
            
            // Переконуємося, що директорія для логів існує
            $logDir = dirname($backupErrorLog);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Записуємо помилку в файл
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[{$timestamp}] {$message}";
            error_log($formattedMessage, 3, $backupErrorLog);
        }

        // Виводимо в консоль, якщо це CLI
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

        // Логуємо виключення
        if (self::$logger) {
            self::$logger->critical($message);
        } else {
            // Якщо логгер не ініціалізовано, використовуємо error_log для запису на диск
            $environment = getenv('ENVIRONMENT') ?: 'local';
            $backupErrorLog = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
            
            // Переконуємося, що директорія для логів існує
            $logDir = dirname($backupErrorLog);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Записуємо помилку в файл
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[{$timestamp}] [CRITICAL] {$message}";
            error_log($formattedMessage, 3, $backupErrorLog);
        }

        // Виводимо в консоль, якщо це CLI
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

            // Логуємо фатальну помилку
            if (self::$logger) {
                self::$logger->critical($message);
            } else {
                // Якщо логгер не ініціалізовано, використовуємо error_log для запису на диск
                $environment = getenv('ENVIRONMENT') ?: 'local';
                $backupErrorLog = __DIR__ . sprintf(self::DEFAULT_ERROR_LOG_PATH, $environment);
                
                // Переконуємося, що директорія для логів існує
                $logDir = dirname($backupErrorLog);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                // Записуємо помилку в файл
                $timestamp = date('Y-m-d H:i:s');
                $formattedMessage = "[{$timestamp}] [CRITICAL] {$message}";
                error_log($formattedMessage, 3, $backupErrorLog);
            }

            // Виводимо в консоль, якщо це CLI
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, $message . "\n");
            }
        }
    }
} 