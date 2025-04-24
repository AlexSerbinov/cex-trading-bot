FROM nginx:alpine

WORKDIR /usr/share/nginx/html

# Installation of necessary tools
RUN apk add --no-cache bash gettext

# Copying frontend files
COPY frontend/ .

# Copying initialization script
COPY frontend/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Setting default environment variables
# These values will be overridden in docker-compose files
ENV API_BASE_URL=http://localhost:8080/api
ENV SWAGGER_URL=http://localhost:8080/swagger-ui
ENV ENVIRONMENT=local

# Exposing the port
EXPOSE 80

# Setting the initialization script as ENTRYPOINT
ENTRYPOINT ["/docker-entrypoint.sh"]

# Starting Nginx
CMD ["nginx", "-g", "daemon off;"] 