#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Створення директорій для конфігураційних файлів різних середовищ
mkdir -p "$PROJECT_ROOT/config/dev"
mkdir -p "$PROJECT_ROOT/config/demo"

# Перевірка наявності конфігураційного файлу ботів
if [ -f "$PROJECT_ROOT/config/bots_config.json" ]; then
    echo "Копіювання конфігураційного файлу ботів для DEV середовища..."
    cp "$PROJECT_ROOT/config/bots_config.json" "$PROJECT_ROOT/config/dev/"
    
    echo "Копіювання конфігураційного файлу ботів для DEMO середовища..."
    cp "$PROJECT_ROOT/config/bots_config.json" "$PROJECT_ROOT/config/demo/"
else
    echo "ПОМИЛКА: Файл $PROJECT_ROOT/config/bots_config.json не знайдено!"
    exit 1
fi

# Копіювання інших конфігураційних файлів (якщо вони є)
for config_file in "$PROJECT_ROOT/config"/*.php "$PROJECT_ROOT/config"/*.ini "$PROJECT_ROOT/config"/*.yaml "$PROJECT_ROOT/config"/*.yml; do
    if [ -f "$config_file" ]; then
        filename=$(basename "$config_file")
        echo "Копіювання $filename для DEV середовища..."
        cp "$config_file" "$PROJECT_ROOT/config/dev/"
        
        echo "Копіювання $filename для DEMO середовища..."
        cp "$config_file" "$PROJECT_ROOT/config/demo/"
    fi
done

echo "Конфігураційні файли підготовлено для обох середовищ!" 