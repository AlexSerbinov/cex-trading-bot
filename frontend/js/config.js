// CEX Trading Bot - Frontend Configuration
window.CONFIG = { 
    apiUrl: 'http://localhost:8080/api',
    swaggerUrl: 'http://localhost:8080/swagger-ui',
    environment: 'local'
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
});
