#!/bin/sh
# Start bazy, import database.sql przy pierwszym uruchomieniu, potem Apache.
set -e

# Wyjście skryptu do pliku - zapis na stdout w Cloudflare Containers potrafił
# przerwać skrypt (set -e), przez co kontener od razu się wyłączał.
exec >> /var/log/msygnalista-start.log 2>&1

# Gdy start się nie uda, zamiast wyłączać kontener pokaż log startu
# (strona i tak jest za bramką z kodem dostępu).
awaria() {
    echo "[start] blad uruchamiania"
    tail -40 /var/log/mysql/error.log 2>/dev/null || true
    mkdir -p /awaria
    printf '<?php header("Content-Type: text/plain; charset=utf-8"); readfile("/var/log/msygnalista-start.log");' > /awaria/index.php
    exec php -S 0.0.0.0:80 -t /awaria
}
trap awaria EXIT

mkdir -p /run/mysqld
mkdir -p /var/log/mysql
chown mysql:mysql /run/mysqld /var/log/mysql

echo "[start] uruchamiam MariaDB"
# bez natywnego AIO - w sandboxie Cloudflare io_uring/libaio może nie działać
mysqld_safe --bind-address=127.0.0.1 --skip-name-resolve \
    --innodb-use-native-aio=0 --log-error=/var/log/mysql/error.log &

for i in $(seq 1 90); do
    mysqladmin ping --silent 2>/dev/null && break
    sleep 1
done

if ! mysqladmin ping --silent 2>/dev/null; then
    echo "[start] MariaDB nie wystartowała"
    exit 1
fi
echo "[start] MariaDB działa"

if [ ! -f /var/www/html/config/config.php ]; then
    HASLO=$(php -r 'echo bin2hex(random_bytes(16));')

    if ! mysql -e "USE msygnalista" 2>/dev/null; then
        mysql < /var/www/html/database/database.sql
    fi

    mysql -e "CREATE USER IF NOT EXISTS 'msygnalista'@'127.0.0.1' IDENTIFIED BY '$HASLO';
              ALTER USER 'msygnalista'@'127.0.0.1' IDENTIFIED BY '$HASLO';
              GRANT SELECT, INSERT, UPDATE, DELETE ON msygnalista.* TO 'msygnalista'@'127.0.0.1';"

    cat > /var/www/html/config/config.php <<CFG
<?php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'msygnalista',
    'username' => 'msygnalista',
    'password' => '$HASLO',
];
CFG
    chown root:www-data /var/www/html/config/config.php
    chmod 640 /var/www/html/config/config.php
fi

echo "[start] uruchamiam Apache"
trap - EXIT
exec apache2-foreground
