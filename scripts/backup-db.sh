#!/usr/bin/env bash
# Respaldo de la base de PIA: dump comprimido, rotación local y copia cifrada
# a Google Drive mediante rclone. Se ejecuta a diario desde el crontab de deploy.
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/apps/pia}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/pia}"
RETENCION_LOCAL_DIAS="${RETENCION_LOCAL_DIAS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-pia-crypt:}"
RETENCION_REMOTA_DIAS="${RETENCION_REMOTA_DIAS:-90}"

log() { echo "$(date '+%F %T') $*"; }

umask 077
mkdir -p "$BACKUP_DIR"

archivo="$BACKUP_DIR/pia_$(date +%F_%H%M).sql.gz"
temporal="$archivo.tmp"
trap 'rm -f "$temporal"' EXIT

log "Iniciando respaldo en $archivo"
cd "$APP_DIR"
# La contraseña se lee dentro del contenedor y se pasa por MYSQL_PWD para que
# no aparezca en la lista de procesos.
docker compose exec -T db sh -c \
  'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --routines --triggers --databases "$MARIADB_DATABASE"' \
  | gzip -9 > "$temporal"

gzip -t "$temporal"
if ! zcat "$temporal" | tail -n 1 | grep -q 'Dump completed'; then
  log "ERROR: el dump está incompleto"
  exit 1
fi
mv "$temporal" "$archivo"
log "Respaldo local creado ($(du -h "$archivo" | cut -f1))"

find "$BACKUP_DIR" -name 'pia_*.sql.gz' -mtime +"$RETENCION_LOCAL_DIAS" -delete

if [ -n "$RCLONE_REMOTE" ]; then
  rclone copy "$archivo" "$RCLONE_REMOTE"
  rclone delete --min-age "${RETENCION_REMOTA_DIAS}d" "$RCLONE_REMOTE"
  log "Copia cifrada subida a $RCLONE_REMOTE"
fi

log "Respaldo finalizado"
