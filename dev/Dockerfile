# 로컬 개발용 PHP + Apache (운영은 Cafe24 공유 호스팅)
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql
# Cafe24 와 같은 배치: deploy/ 전체를 올리고 www/ 만 웹 루트로
ENV APACHE_DOCUMENT_ROOT=/var/www/app/www
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
