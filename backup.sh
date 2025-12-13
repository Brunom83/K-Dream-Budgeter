#!/bin/bash
# backup.sh - O Salvador de Vidas

# Define o nome do ficheiro (ex: kdream_2023-10-25.sql)
FILENAME="backups/kdream_$(date +%F).sql"

# Comando mágico (ajusta o nome do contentor se for diferente, usa 'docker ps' para ver)
# Normalmente é: nomepasta-db-1
docker exec k-dream-budgeter-db-1 mysqldump -u k_dream_user -pk_dream_password k_dream_budgeter > $FILENAME

# Apagar backups com mais de 7 dias (para não encher o disco)
find backups/ -name "*.sql" -mtime +7 -delete