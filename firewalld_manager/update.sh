#!/bin/bash
set -euo pipefail
BASE="/usr/local/directadmin/plugins/firewalld_manager"
echo "[Firewalld Manager] Updating perms..."
chown -R diradmin:diradmin "$BASE" || true
chmod 0755 "$BASE"
find "$BASE/admin" -type f -name "*.html" -exec chmod 0755 {} +
find "$BASE" -type f -name "*.php" -exec chmod 0644 {} +
find "$BASE" -type f -name "*.css" -exec chmod 0644 {} +
chmod -R 0755 "$BASE/scripts" || true
chmod -R 0700 "$BASE/data" || true
[ -f "$BASE/plugin.conf" ] && chmod 0644 "$BASE/plugin.conf"
echo "[Firewalld Manager] Update done."
