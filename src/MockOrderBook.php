<?php

declare(strict_types=1);

/**
 * Клас для генерації тестових даних книги ордерів
 */
class MockOrderBook
{
    /**
     * Повертає тестову книгу ордерів для вказаної пари
     *
     * @param string $pair Торгова пара (наприклад, 'LTC_USDT', 'ETH_USDT')
     * @return array Книга ордерів з бідами та асками
     */
    public function getMockOrderBook(string $pair): array
    {
        // Базова ціна залежить від пари
        $basePrice = $this->getBasePriceForPair($pair);

        // Генеруємо біди (нижче базової ціни)
        $bids = [];
        for ($i = 0; $i < 20; $i++) {
            $priceOffset = $i * 0.1 + mt_rand(0, 10) / 100; // Зменшуємо ціну з кожним кроком
            $price = number_format($basePrice * (1 - $priceOffset / 100), 6, '.', '');
            $amount = number_format(0.1 + mt_rand(1, 50) / 10, 8, '.', '');
            $bids[] = [$price, $amount, time()];
        }

        // Генеруємо аски (вище базової ціни)
        $asks = [];
        for ($i = 0; $i < 20; $i++) {
            $priceOffset = $i * 0.1 + mt_rand(0, 10) / 100; // Збільшуємо ціну з кожним кроком
            $price = number_format($basePrice * (1 + $priceOffset / 100), 6, '.', '');
            $amount = number_format(0.1 + mt_rand(1, 50) / 10, 8, '.', '');
            $asks[] = [$price, $amount, time()];
        }

        // Сортуємо біди за спаданням ціни
        usort($bids, function ($a, $b) {
            return (float) $b[0] <=> (float) $a[0];
        });

        // Сортуємо аски за зростанням ціни
        usort($asks, function ($a, $b) {
            return (float) $a[0] <=> (float) $b[0];
        });

        return [
            'bids' => $bids,
            'asks' => $asks,
        ];
    }

    /**
     * Повертає базову ціну для вказаної пари
     *
     * @param string $pair Торгова пара
     * @return float Базова ціна
     */
    private function getBasePriceForPair(string $pair): float
    {
        switch ($pair) {
            case 'LTC_USDT':
                return 65.0 + mt_rand(-100, 100) / 100; // ~65 USDT
            case 'ETH_USDT':
                return 3000.0 + mt_rand(-1000, 1000) / 100; // ~3000 USDT
            case 'BTC_USDT':
                return 50000.0 + mt_rand(-1000, 1000) / 10; // ~50000 USDT
            default:
                return 100.0 + mt_rand(-10, 10); // Значення за замовчуванням
        }
    }
}
