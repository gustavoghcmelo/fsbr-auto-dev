# Buildando VueJS
FROM node:20.19.0 as NPMBUILD
ARG environment

WORKDIR /app

# 1️⃣ Copia SOMENTE arquivos de dependência
COPY package.json package-lock.json ./

# 2️⃣ Instala de forma determinística e rápida
RUN npm ci --legacy-peer-deps

# 3️⃣ Agora sim copia o resto do projeto
COPY . .

# 4️⃣ Copia o .env do ambiente para que VITE_BASE_PATH esteja disponível durante o build
COPY ./env.$environment ./.env

# 5️⃣ Build do Vue
RUN npm run build

# Base PHP 8.4 + FPM
FROM php:8.4-fpm

ARG environment
# -------------------------------------------------------------------------
# Instalar NGINX + utilitários
# -------------------------------------------------------------------------
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    nginx \
    supervisor \
    unzip \
    zip \
    nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


# Instalar extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        xml \
        gd \
        zip

# -------------------------------------------------------------------------
# Configurar diretórios
# -------------------------------------------------------------------------
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /run/php \
    && mkdir -p /run/nginx

# WebRoot igual ao Wyveo
WORKDIR /usr/share/nginx

# -------------------------------------------------------------------------
# Copiar projeto
# -------------------------------------------------------------------------
COPY composer.json composer.phar /usr/share/nginx/
COPY  --from=NPMBUILD /app .
COPY . .
COPY ./env.$environment ./.env

# -------------------------------------------------------------------------
# Instalar dependências PHP
# -------------------------------------------------------------------------
RUN php composer.phar install --no-interaction
RUN php composer.phar dump-autoload

# -------------------------------------------------------------------------
# Uptime
# -------------------------------------------------------------------------
RUN rm -rf /usr/share/nginx/storage/app/uptime.dat \
    && echo 1 > /usr/share/nginx/storage/app/uptime.dat

# -------------------------------------------------------------------------
# Permissões
# -------------------------------------------------------------------------
RUN chown -R www-data:www-data storage bootstrap/cache

# -------------------------------------------------------------------------
# NGINX config
# -------------------------------------------------------------------------
COPY ./docker/nginx/default_nginx.conf /etc/nginx/sites-available/default

# Alterando os limites de uso de memória e tempo de execução
RUN echo "upload_max_filesize = 500M" >> /usr/local/etc/php/conf.d/php-custom.ini && \
    echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/php-custom.ini && \
    echo "max_execution_time = 3600" >> /usr/local/etc/php/conf.d/php-custom.ini && \
    echo "memory_limit = -1" >> /usr/local/etc/php/conf.d/php-custom.ini

# -------------------------------------------------------------------------
# Supervisor — para rodar PHP-FPM + NGINX
# -------------------------------------------------------------------------
COPY ./docker/supervisord.conf /etc/supervisor/supervisord.conf
#RUN php artisan down
EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
