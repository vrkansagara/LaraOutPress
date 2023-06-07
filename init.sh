#!/usr/bin/env bash

if [ ! -f "$(pwd)/composer.phar" ]; then
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
    then
        >&2 echo 'ERROR: Invalid installer checksum'
        rm composer-setup.php
        exit 1
    fi
    php composer-setup.php --quiet
    RESULT=$?
    rm composer-setup.php
fi

chmod +x $(pwd)/bin/*

php composer.phar  update
php composer.phar  dumpautoload -o
php composer.phar  run permission
php composer.phar  run test

clear
php public/index.php
