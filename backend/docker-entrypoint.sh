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

# Виконання подальших команд
exec "$@" 