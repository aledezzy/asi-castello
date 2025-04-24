FROM php:8.2-apache

# Update and install security updates
RUN apt-get update && \
	apt-get upgrade -y && \
	apt-get install -y --no-install-recommends \
	libpng-dev \
	libjpeg-dev \
	libfreetype6-dev \
	&& apt-get clean \
	&& rm -rf /var/lib/apt/lists/*

# Installa estensioni PHP necessarie
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Abilita il modulo rewrite di Apache
RUN a2enmod rewrite

# Copia i file dell'applicazione
COPY ./app/ /var/www/html/

# Imposta i permessi
RUN chown -R www-data:www-data /var/www/html/