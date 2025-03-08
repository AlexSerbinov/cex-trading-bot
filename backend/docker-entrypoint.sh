#!/bin/bash
set -e

# Створюємо необхідні директорії, якщо вони не існують
mkdir -p /app/data/logs
mkdir -p /app/data/pids
mkdir -p /app/data/storage

# Визначаємо, яке середовище використовується
if [ "$ENVIRONMENT" = "dev" ]; then
    echo "Використовується Dev середовище"
    # Копіюємо конфігураційний файл для Dev середовища
    cp /app/config/dev/bots_config.json /app/data/bots_config.json
elif [ "$ENVIRONMENT" = "demo" ]; then
    echo "Використовується Demo середовище"
    # Копіюємо конфігураційний файл для Demo середовища
    cp /app/config/demo/bots_config.json /app/data/bots_config.json
else
    echo "Використовується локальне середовище"
    # Якщо файл конфігурації не існує, створюємо його за замовчуванням
    if [ ! -f /app/data/bots_config.json ]; then
        echo "Створення файлу конфігурації за замовчуванням"
        cp /app/config/dev/bots_config.json /app/data/bots_config.json
    fi
fi

# Встановлюємо правильні права доступу
chmod -R 777 /app/data

echo "Конфігурація завершена. Запуск додатку..."

# Виконуємо команду, передану як аргументи
exec "$@" 