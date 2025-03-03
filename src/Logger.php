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
     * Конструктор
     * 
     * @param bool $consoleOutput Виводити логи в консоль
     * @param string|null $logFile Шлях до файлу логів (якщо null, використовується стандартний шлях)
     */
    private function __construct(bool $consoleOutput = true, ?string $logFile = null)
    {
        $this->consoleOutput = $consoleOutput;
        $this->logFile = $logFile ?? __DIR__ . '/data/bot.log';
        
        // Створюємо директорію для логів, якщо вона не існує
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Отримання екземпляру класу (Singleton)
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
     */
    public function log(string $message): void
    {
        $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        
        // Виводимо в консоль, якщо потрібно
        if ($this->consoleOutput) {
            echo $logMessage;
        }
        
        // Записуємо в файл
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
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
     * Отримати шлях до файлу логів
     * 
     * @return string Шлях до файлу логів
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
    
    /**
     * Очистити файл логів
     */
    public function clearLog(): void
    {
        file_put_contents($this->logFile, '');
    }
    
    /**
     * Отримати вміст файлу логів
     * 
     * @param int $lines Кількість останніх рядків для отримання (0 - всі)
     * @return string Вміст файлу логів
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
        
        // Отримуємо останні N рядків
        $logLines = explode(PHP_EOL, $content);
        $logLines = array_filter($logLines); // Видаляємо порожні рядки
        $logLines = array_slice($logLines, -$lines);
        
        return implode(PHP_EOL, $logLines);
    }
}
