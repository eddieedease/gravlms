#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
max_tries=30
count=0
while ! php -r "try { new PDO('mysql:host=db;dbname=my_app_db', 'admin', 'admin'); echo 'Connected'; } catch (PDOException \$e) { exit(1); }" > /dev/null 2>&1; do
    echo "MySQL not ready yet... retrying ($count/$max_tries)"
    sleep 2
    count=$((count+1))
    if [ $count -ge $max_tries ]; then
        echo "Error: MySQL did not become ready in time."
        exit 1
    fi
done

echo "MySQL is ready!"

# Run database initialization
echo "Running database initialization..."
php /var/www/html/init_db.php

# Start Apache
echo "Starting Apache..."
apache2-foreground
