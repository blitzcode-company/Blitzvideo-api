#!/bin/bash

MINIO_URL="http://minio:9000"
MINIO_ACCESS_KEY="TU_ACCESS_KEY"
MINIO_SECRET_KEY="TU_SECRET_KEY"
BUCKET="blitzvideo-bucket"

FILE_PATH="$1"
LOG_FILE="/tmp/upload_log.txt"

if [ ! -w "$LOG_FILE" ]; then
  echo "[ERROR] No se tiene permiso para escribir en el archivo de log. Intentando cambiar permisos..." >> "$LOG_FILE"
  chmod 777 "$LOG_FILE"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] -------------------------------------------------------------" >> "$LOG_FILE"
echo "[INFO] Ejecutando upload_to_minio.sh con archivo: $FILE_PATH" >> "$LOG_FILE"

if [ -z "$FILE_PATH" ]; then
  echo "[ERROR] FILE_PATH vacío. No se recibió ningún archivo." >> "$LOG_FILE"
  exit 1
fi

FILE_PATH=$(realpath "$FILE_PATH")
FILE_NAME=$(basename "$FILE_PATH")

echo "[INFO] basename de FILE_PATH : $FILE_PATH" >> "$LOG_FILE"
echo "[INFO] basename de FILE_NAME : $FILE_NAME" >> "$LOG_FILE"

export PATH=$PATH:/usr/local/bin

if ! command -v mc &> /dev/null; then
    echo "[ERROR] mc no está disponible en el contenedor." >> "$LOG_FILE"
    exit 1
fi

export MC_CONFIG_DIR="/tmp/.mc"

echo "[INFO] Configurando MinIO..." >> "$LOG_FILE"
mc alias set myminio "$MINIO_URL" "$MINIO_ACCESS_KEY" "$MINIO_SECRET_KEY" >> "$LOG_FILE" 2>&1

if [ $? -ne 0 ]; then
    echo "[ERROR] No se pudo configurar el alias de MinIO." >> "$LOG_FILE"
    exit 1
fi

echo "[INFO] Subiendo $FILE_NAME al bucket $BUCKET..." >> "$LOG_FILE"

mc cp "$FILE_PATH" myminio/$BUCKET/streams/"$FILE_NAME" >> "$LOG_FILE" 2>> /tmp/upload_errors.log

if [ $? -eq 0 ]; then
  echo "[INFO] Archivo subido correctamente. Eliminando..." >> "$LOG_FILE"
  rm "$FILE_PATH"
else
  echo "[ERROR] Falló la subida. No se elimina el archivo." >> "$LOG_FILE"
fi
