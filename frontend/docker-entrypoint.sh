#!/bin/bash
set -e

# Визначаємо API URL на основі середовища
if [ "$ENVIRONMENT" = "dev" ]; then
    # Для Dev середовища використовуємо відносний шлях
    DEFAULT_API_URL="/api"
    DEFAULT_SWAGGER_URL="/swagger-ui"
elif [ "$ENVIRONMENT" = "demo" ]; then
    # Для Demo середовища використовуємо відносний шлях
    DEFAULT_API_URL="/api"
    DEFAULT_SWAGGER_URL="/swagger-ui"
else
    # Для локального середовища використовуємо значення за замовчуванням
    DEFAULT_API_URL="${API_BASE_URL:-http://localhost:8080/api}"
    DEFAULT_SWAGGER_URL="${SWAGGER_URL:-http://localhost:8080/swagger-ui}"
fi

# Використовуємо значення з середовища або значення за замовчуванням
API_URL="${API_BASE_URL:-$DEFAULT_API_URL}"
SWAGGER_URL="${SWAGGER_URL:-$DEFAULT_SWAGGER_URL}"

# Створення конфігурації для фронтенду
CONFIG_JS="window.CONFIG = { 
    apiUrl: '$API_URL',
    swaggerUrl: '$SWAGGER_URL',
    environment: '${ENVIRONMENT}'
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
echo "${CONFIG_JS}" > /usr/share/nginx/html/js/config.js

echo "Фронтенд налаштовано для середовища: ${ENVIRONMENT}"
echo "API URL: ${API_URL}"
echo "Swagger URL: ${SWAGGER_URL}"

# Виконання подальших команд
exec "$@" 