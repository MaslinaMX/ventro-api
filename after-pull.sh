#!/bin/bash
set -e
export COMPOSER_ALLOW_SUPERUSER=1
echo "🚀 Iniciando deploy..."
cd /var/www/ventro/api

echo "📥 Git pull..."
git pull origin main

echo "📦 Composer install..."
composer install --no-dev --optimize-autoloader --no-scripts
composer dump-autoload --optimize

echo "🔒 Permisos..."
sudo chown -R www-data:www-data /var/www/ventro/api
sudo chmod -R 775 /var/www/ventro/api/storage /var/www/ventro/api/bootstrap/cache

echo "🧹 Limpiando cachés..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo "🗄️ Migraciones central..."
php artisan migrate --force
echo "🗄️ Migraciones tenants..."
php artisan tenants:migrate --force

echo "⚡ Cacheando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "🔁 Reiniciando queue worker..."
php artisan queue:restart

echo "🔄 Reiniciando servicios..."
sudo systemctl restart php8.4-fpm
sudo systemctl reload nginx

echo "✅ Deploy completado exitosamente"