#!/bin/bash
set -e

# Determine API URL based on environment
# For Dev environment use a relative path
# For Demo environment use a relative path
# For local environment use default values
# Use values from environment or default values
# Creating configuration for frontend
// Function for dynamic update of API URL from headers
// Check for X-API-URL and X-SWAGGER-URL headers
// API URL updated from header:
// Swagger URL updated from header:
// Error getting headers:
# Writing configuration to file
echo "Frontend configured for environment: ${ENVIRONMENT}"
echo "API URL: ${API_URL}"
echo "Swagger URL: ${SWAGGER_URL}"

# Execute further commands
exec "$@" 