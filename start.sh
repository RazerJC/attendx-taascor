#!/bin/bash
set -e

PORT="${PORT:-10000}"

# ── Configure Apache port ──
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# ── Start embedded MariaDB ──
mkdir -p /run/mysqld
chown mysql:mysql /run/mysqld
chown -R mysql:mysql /var/lib/mysql

if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "[attendx] Initializing MariaDB data directory..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql
fi

chown -R mysql:mysql /var/lib/mysql

echo "[attendx] Starting MariaDB..."
mysqld_safe --user=mysql --skip-grant-tables \
    --bind-address=127.0.0.1 \
    --innodb-buffer-pool-size=32M \
    --key-buffer-size=8M \
    --max-connections=20 &

DB_READY=false
for i in $(seq 1 30); do
    if mysqladmin --protocol=tcp -h 127.0.0.1 ping --silent 2>/dev/null; then
        echo "[attendx] MariaDB is ready."
        DB_READY=true
        break
    fi
    sleep 1
done

if [ "$DB_READY" = false ]; then
    echo "[attendx] ERROR: MariaDB failed to start within 30 seconds!"
    exit 1
fi

# ── Initialize app database ──
mysql --protocol=tcp -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS taascor_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

TABLE_COUNT=$(mysql --protocol=tcp -h 127.0.0.1 -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'taascor_attendance';" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt "5" ]; then
    echo "[attendx] Creating tables..."
    mysql --protocol=tcp -h 127.0.0.1 -u root taascor_attendance < /var/www/html/ATTENDANCE/init-database.sql

    ADMIN_HASH=$(php -r "echo password_hash('admin123', PASSWORD_BCRYPT);")
    COOR_HASH=$(php -r "echo password_hash('coor1', PASSWORD_BCRYPT);")

    mysql --protocol=tcp -h 127.0.0.1 -u root taascor_attendance -e "
        UPDATE users SET password='${ADMIN_HASH}' WHERE username='admin';
        INSERT IGNORE INTO users (username, password, full_name, role, status)
        VALUES ('coor1', '${COOR_HASH}', 'Coordinator', 'coordinator', 'active');
    "

    echo "╔══════════════════════════════════════╗"
    echo "║   ✅ Database Ready!                 ║"
    echo "║   Admin:       admin / admin123      ║"
    echo "║   Coordinator: coor1 / coor1         ║"
    echo "╚══════════════════════════════════════╝"
fi

# ── Start Apache ──
echo "[attendx] Starting Apache on port ${PORT}..."
exec apache2-foreground
