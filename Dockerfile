FROM php:7.3-cli-alpine

WORKDIR /app

COPY . /app

CMD ["/bin/sh", "-c", "php -r \"echo 'Invalid Command. You must pass a CMD from your docker-compose. Choose from the following ';print_r(glob(\"/app/*.php\"));\""]