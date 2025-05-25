#!/bin/bash

# Get parameters
ENVIRONMENT=$1
HOST=$2
PORT=$3

# Determine the project root directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

echo "Configuring frontend for environment: $ENVIRONMENT (host: $HOST, port: $PORT)"

# Define URL for API and Swagger depending on the environment
if [ "$ENVIRONMENT" = "local" ]; then
    # For local environment, use the full address
    API_URL="http://$HOST:$PORT/api"
    SWAGGER_URL="http://$HOST:$PORT/swagger-ui"
else
    # For other environments (dev, demo), use relative paths
    API_URL="/api"
    SWAGGER_URL="/swagger-ui"
fi

# Create frontend configuration
CONFIG_JS="window.CONFIG = { 
    apiUrl: '$API_URL',
    swaggerUrl: '$SWAGGER_URL',
    environment: '$ENVIRONMENT'
};

// Function for dynamic update of API URL from headers
window.addEventListener('DOMContentLoaded', function() {
    // Check if X-API-URL and X-SWAGGER-URL headers are present
    fetch('/')
        .then(response => {
            const apiUrl = response.headers.get('X-API-URL');
            const swaggerUrl = response.headers.get('X-SWAGGER-URL');
            
            if (apiUrl) {
                window.CONFIG.apiUrl = apiUrl;
                console.log('API URL updated from header:', apiUrl);
            }
            
            if (swaggerUrl) {
                window.CONFIG.swaggerUrl = swaggerUrl;
                console.log('Swagger URL updated from header:', swaggerUrl);
            }
        })
        .catch(error => {
            console.error('Error getting headers:', error);
        });
});"

# Write configuration to file
echo "${CONFIG_JS}" > "$PROJECT_ROOT/frontend/js/config.js"

echo "Frontend configured:"
echo "API URL: ${API_URL}"
echo "Swagger URL: ${SWAGGER_URL}"
echo "Environment: ${ENVIRONMENT}" 