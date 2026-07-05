#!/bin/bash

printenv | grep -v "no_proxy" >> /etc/environment
cron
composer install

apache2-foreground