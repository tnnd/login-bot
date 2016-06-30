#! /bin/sh

script_path='/root/login-bot/'
log=${script_path}login-bot.log

cd $script_path
php -f ${script_path}login-bot.php

echo "`date` => crond service started." >> $log

