<?php

declare(strict_types=1);

/**
 * Class for working with API
 */
class ApiClient
{
    /**
     * Execution of GET-request
     *
     * @param string $url URL for the request
     * @return string Response from the server
     */
    public function get(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TradingBot/1.0');
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($response === false) {
            throw new RuntimeException("Помилка CURL: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new RuntimeException("Помилка HTTP {$httpCode}: {$response}");
        }
        
        return $response;
    }
    
    /**
     * Execution of POST-request
     *
     * @param string $url URL for the request
     * @param string $data Data to send
     * @return string Response from the server
     */
    public function post(string $url, string $data): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        
        // Set the timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            throw new RuntimeException('Connection error to the trade server: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new RuntimeException('The trade server returned an error: HTTP ' . $httpCode);
        }
        
        return $response;
    }
} 