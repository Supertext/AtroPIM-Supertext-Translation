#!/bin/bash
# Runs an AtroCore console command as the web server user, e.g. demo-console supertext check
exec runuser -u www-data -- php /var/www/atro/console.php "$*"
