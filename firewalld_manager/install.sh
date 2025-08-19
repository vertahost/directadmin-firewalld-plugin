#!/bin/bash
set -euo pipefail
BASE="/usr/local/directadmin/plugins/firewalld_manager"
SUDOERS_FILE="/etc/sudoers.d/directadmin_firewalld_manager"
echo "[Firewalld Manager] Installing..."
if [[ -d "$(pwd)" && "$(pwd)" != "$BASE" ]]; then
  mkdir -p "$BASE"
  cp -a ./* "$BASE/" || true
fi
mkdir "$BASE/data"
chown -R diradmin:diradmin "$BASE" || true
chmod 0755 "$BASE"
find "$BASE/admin" -type f -name "*.html" -exec chmod 0755 {} +
find "$BASE" -type f -name "*.php" -exec chmod 0644 {} +
find "$BASE" -type f -name "*.css" -exec chmod 0644 {} +
chmod -R 0755 "$BASE/scripts" || true
chmod -R 0700 "$BASE/data" || true
[ -f "$BASE/plugin.conf" ] && chmod 0644 "$BASE/plugin.conf"

# sudoers rule for admin -> fwctl.sh
if [[ ! -f "$SUDOERS_FILE" ]]; then
  cat > "$SUDOERS_FILE" <<'EOF'
Defaults:admin !requiretty
admin ALL=(root) NOPASSWD: /usr/local/directadmin/plugins/firewalld_manager/scripts/fwctl.sh *
EOF
  chmod 0440 "$SUDOERS_FILE"
  visudo -c >/dev/null || { echo "visudo validation failed"; exit 1; }
fi
echo "[Firewalld Manager] Install complete."
