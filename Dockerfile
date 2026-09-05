# VOIDBILL — runs on PHP's built-in server behind Railway's proxy.
# No Apache/Nginx needed for a project this size; the same server used
# throughout local development runs here too.
FROM php:8.2-cli

RUN docker-php-ext-install mbstring fileinfo

WORKDIR /app
COPY . /app

# storage/ and public/uploads/ are expected to be mounted as persistent
# volumes in production; create them so a fresh deploy without volumes
# attached still boots cleanly.
RUN mkdir -p storage public/uploads

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public"]
