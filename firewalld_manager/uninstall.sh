#!/bin/bash
set -euo pipefail
BASE="/usr/local/directadmin/plugins/firewalld_manager"
SUDOERS_FILE="/etc/sudoers.d/directadmin_firewalld_manager"
echo "[Firewalld Manager] Uninstalling..."
rm -f "$SUDOERS_FILE" || true
rm -rf "$BASE" || true
echo "[Firewalld Manager] Uninstalled."
