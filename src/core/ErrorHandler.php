<?php

declare(strict_types=1);

class ErrorHandler
{
    private static ?Logger $logger = null;

    public static function initialize(): void
    {
        self::$logger = Logger::getInstance();

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
            error_log($message);
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
            error_log($message);
        }

        // Виводимо в консоль, якщо це CLI
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $message . "\n");
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
                error_log($message);
            }

            // Виводимо в консоль, якщо це CLI
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, $message . "\n");
            }
        }
    }
} 