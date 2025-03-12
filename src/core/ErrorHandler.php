<?php

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';

/**
 * Клас для централізованої обробки помилок
 */
class ErrorHandler
{
    private static ?ErrorHandler $instance = null;
    private Logger $logger;
    private string $environment;
    
    /**
     * Конструктор
     */
    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->environment = getenv('ENVIRONMENT') ?: 'dev';
        
        // Налаштування рівня звітування про помилки
        if ($this->environment === 'dev') {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
        }
    }
    
    /**
     * Отримання екземпляра класу (Singleton)
     */
    public static function getInstance(): ErrorHandler
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Встановлення обробників помилок
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    /**
     * Обробка стандартних помилок PHP
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Ігнорувати помилки, які виключені з error_reporting
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorType = match($errno) {
            E_ERROR, E_USER_ERROR => 'ERROR',
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => "UNKNOWN ($errno)"
        };
        
        $this->logger->error("[{$errorType}] {$errstr} in {$errfile}:{$errline}");
        
        // Для критичних помилок завершуємо виконання
        if ($errno == E_ERROR || $errno == E_USER_ERROR) {
            $this->terminateWithError("Internal Server Error: {$errstr}");
        }
        
        return true; // Дозволяємо PHP також обробити помилку
    }
    
    /**
     * Обробка винятків
     */
    public function handleException(\Throwable $exception): void
    {
        $this->logger->error(
            "EXCEPTION: " . $exception->getMessage() . 
            " in " . $exception->getFile() . ":" . $exception->getLine() . 
            "\nStack trace: " . $exception->getTraceAsString()
        );
        
        $this->terminateWithError("Exception: " . $exception->getMessage());
    }
    
    /**
     * Обробка фатальних помилок
     */
    public function handleFatalError(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->error(
                "[FATAL ERROR] {$error['message']} in {$error['file']}:{$error['line']}"
            );
            
            $this->terminateWithError("Fatal Error: {$error['message']}");
        }
    }
    
    /**
     * Завершення виконання з виведенням помилки
     */
    private function terminateWithError(string $message): void
    {
        http_response_code(500);
        
        // Перевіряємо, чи це API-запит
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Server Error', 'message' => $message]);
        } else {
            // Для веб-сторінок виводимо HTML
            if ($this->environment === 'dev') {
                // В режимі розробки показуємо детальну інформацію
                echo '<html><head><title>Error</title>';
                echo '<style>body{font-family:sans-serif;margin:20px;} .error{background:#ffebee;border:1px solid #f44336;padding:15px;border-radius:4px;}</style>';
                echo '</head><body>';
                echo '<h1>Error</h1>';
                echo '<div class="error">' . htmlspecialchars($message) . '</div>';
                echo '</body></html>';
            } else {
                // В продакшн режимі показуємо загальне повідомлення
                echo '<html><head><title>Error</title></head><body>';
                echo '<h1>Server Error</h1>';
                echo '<p>An unexpected error occurred. Please try again later.</p>';
                echo '</body></html>';
            }
        }
        
        exit(1);
    }
} 