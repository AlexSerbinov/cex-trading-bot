window.CONFIG = { 
    apiUrl: 'http://localhost:8080/api',
    swaggerUrl: 'http://localhost:8080/swagger-ui',
    environment: 'local'
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
});
