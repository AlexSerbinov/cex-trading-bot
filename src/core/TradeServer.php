<?php

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../../config/config.php';
use stdClass;
use Exception;

/**
 * Class TradeServer
 * 
 * Клас для взаємодії з трейд-сервером
 */
class TradeServer {
    private $logger;
    private $tradeServerUrl;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $config;
        $this->logger = Logger::getInstance();
        
        // Використовуємо URL з конфігурації або значення за замовчуванням
        $this->tradeServerUrl = $config['trade_server_url'] ?? 'http://164.68.117.90:18080'; // 90 dev
    }
    
    /**
     * Отримати баланси користувача
     * 
     * @param int $userId ID користувача
     * @return array Баланси користувача
     */
    public function getUserBalances(int $userId): array {
        try {
            $response = $this->sendRequest('balance.query', [$userId]);
            
            if (isset($response['result'])) {
                return $response['result'];
            }
            
            $this->logger->log('Error getting balances: Invalid response format');
            return [];
        } catch (Exception $e) {
            $this->logger->log('Error getting balances: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Оновити баланс користувача
     * 
     * @param int $userId ID користувача
     * @param string $currency Валюта
     * @param string $type Тип операції (deposit/withdraw)
     * @param int $operationId Унікальний ID операції (повинен бути числом!)
     * @param string $amount Сума
     * @param array $extra Додаткові параметри
     * @return array Результат операції
     */
    public function updateBalance(int $userId, string $currency, string $type, int $operationId, string $amount, array $extra = []): array {
        try {
            // Переконуємося, що amount це рядок
            if (!is_string($amount)) {
                $amount = (string)$amount;
            }
            
            // Переконуємося, що extra серіалізується як пустий об'єкт {}, а не масив []
            if (empty($extra)) {
                $extraObj = new stdClass(); // Пустий об'єкт {} в JSON
            } else {
                $extraObj = (object)$extra; // Перетворюємо масив на об'єкт
            }
            
            $this->logger->log("Sending balance update request: userId={$userId}, currency={$currency}, type={$type}, operationId={$operationId}, amount={$amount}");
            
            // ВАЖЛИВО: operationId передається як число, без приведення до рядка
            $response = $this->sendRequest('balance.update', [$userId, $currency, $type, $operationId, $amount, $extraObj]);
            
            if (isset($response['result'])) {
                $this->logger->log("Balance updated for {$currency}: {$type} {$amount}");
                return $response['result'];
            }
            
            if (isset($response['error'])) {
                $this->logger->log("Error updating balance: " . json_encode($response['error']));
                throw new Exception(json_encode($response['error']));
            }
            
            $this->logger->log('Error updating balance: Invalid response format');
            throw new Exception('Invalid response format');
        } catch (Exception $e) {
            $this->logger->log('Error updating balance: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Отримати список доступних пар на трейд-сервері
     * 
     * @return array Список пар
     */
    public function getMarketPairs(): array {
        try {
            $response = $this->sendRequest('market.list', []);
            
            if (isset($response['result'])) {
                return array_keys($response['result']);
            }
            
            $this->logger->log('Error getting market pairs: Invalid response format');
            return [];
        } catch (Exception $e) {
            $this->logger->log('Error getting market pairs: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Відправити запит на трейд-сервер
     * 
     * @param string $method Метод API
     * @param array $params Параметри
     * @return array Відповідь
     */
    private function sendRequest(string $method, array $params): array {
        $data = [
            'method' => $method,
            'params' => $params,
            'id' => time()
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($this->tradeServerUrl, false, $context);
        
        if ($result === false) {
            throw new Exception('Failed to connect to trade server');
        }
        
        $response = json_decode($result, true);
        
        if ($response === null) {
            throw new Exception('Invalid JSON response from trade server');
        }
        
        return $response;
    }
} 