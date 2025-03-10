#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

echo "Зупинка Dev-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-dev.yml down

echo "Зупинка Demo-середовища Trading Bot..."
cd "$PROJECT_ROOT" && docker-compose -f docker-compose-demo.yml down

echo "Всі контейнери зупинено!" 