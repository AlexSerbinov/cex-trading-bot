#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Налаштовуємо конфігурацію фронтенду для Dev середовища
"$SCRIPT_DIR/configure_frontend.sh" "dev" "localhost"

# Запускаємо фронтенд
echo "Запуск фронтенду для Dev середовища на порту 8081..."
cd "$PROJECT_ROOT" && php -S localhost:8081 -t frontend > /dev/null 2>&1 &
FRONTEND_PID=$!
echo "Фронтенд запущено з PID: $FRONTEND_PID"

# Зберігаємо PID в файл
echo $FRONTEND_PID > "$PROJECT_ROOT/data/pids/frontend.pid"

echo ""
echo "Фронтенд для Dev середовища запущено!"
echo "Доступний на: http://localhost:8081"
echo "API URL налаштовано на: http://localhost:5501/api"
echo ""
echo "Для зупинки використовуйте: kill $FRONTEND_PID або ./scripts/stop_all.sh" 