FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

#pré-requisitos mínimos para PPA
RUN apt-get update && apt-get install -y \
    software-properties-common \
    ca-certificates \
    curl \
    gnupg \
    git \
    unzip

#adicionar PPA do Ondřej Surý (Ubuntu)
RUN add-apt-repository ppa:ondrej/php -y

#instalar PHP
RUN apt-get update && apt-get install -y \
    php8.5-dev \
    php8.5-cli \
    php8.5-xml \
    php8.5-curl

RUN apt-get install -y \
    libfann-dev

#PECL já está deprecated, mas algumas extensões ainda não estão no PIE
RUN pecl install channel://pecl.php.net/fann-1.2.0
RUN echo "extension=fann.so" > /etc/php/8.5/cli/conf.d/20-fann.ini

#Composer
RUN apt-get install wget -y

RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/f3108f64b4e1c1ce6eb462b159956461592b3e3e/web/installer -O - -q | php && \
    mv composer.phar /usr/bin/composer

#PHP PIE
RUN apt install -y \
    gcc \
    make \
    autoconf \
    libtool \
    bison \
    re2c \
    pkg-config \
    php-dev

RUN wget https://github.com/php/pie/releases/download/1.3.7/pie.phar && \
    mv pie.phar /usr/bin/pie && \
    chmod +x /usr/bin/pie

#AHPd (AHP Data-Driven)
RUN pie install wead/ahpd

WORKDIR /livro