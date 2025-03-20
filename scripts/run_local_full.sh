#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Зупиняємо всі існуючі процеси
echo "Зупинка всіх існуючих процесів..."
"$SCRIPT_DIR/stop_all.sh"

# Очищаємо всі PID-файли
echo "Видалення всіх PID-файлів..."
rm -f "$PROJECT_ROOT/data/pids"/*.pid

# Очищаємо системний кеш
echo "Очищення системного кешу..."
php "$PROJECT_ROOT/tools/clean_system.php"

# Налаштовуємо конфігурацію фронтенду
"$SCRIPT_DIR/configure_frontend.sh" "local" "localhost" "8080"

# Запускаємо бекенд
echo "Запуск бекенду на порту 8080..."
cd "$PROJECT_ROOT" && php -S localhost:8080 router.php > /dev/null 2>&1 &
BACKEND_PID=$!
echo "Бекенд запущено з PID: $BACKEND_PID"

# Запускаємо фронтенд
echo "Запуск фронтенду на порту 8081..."
cd "$PROJECT_ROOT" && php -S localhost:8081 -t frontend > /dev/null 2>&1 &
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
        cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php > /dev/null 2>&1 &
        BOTS_PID=$!
        echo "Боти запущені з новим PID: $BOTS_PID"
    fi
else
    cd "$PROJECT_ROOT" && php src/core/TradingBotManager.php > /dev/null 2>&1 &
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
tail -f "$PROJECT_ROOT/data/logs/bot.log" 