#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Перевірка існування необхідних конфігураційних файлів
if [ ! -f "$PROJECT_ROOT/config/bots_config.json" ]; then
    echo "Створення порожнього bots_config.json..."
    echo '{"enabledPairs": ["BTC_USDT", "ETH_USDT"]}' > "$PROJECT_ROOT/config/bots_config.json"
fi

# Перевірка існування основного конфігураційного файлу
if [ ! -f "$PROJECT_ROOT/config/config.php" ]; then
    echo "Конфігураційний файл config.php відсутній!"
    echo "Створіть файл config.php з необхідними параметрами"
    exit 1
fi

echo "Конфігураційні файли підготовлено!" 