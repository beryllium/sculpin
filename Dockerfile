FROM alpine:3.21

RUN apk update && \
    apk upgrade -a && \
    apk add --no-cache \
        autoconf \
        automake \
        gettext \
        bash \
        binutils \
        bison \
        build-base \
        cmake \
        curl \
        file \
        flex \
        g++ \
        gcc \
        git \
        jq \
        libgcc \
        libtool \
        libstdc++ \
        linux-headers \
        m4 \
        make \
        pkgconfig \
        re2c \
        wget \
        xz \
        gettext-dev \
        binutils-gold

RUN curl -#fSL https://dl.static-php.dev/static-php-cli/common/php-8.5.8-cli-linux-$(uname -m).tar.gz | tar -xz -C /usr/local/bin && \
    chmod +x /usr/local/bin/php && \
    curl -#fSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-$(uname -m) -o spc && \
    mv spc /usr/local/bin/spc && \
    chmod +x /usr/local/bin/spc

RUN curl -#fSL https://getcomposer.org/download/latest-stable/composer.phar -o /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer && \
    curl -#fSL https://github.com/box-project/box/releases/download/4.7.0/box.phar -o /usr/local/bin/box && \
    chmod +x /usr/local/bin/box

WORKDIR /app
COPY ./src /app/src
COPY ./composer.* /app/
COPY ./bin /app/bin
COPY ./box.json.dist /app/box.json
COPY ./craft.yml /app/craft.yml
RUN composer install --no-dev && spc doctor --auto-fix -vvv && spc craft
RUN box compile && spc micro:combine bin/sculpin.phar && mv my-app sculpin-linux-$(uname -m)
