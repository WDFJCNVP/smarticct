web: sh -c 'if [ "$PROCESS_ROLE" = "reverb" ]; then php artisan reverb:start --host=0.0.0.0 --port=$PORT; else vendor/bin/heroku-php-apache2 public/; fi'
worker: php artisan queue:work --tries=3
release: sh -c 'if [ "$PROCESS_ROLE" != "reverb" ]; then php artisan migrate --force; fi'