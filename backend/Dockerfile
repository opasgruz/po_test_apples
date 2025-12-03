FROM yiisoftware/yii2-php:8.1-apache

# Включаем модуль rewrite
RUN a2enmod rewrite

# Разрешаем использование .htaccess (меняем AllowOverride None на All)
# Это важно! Иначе Apache просто проигнорирует ваш файл.
ENV APACHE_DOCUMENT_ROOT /app/backend/web
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Change document root for Apache
RUN sed -i -e 's|/app/web|/app/backend/web|g' /etc/apache2/sites-available/000-default.conf
