<?php

declare(strict_types=1);

/**
 * Class for handling API requests via CURL.
 */
class ApiClient
{
    /**
     * Performs a GET request.
     *
     * @param string $url URL for the request
     * @return string Server response
     * @throws RuntimeException If the request fails
     */
    public function get(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('CURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }

    /**
     * Performs a POST request.
     *
     * @param string $url URL for the request
     * @param string $data Data to send
     * @return string Server response
     * @throws RuntimeException If the request fails
     */
    public function post(string $url, string $data): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('CURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }
}
