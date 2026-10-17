FROM php:8.3.35-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev && rm -rf /var/lib/apt/lists/* && docker-php-ext-configure gd --with-jpeg --with-webp && docker-php-ext-install pdo_mysql gd opcache && a2enmod headers rewrite && mkdir -p /var/www/private-uploads && chown www-data:www-data /var/www/private-uploads
RUN php -r '$hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID); if (!is_string($hash) || file_put_contents("/etc/webboard-dummy-password-hash", $hash . PHP_EOL) === false) { exit(1); }' && chown root:root /etc/webboard-dummy-password-hash && chmod 0444 /etc/webboard-dummy-password-hash
COPY apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY apache/security.conf /etc/apache2/conf-available/security.conf
RUN a2enconf security
COPY php/hardening.ini /usr/local/etc/php/conf.d/hardening.ini
COPY src/ /var/www/app/
COPY bin/ /var/www/app/bin/
RUN chown -R root:root /var/www/app && chmod -R go-w /var/www/app
WORKDIR /var/www/app
