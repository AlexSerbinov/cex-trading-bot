FROM nginx:alpine

WORKDIR /usr/share/nginx/html

# Встановлення необхідних інструментів
RUN apk add --no-cache bash gettext

# Копіювання файлів фронтенду
COPY frontend/ .

# Копіювання скрипту ініціалізації
COPY frontend/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Встановлення змінних середовища за замовчуванням
# Ці значення будуть перевизначені в docker-compose файлах
ENV API_BASE_URL=http://localhost:8080/api
ENV SWAGGER_URL=http://localhost:8080/swagger-ui
ENV ENVIRONMENT=local

# Експозиція порту
EXPOSE 80

# Встановлення скрипту ініціалізації як ENTRYPOINT
ENTRYPOINT ["/docker-entrypoint.sh"]

# Запуск Nginx
CMD ["nginx", "-g", "daemon off;"] 