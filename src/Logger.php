<?php

declare(strict_types=1);

/**
 * Class for logging.
 */
class Logger
{
    /**
     * Logs a message.
     *
     * @param string $message Message to log
     */
    public function log(string $message): void
    {
        echo '[' . date('Y-m-d H:i:s:ms') . '] ' . $message . PHP_EOL;
        // For real logging, you can use file_put_contents or Monolog
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
}
