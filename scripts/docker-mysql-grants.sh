#!/bin/sh
# Apply tenant DB grants on an existing MySQL volume (init scripts only run on first boot).
set -e
ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-toweros}"

docker compose --env-file .env.docker exec -T mysql mysql -uroot -p"${ROOT_PASSWORD}" <<'SQL'
GRANT CREATE, DROP, ALTER, REFERENCES, LOCK TABLES, EXECUTE ON *.* TO 'toweros'@'%';
GRANT ALL PRIVILEGES ON `toweros`.* TO 'toweros'@'%';
GRANT ALL PRIVILEGES ON `tenant%`.* TO 'toweros'@'%';
FLUSH PRIVILEGES;
SQL

echo "MySQL grants applied for user toweros (tenant database provisioning)."
