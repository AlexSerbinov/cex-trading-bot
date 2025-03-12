#!/bin/bash
set -e

# Створення необхідних директорій
mkdir -p /app/data/logs
mkdir -p /app/data/pids
mkdir -p /app/data/storage

# Встановлення прав на запис
chmod -R 777 /app/data

# Виведення інформації про середовище
echo "Бекенд запущено в середовищі: ${ENVIRONMENT}"
echo "Trade Server URL: ${TRADE_SERVER_URL}"

# Запуск менеджера ботів у фоні, якщо це не режим API
if [ "$1" == "bot-manager" ] || [ "$1" == "both" ]; then
    echo "Запуск менеджера ботів..."
    php /app/src/core/TradingBotManager.php &
    echo "Менеджер ботів запущено з PID: $!"
fi

# Якщо потрібно запустити і сервер, і менеджера ботів
if [ "$1" == "both" ]; then
    # Запускаємо PHP сервер
    echo "Запуск API сервера..."
    exec php -S 0.0.0.0:8080 router.php
elif [ "$1" == "bot-manager" ]; then
    # Створюємо метод для моніторингу логів
    echo "Запуск моніторингу логів..."
    exec tail -f /app/data/logs/bot.log
else
    # За замовчуванням - виконання команди, переданої в CMD
    echo "Запуск з командою: $@"
    exec "$@"
fi 