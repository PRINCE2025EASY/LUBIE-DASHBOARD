# Production image for container hosts (Render free web service). PHP 8.2 + Apache.
# No secrets in the image: every setting comes from the host's environment variables.
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-req="ext-*"

FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql opcache \
 && a2enmod rewrite headers deflate expires \
 && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-lubie.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/lubie-entrypoint
WORKDIR /var/www/lubie
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
RUN chmod +x /usr/local/bin/lubie-entrypoint \
 && rm -rf .git .github tests tools docs node_modules .env \
 && php bin/lint.php
ENV APP_ENV=production APP_BASE_PATH= SESSION_SECURE_COOKIE=1 TRUSTED_PROXY_HOPS=1 PORT=10000
EXPOSE 10000
HEALTHCHECK --interval=30s --timeout=5s CMD php -r 'exit(@file_get_contents("http://127.0.0.1:".getenv("PORT")."/health") ? 0 : 1);'
ENTRYPOINT ["lubie-entrypoint"]
CMD ["apache2-foreground"]
