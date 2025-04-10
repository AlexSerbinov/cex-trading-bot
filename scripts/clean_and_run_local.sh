#!/bin/bash

echo "Stopping all processes..."

# Display the list of processes before stopping for information
echo "Active processes before stopping:"
ps aux | grep -E "BotRunner|TradingBotManager|php -S" | grep -v grep

# Stop all BotRunner.php processes
echo "Stopping BotRunner.php processes..."
pkill -f "BotRunner.php"

# Stop all TradingBotManager.php processes
echo "Stopping TradingBotManager.php processes..."
pkill -f "TradingBotManager.php"

# Stop the HTTP server
echo "Stopping the HTTP server..."
pkill -f "php -S localhost:8080"

# Clean up PID files
echo "Cleaning up PID files..."
# Determine the current script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
# Determine the project root directory
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Check if any processes remain
echo "Remaining processes after stopping:"
REMAINING=$(ps aux | grep -E "BotRunner|TradingBotManager|php -S" | grep -v grep)
if [ -n "$REMAINING" ]; then
    echo "$REMAINING"
    echo "Forcing the termination of processes..."
    ps aux | grep -E "BotRunner|TradingBotManager|php -S" | grep -v grep | awk '{print $2}' | xargs -r kill -9
else
    echo "All processes stopped successfully."
fi

echo "Completed." 

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Встановлюємо змінну середовища
export ENVIRONMENT=local

# Створюємо директорію для логів, якщо вона не існує
mkdir -p "$PROJECT_ROOT/data/logs/local"

# Зупиняємо всі існуючі процеси
echo "Зупинка всіх існуючих процесів..."
"$SCRIPT_DIR/stop_all.sh"

# Очищаємо всі PID-файли
echo "Видалення всіх PID-файлів..."
rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Очищаємо системний кеш
echo "Очищення системного кешу..."
php "$PROJECT_ROOT/tools/clean_system.php"

# Налаштовуємо конфігурацію фронтенду - використовуємо створений нами скрипт
"$SCRIPT_DIR/configure_frontend.sh" "local" "localhost" "8080"

# Запускаємо бекенд
echo "Запуск бекенду на порту 8080..."
cd "$PROJECT_ROOT" && php -S localhost:8080 router.php 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/backend_error.log" &
BACKEND_PID=$!
echo "Бекенд запущено з PID: $BACKEND_PID"

# Запускаємо фронтенд
echo "Запуск фронтенду на порту 8081..."
cd "$PROJECT_ROOT" && php -S localhost:8081 -t frontend 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/frontend_error.log" &
FRONTEND_PID=$!
echo "Фронтенд запущено з PID: $FRONTEND_PID"

# Запускаємо ботів
echo "Запуск ботів..."
# Перевіряємо, чи не запущений уже TradingBotManager
BOT_MANAGER_LOCK="$PROJECT_ROOT/data/pids/trading_bot_manager.lock"

if [ -f "$BOT_MANAGER_LOCK" ]; then
    LOCK_PID=$(cat "$BOT_MANAGER_LOCK")
    echo "Знайдено лок-файл TradingBotManager з PID: $LOCK_PID"
    
    # Перевіряємо, чи процес із цим PID існує і чи це TradingBotManager
    if ps -p $LOCK_PID > /dev/null && grep -q TradingBotManager /proc/$LOCK_PID/cmdline 2>/dev/null; then
        echo "TradingBotManager уже запущений з PID: $LOCK_PID. Використовуємо існуючий процес."
        BOTS_PID=$LOCK_PID
    else
        echo "Лок-файл існує, але процес не запущений або це не TradingBotManager. Видаляємо старий лок-файл."
        rm -f "$BOT_MANAGER_LOCK"
        cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/bots_error.log" &
        BOTS_PID=$!
        echo "Боти запущені з новим PID: $BOTS_PID"
    fi
else
    cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php 2>&1 | tee -a "$PROJECT_ROOT/data/logs/local/bots_error.log" &
    BOTS_PID=$!
    echo "Боти запущені з PID: $BOTS_PID"
fi

# Зберігаємо PID-и в файли
echo $BACKEND_PID > "$PROJECT_ROOT/data/pids/backend.pid"
echo $FRONTEND_PID > "$PROJECT_ROOT/data/pids/frontend.pid"
echo $BOTS_PID > "$PROJECT_ROOT/data/pids/bots.pid"

echo ""
echo "Система запущена!"
echo "Бекенд доступний на: http://localhost:8080"
echo "Swagger UI доступний на: http://localhost:8080/swagger-ui"
echo "Фронтенд доступний на: http://localhost:8081"
echo ""
echo "Для зупинки всіх процесів використовуйте: ./scripts/stop_all.sh"
echo ""
echo "Показ логів..."
echo "Для перегляду помилок дивіться файли:"
echo "- $PROJECT_ROOT/data/logs/local/backend_error.log"
echo "- $PROJECT_ROOT/data/logs/local/frontend_error.log"
echo "- $PROJECT_ROOT/data/logs/local/bots_error.log"
echo ""
tail -f \
    "$PROJECT_ROOT/data/logs/local/bot.log" \
    "$PROJECT_ROOT/data/logs/local/backend_error.log" \
    "$PROJECT_ROOT/data/logs/local/frontend_error.log" \
    "$PROJECT_ROOT/data/logs/local/bots_error.log" 