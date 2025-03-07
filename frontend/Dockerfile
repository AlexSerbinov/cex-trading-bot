FROM nginx:alpine

WORKDIR /usr/share/nginx/html

# Копіювання файлів фронтенду
COPY frontend/ .

# Експозиція порту
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"] 