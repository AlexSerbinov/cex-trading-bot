#!/bin/bash

# Скрипт для моніторингу логів у Docker-контейнерах

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Функція для відображення допомоги
show_help() {
    echo "Використання: $0 [середовище] [сервіс]"
    echo ""
    echo "Середовище:"
    echo "  dev   - Development (за замовчуванням)"
    echo "  demo  - Demonstration"
    echo ""
    echo "Сервіс:"
    echo "  backend   - Backend сервіс"
    echo "  frontend  - Frontend сервіс"
    echo "  all       - Усі сервіси (за замовчуванням)"
    echo ""
    echo "Приклади:"
    echo "  $0 dev backend    # Моніторинг логів Backend у Dev середовищі"
    echo "  $0 demo           # Моніторинг логів усіх сервісів у Demo середовищі"
    echo "  $0                # Моніторинг логів усіх сервісів у Dev середовищі (за замовчуванням)"
}

# Парсимо аргументи
ENVIRONMENT="dev"
SERVICE="all"

if [ $# -gt 0 ]; then
    case "$1" in
        dev|demo)
            ENVIRONMENT="$1"
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            if [ $# -eq 1 ]; then
                # Якщо єдиний аргумент - це сервіс
                SERVICE="$1"
            else
                echo "Помилка: Невідоме середовище '$1'"
                show_help
                exit 1
            fi
            ;;
    esac
fi

if [ $# -gt 0 ]; then
    case "$1" in
        backend|frontend|all)
            SERVICE="$1"
            ;;
        *)
            echo "Помилка: Невідомий сервіс '$1'"
            show_help
            exit 1
            ;;
    esac
fi

# Отримуємо імена контейнерів
BACKEND_CONTAINER="trading-bot-backend-${ENVIRONMENT}"
FRONTEND_CONTAINER="trading-bot-frontend-${ENVIRONMENT}"

# Моніторинг логів в залежності від вибраного сервісу
if [ "$SERVICE" == "backend" ] || [ "$SERVICE" == "all" ]; then
    echo "Моніторинг логів Backend у ${ENVIRONMENT} середовищі (${BACKEND_CONTAINER})..."
    if [ "$SERVICE" == "all" ]; then
        # Запускаємо в фоновому режимі, якщо моніторимо всі сервіси
        docker logs -f "$BACKEND_CONTAINER" &
        BACKEND_LOGS_PID=$!
    else
        # Запускаємо в основному режимі, якщо тільки backend
        docker logs -f "$BACKEND_CONTAINER"
    fi
fi

if [ "$SERVICE" == "frontend" ] || [ "$SERVICE" == "all" ]; then
    echo "Моніторинг логів Frontend у ${ENVIRONMENT} середовищі (${FRONTEND_CONTAINER})..."
    docker logs -f "$FRONTEND_CONTAINER"
fi

# Якщо моніторимо всі сервіси, потрібно почекати, поки закінчиться фоновий процес
if [ "$SERVICE" == "all" ]; then
    # Чекаємо на сигнал завершення від користувача
    trap "kill $BACKEND_LOGS_PID" SIGINT SIGTERM
    wait $BACKEND_LOGS_PID
fi 