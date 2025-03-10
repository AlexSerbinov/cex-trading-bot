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

# Підготовка конфігураційних файлів
echo "Підготовка конфігураційних файлів..."
"$SCRIPT_DIR/prepare-configs.sh"

echo "Зупинка всіх контейнерів..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml down
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml down

echo "Перебілдування Dev-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml build --no-cache

echo "Перебілдування Demo-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml build --no-cache

echo "Запуск Dev-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml up -d

echo "Запуск Demo-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml up -d

echo "Обидва середовища перебілдовано та запущено!"
echo ""
echo "Dev-середовище:"
echo "Backend доступний на: http://localhost:5501"
echo "Swagger UI доступний на: http://localhost:5501/swagger-ui"
echo "Frontend доступний на: http://localhost:5502"
echo ""
echo "Demo-середовище:"
echo "Backend доступний на: http://localhost:6501"
echo "Swagger UI доступний на: http://localhost:6501/swagger-ui"
echo "Frontend доступний на: http://localhost:6502"
echo ""
echo "Для зупинки всіх контейнерів використовуйте: ./scripts/stop-all.sh" 