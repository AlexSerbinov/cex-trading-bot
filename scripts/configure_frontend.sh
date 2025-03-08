#!/bin/bash

# Визначаємо кореневу директорію проекту
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Отримуємо параметри з командного рядка або використовуємо значення за замовчуванням
ENVIRONMENT=${1:-local}
API_HOST=${2:-localhost}
API_PORT=${3:-8080}
SWAGGER_PORT=${4:-$API_PORT}

# Визначаємо API URL на основі середовища
if [ "$ENVIRONMENT" = "dev" ]; then
    # Для Dev середовища
    API_URL="http://${API_HOST}:5501/api"
    SWAGGER_URL="http://${API_HOST}:5501/swagger-ui"
elif [ "$ENVIRONMENT" = "demo" ]; then
    # Для Demo середовища
    API_URL="http://${API_HOST}:6501/api"
    SWAGGER_URL="http://${API_HOST}:6501/swagger-ui"
else
    # Для локального середовища
    API_URL="http://${API_HOST}:${API_PORT}/api"
    SWAGGER_URL="http://${API_HOST}:${SWAGGER_PORT}/swagger-ui"
fi

# Створюємо файл конфігурації для фронтенду
echo "Створення конфігурації для фронтенду (${ENVIRONMENT})..."
cat > "$PROJECT_ROOT/frontend/js/config.js" << EOL
// Конфігурація для ${ENVIRONMENT} середовища
window.CONFIG = { 
    apiUrl: '${API_URL}',
    swaggerUrl: '${SWAGGER_URL}',
    environment: '${ENVIRONMENT}'
};
EOL

echo "Фронтенд налаштовано для середовища: ${ENVIRONMENT}"
echo "API URL: ${API_URL}"
echo "Swagger URL: ${SWAGGER_URL}" 