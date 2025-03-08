#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Створення необхідних директорій для даних, якщо вони не існують
mkdir -p "$PROJECT_ROOT/data/logs"
mkdir -p "$PROJECT_ROOT/data/pids"
mkdir -p "$PROJECT_ROOT/data/storage"

# Надання прав на запис для директорій даних
chmod -R 777 "$PROJECT_ROOT/data"

echo "Запуск Demo-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml up -d

echo "Demo-середовище запущено!"
echo "Backend доступний на: http://localhost:6501"
echo "Swagger UI доступний на: http://localhost:6501/swagger-ui"
echo "Frontend доступний на: http://localhost:6502" 