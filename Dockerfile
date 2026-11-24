# Étape 1 : compilation du moteur de jeu en C (module L1). Même base Debian que l'image PHP.
FROM debian:bookworm-slim AS moteur
RUN apt-get update \
    && apt-get install -y --no-install-recommends gcc libc6-dev make \
    && rm -rf /var/lib/apt/lists/*
COPY moteur /moteur
RUN make -C /moteur clean all

# Étape 2 : site PHP. Seuls les exécutables du moteur sont repris, pas le compilateur.
FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Seul public/ est servi : le reste du projet n'est pas accessible depuis le web.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Port attendu par Render, et version d'Apache masquée.
RUN sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-securite.conf

COPY . /var/www/html/
COPY --from=moteur /moteur/bin /var/www/html/moteur/bin
RUN chown -R www-data:www-data /var/www/html/data

EXPOSE 10000
