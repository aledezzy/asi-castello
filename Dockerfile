FROM php:8.4-apache-bullseye

# Update, install security updates, PHP extensions and enable Apache modules
RUN apt-get update && \
	apt-get upgrade -y && \
	apt-get install -y --no-install-recommends \
	libfreetype6-dev \
	libjpeg-dev \
	libpng-dev \
	&& apt-get clean \
	&& rm -rf /var/lib/apt/lists/* \
	&& docker-php-ext-install mysqli pdo pdo_mysql \
	&& a2enmod rewrite

# Copia i file dell'applicazione
COPY ./app/ /var/www/html/

# Imposta i permessi
RUN chown -R www-data:www-data /var/www/html/