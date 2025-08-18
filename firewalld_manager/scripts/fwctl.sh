#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
PATH="/usr/sbin:/usr/bin:/sbin:/bin"

die(){ echo "ERROR: $*" >&2; exit 1; }
require_firewalld(){ command -v firewall-cmd >/dev/null 2>&1 || die "firewall-cmd not found"; }

json_escape() { python3 - <<'PY' -- "$1"
import json,sys; print(json.dumps(sys.argv[1]))
PY
}
list_to_json_array(){ python3 - <<'PY'
import json,sys; print(json.dumps([x for x in sys.stdin.read().splitlines() if x.strip()]))
PY
}

systemd_status_json() {
  local active="unknown" enabled="unknown"
  if command -v systemctl >/dev/null 2>&1; then
    active=$(systemctl is-active firewalld || true)
    enabled=$(systemctl is-enabled firewalld || true)
  fi
  printf '{\"active\": %s, \"enabled\": %s}' "$(json_escape "$active")" "$(json_escape "$enabled")"
}

status_json(){
  require_firewalld
  local version="" default_zone="" zones="" panic=""
  version=$(firewall-cmd --version || echo "")
  default_zone=$(firewall-cmd --get-default-zone || echo "")
  zones=$(firewall-cmd --get-zones || echo "")
  firewall-cmd --query-panic >/dev/null 2>&1 && panic="yes" || panic="no"

  printf '{'
  printf '"systemd": %s,' "$(systemd_status_json)"
  printf '"version": %s,' "$(json_escape "$version")"
  printf '"default_zone": %s,' "$(json_escape "$default_zone")"
  printf '"panic_mode": %s,' "$(json_escape "$panic")"
  printf '"zones": '
  echo "$zones" | tr ' ' '\n' | list_to_json_array
  printf '}'
}

valid_zone(){ firewall-cmd --get-zones | tr ' ' '\n' | grep -Ex -- "$1" >/dev/null 2>&1; }

zone_info_json(){
  require_firewalld
  local zone="$1"; valid_zone "$zone" || die "Invalid zone: $zone"
  local services ports sources interfaces rich
  services=$(firewall-cmd --zone="$zone" --list-services || echo "")
  ports=$(firewall-cmd --zone="$zone" --list-ports || echo "")
  sources=$(firewall-cmd --zone="$zone" --list-sources || echo "")
  interfaces=$(firewall-cmd --zone="$zone" --list-interfaces || echo "")
  rich=$(firewall-cmd --zone="$zone" --list-rich-rules || echo "")

  printf '{'
  printf '"zone": %s,' "$(json_escape "$zone")"
  printf '"services": '; echo "$services" | tr ' ' '\n' | list_to_json_array; printf ','
  printf '"ports": '; echo "$ports" | tr ' ' '\n' | list_to_json_array; printf ','
  printf '"sources": '; echo "$sources" | tr ' ' '\n' | list_to_json_array; printf ','
  printf '"interfaces": '; echo "$interfaces" | tr ' ' '\n' | list_to_json_array; printf ','
  printf '"rich_rules": '; echo "$rich" | list_to_json_array
  printf '}'
}

get_services_json(){ require_firewalld; firewall-cmd --get-services | tr ' ' '\n' | list_to_json_array; }
list_interfaces_json(){
  local out=""
  if command -v ip >/dev/null 2>&1; then
    out=$(ip -o link show | awk -F': ' '{print $2}' | sed 's/@.*//' | grep -v '^lo$' || true)
  fi
  echo "$out" | list_to_json_array
}

add_service(){ local zone="$1" service="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$service" ]] || die "Service required"
  local args=(--zone="$zone" --add-service="$service"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}
remove_service(){ local zone="$1" service="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$service" ]] || die "Service required"
  local args=(--zone="$zone" --remove-service="$service"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}

add_port(){ local zone="$1" pp="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ "$pp" =~ ^[0-9]{1,5}/(tcp|udp)$ ]] || die "Port must be like 443/tcp"
  local args=(--zone="$zone" --add-port="$pp"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}
remove_port(){ local zone="$1" pp="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ "$pp" =~ ^[0-9]{1,5}/(tcp|udp)$ ]] || die "Port must be like 443/tcp"
  local args=(--zone="$zone" --remove-port="$pp"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}

add_source(){ local zone="$1" src="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$src" ]] || die "Source CIDR required"
  local args=(--zone="$zone" --add-source="$src"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}
remove_source(){ local zone="$1" src="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$src" ]] || die "Source CIDR required"
  local args=(--zone="$zone" --remove-source="$src"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}

add_rich_rule(){ local zone="$1" rule="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$rule" ]] || die "Rich rule required"
  local args=(--zone="$zone" --add-rich-rule="$rule"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}
remove_rich_rule(){ local zone="$1" rule="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$rule" ]] || die "Rich rule required"
  local args=(--zone="$zone" --remove-rich-rule="$rule"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}

add_interface(){ local zone="$1" iface="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$iface" ]] || die "Interface required"
  local args=(--zone="$zone" --add-interface="$iface"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}
remove_interface(){ local zone="$1" iface="$2" permanent="${3:-no}"
  valid_zone "$zone" || die "Invalid zone"; [[ -n "$iface" ]] || die "Interface required"
  local args=(--zone="$zone" --remove-interface="$iface"); [[ "$permanent" == "yes" ]] && args+=(--permanent)
  firewall-cmd "${args[@]}"; [[ "$permanent" == "yes" ]] && firewall-cmd --reload
}

create_zone(){ local zone="$1"; [[ -n "$zone" ]] || die "Zone name required"; firewall-cmd --permanent --new-zone="$zone"; firewall-cmd --reload; }
delete_zone(){ local zone="$1"; [[ -n "$zone" ]] || die "Zone name required"; local def=$(firewall-cmd --get-default-zone || echo ""); [[ "$zone" == "$def" ]] && die "Cannot delete the default zone"; firewall-cmd --permanent --delete-zone="$zone"; firewall-cmd --reload; }
set_default_zone(){ local zone="$1"; valid_zone "$zone" || die "Invalid zone"; firewall-cmd --set-default-zone="$zone"; }

panic(){ case "${1:-}" in on) firewall-cmd --panic-on ;; off) firewall-cmd --panic-off ;; *) die "Use on|off";; esac; }
icmp_block(){ local cmd="$1" type="${2:-echo-request}"; case "$cmd" in add) firewall-cmd --add-icmp-block="$type" --permanent; firewall-cmd --reload ;; remove) firewall-cmd --remove-icmp-block="$type" --permanent; firewall-cmd --reload ;; list) firewall-cmd --list-icmp-blocks ;; *) die "Use add|remove|list [type]";; esac; }

service_control(){ local cmd="$1"; command -v systemctl >/dev/null 2>&1 || die "systemctl not found"; case "$cmd" in start|stop|restart|reload|enable|disable|status) systemctl "$cmd" firewalld ;; *) die "Use start|stop|restart|reload|enable|disable|status";; esac; }

usage(){ cat <<EOF
Usage:
  $0 status-json
  $0 zone-info-json <zone>
  $0 get-services-json
  $0 list-interfaces-json
  $0 add-service <zone> <service> [permanent: yes|no]
  $0 remove-service <zone> <service> [permanent: yes|no]
  $0 add-port <zone> <port/proto> [permanent: yes|no]
  $0 remove-port <zone> <port/proto> [permanent: yes|no]
  $0 add-source <zone> <cidr> [permanent: yes|no]
  $0 remove-source <zone> <cidr> [permanent: yes|no]
  $0 add-rich-rule <zone> <rule> [permanent: yes|no]
  $0 remove-rich-rule <zone> <rule> [permanent: yes|no]
  $0 add-interface <zone> <iface> [permanent: yes|no]
  $0 remove-interface <zone> <iface> [permanent: yes|no]
  $0 create-zone <zone>
  $0 delete-zone <zone>
  $0 set-default-zone <zone>
  $0 panic <on|off>
  $0 icmp-block <add|remove|list> [type]
  $0 service <start|stop|restart|reload|enable|disable|status>
EOF
}

cmd="${1:-}"; shift || true
case "${cmd:-}" in
  status-json) status_json ;;
  zone-info-json) zone_info_json "${1:-}" ;;
  get-services-json) get_services_json ;;
  list-interfaces-json) list_interfaces_json ;;
  add-service) add_service "${1:-}" "${2:-}" "${3:-no}" ;;
  remove-service) remove_service "${1:-}" "${2:-}" "${3:-no}" ;;
  add-port) add_port "${1:-}" "${2:-}" "${3:-no}" ;;
  remove-port) remove_port "${1:-}" "${2:-}" "${3:-no}" ;;
  add-source) add_source "${1:-}" "${2:-}" "${3:-no}" ;;
  remove-source) remove_source "${1:-}" "${2:-}" "${3:-no}" ;;
  add-rich-rule) add_rich_rule "${1:-}" "${2:-}" "${3:-no}" ;;
  remove-rich-rule) remove_rich_rule "${1:-}" "${2:-}" "${3:-no}" ;;
  add-interface) add_interface "${1:-}" "${2:-}" "${3:-no}" ;;
  remove-interface) remove_interface "${1:-}" "${2:-}" "${3:-no}" ;;
  create-zone) create_zone "${1:-}" ;;
  delete-zone) delete_zone "${1:-}" ;;
  set-default-zone) set_default_zone "${1:-}" ;;
  panic) panic "${1:-}" ;;
  icmp-block) icmp_block "${1:-}" "${2:-echo-request}" ;;
  service) service_control "${1:-}" ;;
  *) usage; exit 1 ;;
esac
