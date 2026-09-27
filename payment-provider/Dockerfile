FROM php:8.4-cli-alpine

WORKDIR /var/www/html

COPY public/ ./public/

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
