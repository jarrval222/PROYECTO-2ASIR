#!/bin/sh

TARGETS=${1:-"all"}
DATE=$(date +%Y%m%d_%H%M%S)

echo "=== Iniciando Backup: $DATE | Objetivos: $TARGETS ==="

# 1. Copia de MySQL
if echo "$TARGETS" | grep -q "mysql" || [ "$TARGETS" = "all" ]; then
    echo "[MySQL] Generando volcado..."
    docker exec db mysqldump -u root -proot --all-databases > "/backups/mysql_$DATE.sql"
fi

# 2. Copia de LDAP
if echo "$TARGETS" | grep -q "ldap" || [ "$TARGETS" = "all" ]; then
    echo "[LDAP] Generando volcado estructural..."
    docker exec ldap-server slapcat > "/backups/ldap_$DATE.ldif"
fi

# 3. Copia de Grafana
if echo "$TARGETS" | grep -q "grafana" || [ "$TARGETS" = "all" ]; then
    echo "[Grafana] Comprimiendo configuraciones y dashboards..."
    tar -czf "/backups/grafana_$DATE.tar.gz" -C /backup_src/grafana .
fi

# 4. Copia de VPN (Wireguard)
if echo "$TARGETS" | grep -q "vpn" || [ "$TARGETS" = "all" ]; then
    echo "[VPN] Comprimiendo perfiles y claves de Wireguard..."
    tar -czf "/backups/vpn_$DATE.tar.gz" -C /backup_src/vpn .
fi

# 5. Copia de Archivos Redmine
if echo "$TARGETS" | grep -q "redmine" || [ "$TARGETS" = "all" ]; then
    echo "[Redmine] Comprimiendo adjuntos de tareas..."
    tar -czf "/backups/redmine_$DATE.tar.gz" -C /backup_src/redmine_files .
fi

# 6. Copia de Buzones de Correo
if echo "$TARGETS" | grep -q "mail" || [ "$TARGETS" = "all" ]; then
    echo "[Mail] Comprimiendo buzones de correo..."
    tar -czf "/backups/mail_$DATE.tar.gz" -C /backup_src/mail .
fi

# Rotación (Borra archivos de más de 7 días)
find /backups -name "*.sql" -mtime +7 -exec rm {} \;
find /backups -name "*.ldif" -mtime +7 -exec rm {} \;
find /backups -name "*.tar.gz" -mtime +7 -exec rm {} \;

echo "=== Backup Completado ==="