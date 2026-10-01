
#!/usr/bin/env sh
set -e


APP_DIR="/var/www/html"
cd "$APP_DIR"

echo "Reparando permisos de storage..."
# Crear carpetas
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache

# 2. Composer install (Solo si falta vendor o en cambios)
# if [ -f composer.json ]; then
#   echo "Verificando dependencias..."
#   composer install --no-interaction --prefer-dist --optimize-autoloader || echo "Error en composer install"
# fi
if [ -f "vendor/autoload.php" ]; then
    echo "Dependencias (vendor) ya detectadas. Saltando instalación..."
else
    echo "Instalando dependencias..."
    composer install --no-interaction --prefer-dist --optimize-autoloader || echo "Error en composer install"
    # Validar si el comando anterior tuvo éxito
    if [ $? -ne 0 ]; then
        echo "Error crítico: Falló composer install"
        exit 1
    fi
fi


# Generar key si no existe APP_KEY
# 3. Generar APP_KEY si falta
if [ -f .env ]; then
    if ! grep -q "APP_KEY=base64" .env; then
        echo "Generando APP_KEY..."
        php artisan key:generate --force
    fi
fi

# 4. Base de datos
echo "Ejecutando migraciones..."
php artisan migrate --force || echo "Migraciones fallidas o ya ejecutadas"

# Seeders solo si la BD está vacía (primera instalación)
echo "Verificando si se necesitan seeders..."
DB_USER_COUNT=$(mysql -h "${DB_HOST:-mysql}" -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD}" "${DB_DATABASE:-inventario}" \
  -se "SELECT COUNT(*) FROM users;" 2>/dev/null || echo "0")

if [ -z "$DB_USER_COUNT" ] || [ "$DB_USER_COUNT" -eq 0 ] 2>/dev/null; then
    echo "Base de datos vacía. Ejecutando seeders iniciales..."
    php artisan db:seed --force || echo "Error en seeders"
else
    echo "Base de datos con datos existentes (${DB_USER_COUNT} usuarios). Saltando seeders."
fi


# 5. Limpiezas preventivas (Mejor que cachear en desarrollo)
echo "Limpiando caches..."
php artisan config:clear
php artisan view:clear
php artisan cache:clear

# 6. Enlace de storage
php artisan storage:link --force || true

# 7. Los comandos artisan corren como root y crean archivos (ej. laravel.log) que php-fpm (www-data) no puede escribir
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
