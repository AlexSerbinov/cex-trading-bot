#!/bin/bash

# Отримання параметрів
ENVIRONMENT=$1
HOST=$2
PORT=$3

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

echo "Налаштування фронтенду для середовища: $ENVIRONMENT (хост: $HOST, порт: $PORT)"

# Визначення URL для API та Swagger в залежності від середовища
if [ "$ENVIRONMENT" = "local" ]; then
    # Для локального середовища використовуємо повну адресу
    API_URL="http://$HOST:$PORT/api"
    SWAGGER_URL="http://$HOST:$PORT/swagger-ui"
else
    # Для інших середовищ (dev, demo) використовуємо відносні шляхи
    API_URL="/api"
    SWAGGER_URL="/swagger-ui"
fi

# Створення конфігурації для фронтенду
CONFIG_JS="window.CONFIG = { 
    apiUrl: '$API_URL',
    swaggerUrl: '$SWAGGER_URL',
    environment: '$ENVIRONMENT'
};

// Функція для динамічного оновлення URL API з заголовків
window.addEventListener('DOMContentLoaded', function() {
    // Перевіряємо, чи є заголовки X-API-URL і X-SWAGGER-URL
    fetch('/')
        .then(response => {
            const apiUrl = response.headers.get('X-API-URL');
            const swaggerUrl = response.headers.get('X-SWAGGER-URL');
            
            if (apiUrl) {
                window.CONFIG.apiUrl = apiUrl;
                console.log('API URL оновлено з заголовка:', apiUrl);
            }
            
            if (swaggerUrl) {
                window.CONFIG.swaggerUrl = swaggerUrl;
                console.log('Swagger URL оновлено з заголовка:', swaggerUrl);
            }
        })
        .catch(error => {
            console.error('Помилка при отриманні заголовків:', error);
        });
});"

# Запис конфігурації у файл
echo "${CONFIG_JS}" > "$PROJECT_ROOT/frontend/js/config.js"

echo "Фронтенд налаштовано:"
echo "API URL: ${API_URL}"
echo "Swagger URL: ${SWAGGER_URL}"
echo "Environment: ${ENVIRONMENT}" 