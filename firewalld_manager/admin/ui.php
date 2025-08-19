<?php
/**
 * Persistent warning popup for DirectAdmin plugin pages.
 * Dismissal persists across reloads (localStorage with cookie fallback).
 *
 * Usage:
 *   render_da_warning_popup_persist(
 *     "This plugin can change system settings. Proceed carefully.",
 *     ['key' => 'firewalld_manager_warning', 'scope' => 'local'] // 'local' or 'session'
 *   );
 */
function render_da_warning_popup_persist($message = "This plugin can change system settings. Proceed carefully. You can break things.", array $opts = []) {
    static $rendered = false; if ($rendered) return; $rendered = true;

    $key = isset($opts['key']) && $opts['key'] !== ''
         ? preg_replace('/[^A-Za-z0-9_.:-]/','_', (string)$opts['key'])
         : ('da_plugin_warning_' . md5($_SERVER['SCRIPT_NAME'] ?? 'da'));
    $scope = (isset($opts['scope']) && $opts['scope'] === 'session') ? 'session' : 'local';

    $msg      = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $key_js   = json_encode($key,   JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
    $scope_js = json_encode($scope, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

    echo <<<HTML
<div id="da-popup-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);align-items:center;justify-content:center;z-index:99999;">
  <div role="dialog" aria-modal="true" aria-labelledby="da-popup-title" style="background:#fff;color:#000;min-width:320px;max-width:560px;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.25);padding:18px 16px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif">
    <h3 id="da-popup-title" style="margin:0 0 10px;font-size:18px">Heads up!</h3>
    <p style="margin:0 0 14px;">{$msg}</p>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <button id="da-popup-ok" type="button"
        style="padding:8px 14px;border:1px solid #1e5cb3;background:#2d7df7;color:#fff;border-radius:8px;cursor:pointer;">
        OK
      </button>
    </div>
  </div>
</div>
<script>
(function(){
  var KEY = {$key_js};
  var SCOPE = {$scope_js};
  var overlay = document.getElementById('da-popup-overlay');

  function storageObj(){
    try { return SCOPE === "session" ? window.sessionStorage : window.localStorage; } catch(e){ return null; }
  }
  function isDismissed(){
    var s = storageObj();
    if (s) { try { return s.getItem(KEY) === '1'; } catch(e){} }
    return document.cookie.indexOf(KEY + '=1') !== -1;
  }
  function setDismissed(){
    var s = storageObj();
    if (s) { try { s.setItem(KEY, '1'); return; } catch(e){} }
    var expires = new Date(); expires.setFullYear(expires.getFullYear()+5);
    document.cookie = KEY + '=1; path=/; expires=' + expires.toUTCString();
  }

  if (isDismissed()) {
    if (overlay) overlay.remove();
    return;
  }
  if (overlay) overlay.style.display = 'flex';

  var ok = document.getElementById('da-popup-ok');
  if (ok) ok.addEventListener('click', function(){
    if (overlay) overlay.remove();
    setDismissed();
  }, { once: true });
})();
</script>
HTML;
}

// Apply the popup
render_da_warning_popup_persist(
  "This plugin can change system settings. Proceed carefully. You can break things.",
  ['key' => 'firewalld_manager_warning', 'scope' => 'local'] // use 'session' for per-tab dismissal
);








require __DIR__.'/header.php';

// DirectAdmin passes query/body in env for CLI PHP
$_GET = []; $qs = getenv('QUERY_STRING'); if ($qs) { parse_str(html_entity_decode($qs), $ga); foreach ($ga as $k=>$v) $_GET[urldecode($k)] = urldecode($v); }
$_POST = []; $ps = getenv('POST'); if ($ps) { parse_str(html_entity_decode($ps), $pa); foreach ($pa as $k=>$v) $_POST[urldecode($k)] = urldecode($v); }

$message = null; $error = null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  $zone = $_POST['zone'] ?? '';
  $permanent = (!empty($_POST['permanent']) && $_POST['permanent']==='yes') ? 'yes' : 'no';
  try {
    switch ($action) {
      case 'service_start': case 'service_stop': case 'service_restart': case 'service_reload':
      case 'service_enable': case 'service_disable':
        $cmd = str_replace('service_', '', $action);
        fw_exec('service '.escapeshellarg($cmd));
        $message = ucfirst($cmd).' executed.'; break;
      case 'set_default_zone':
        if (!$zone) throw new Exception('Zone is required');
        fw_exec('set-default-zone '.escapeshellarg($zone));
        $message = 'Default zone set to '.h($zone); break;
      case 'create_zone':
        $newz = trim($_POST['new_zone'] ?? '');
        if (!$newz) throw new Exception('Zone name required');
        fw_exec('create-zone '.escapeshellarg($newz));
        $message = 'Zone created: '.h($newz); break;
      case 'delete_zone':
        $delz = trim($_POST['delete_zone'] ?? '');
        if (!$delz) throw new Exception('Zone name required');
        fw_exec('delete-zone '.escapeshellarg($delz));
        $message = 'Zone deleted: '.h($delz); break;
      case 'add_service': case 'remove_service':
        $zone = trim($_POST['zone'] ?? '');
        $svc_sel = trim($_POST['service_select'] ?? '');
        $svc_custom = trim($_POST['service_custom'] ?? '');
        $service = $svc_custom !== '' ? $svc_custom : $svc_sel;
        if (!$zone || !$service) throw new Exception('Zone and service required');
        $cmd = $action==='add_service' ? 'add-service' : 'remove-service';
        fw_exec($cmd.' '.escapeshellarg($zone).' '.escapeshellarg($service).' '.escapeshellarg($permanent));
        $message = ucfirst(str_replace('_',' ',$action)).' OK'; break;
      case 'add_port': case 'remove_port':
        $port = trim($_POST['port'] ?? ''); $proto = trim($_POST['proto'] ?? 'tcp');
        if (!$zone || !$port) throw new Exception('Zone and port required');
        $pp = $port.'/'.$proto;
        $cmd = $action==='add_port' ? 'add-port' : 'remove-port';
        fw_exec($cmd.' '.escapeshellarg($zone).' '.escapeshellarg($pp).' '.escapeshellarg($permanent));
        $message = ucfirst(str_replace('_',' ',$action)).' OK'; break;
      case 'add_source': case 'remove_source':
        $src = trim($_POST['source'] ?? '');
        if (!$zone || !$src) throw new Exception('Zone and source required');
        $cmd = $action==='add_source' ? 'add-source' : 'remove-source';
        fw_exec($cmd.' '.escapeshellarg($zone).' '.escapeshellarg($src).' '.escapeshellarg($permanent));
        $message = ucfirst(str_replace('_',' ',$action)).' OK'; break;
      case 'add_rich': case 'remove_rich':
        $rule = trim($_POST['rule'] ?? '');
        if (!$zone || !$rule) throw new Exception('Zone and rule required');
        $cmd = $action==='add_rich' ? 'add-rich-rule' : 'remove-rich-rule';
        fw_exec($cmd.' '.escapeshellarg($zone).' '.escapeshellarg($rule).' '.escapeshellarg($permanent));
        $message = ucfirst(str_replace('_',' ',$action)).' OK'; break;
      case 'add_iface': case 'remove_iface':
        $iface = trim($_POST['iface'] ?? '');
        if (!$zone || !$iface) throw new Exception('Zone and interface required');
        $cmd = $action==='add_iface' ? 'add-interface' : 'remove-interface';
        fw_exec($cmd.' '.escapeshellarg($zone).' '.escapeshellarg($iface).' '.escapeshellarg($permanent));
        $message = ucfirst(str_replace('_',' ',$action)).' OK'; break;
      case 'panic_on': fw_exec('panic on'); $message = 'Panic mode ON'; break;
      case 'panic_off': fw_exec('panic off'); $message = 'Panic mode OFF'; break;
      case 'icmp_add':
        $type = trim($_POST['icmp_type'] ?? 'echo-request');
        fw_exec('icmp-block add '.escapeshellarg($type)); $message = "ICMP block added: ".h($type); break;
      case 'icmp_remove':
        $type = trim($_POST['icmp_type'] ?? 'echo-request');
        fw_exec('icmp-block remove '.escapeshellarg($type)); $message = "ICMP block removed: ".h($type); break;
      default: $error = 'Unknown action';
    }
  } catch (Throwable $e){ $error = $e->getMessage(); }
}

$status = fw_json('status-json');
$zones = $status['zones'] ?? [];
$default_zone = $status['default_zone'] ?? '';
$systemd = $status['systemd'] ?? [];
$active = $systemd['active'] ?? 'unknown';
$enabled = $systemd['enabled'] ?? 'unknown';
$panic_mode = $status['panic_mode'] ?? 'no';
$services = fw_json('get-services-json');
$ifaces = fw_json('list-interfaces-json');

function badge($state,$labels){ $class='badge'; $text=$labels[$state]??$state;
  if (in_array($state,['active','running','yes','enabled'])) $class.=' ok';
  elseif (in_array($state,['inactive','failed','no','disabled'])) $class.=' bad';
  return '<span class="'.$class.'">'.h($text).'</span>';
}
?>
<link rel="stylesheet" href="/CMD_PLUGINS_ADMIN/firewalld_manager/images/style.css">
<div class="grid">
  <div class="panel">
    <h3>Service Status</h3>
    <div class="kvs">
      <div>firewalld:</div><div><?= badge($active,['active'=>'active','inactive'=>'inactive','failed'=>'failed']); ?></div>
      <div>Enabled on boot:</div><div><?= badge($enabled,['enabled'=>'enabled','disabled'=>'disabled']); ?></div>
      <div>Version:</div><div class="badge"><?= h($status['version'] ?? 'unknown'); ?></div>
      <div>Panic mode:</div><div><?= badge($panic_mode,['yes'=>'ON','no'=>'OFF']); ?></div>
      <div>Default zone:</div><div class="badge"><?= h($default_zone ?: 'unknown'); ?></div>
    </div>
    <div class="hr"></div>
    <form method="post" class="controls">
      <button name="action" value="service_start">Start</button>
      <button name="action" value="service_stop" class="btn secondary">Stop</button>
      <button name="action" value="service_restart">Restart</button>
      <button name="action" value="service_reload" class="btn secondary">Reload</button>
      <button name="action" value="service_enable">Enable on boot</button>
      <button name="action" value="service_disable" class="btn secondary">Disable on boot</button>
<!--      <button name="action" value="panic_on" class="btn danger">Panic ON</button> -->
<!--      <button name="action" value="panic_off" class="btn success">Panic OFF</button> -->
    </form>
  </div>

  <div class="panel">
    <h3>Zones</h3>
    <form method="post" class="inline">
      <label class="muted">Default zone</label>
      <select name="zone">
        <?php foreach ($zones as $z): ?>
          <option value="<?= h($z); ?>" <?= $z===$default_zone?'selected':''; ?>><?= h($z); ?></option>
        <?php endforeach; ?>
      </select>
      <button name="action" value="set_default_zone">Set Default</button>
    </form>
    <div class="hr"></div>
    <div class="row">
      <form method="post" class="inline">
        <input type="text" name="new_zone" placeholder="New zone name">
        <button name="action" value="create_zone">Create Zone</button>
      </form>
      <form method="post" class="inline">
        <select name="delete_zone">
          <?php foreach ($zones as $z): ?><option value="<?= h($z); ?>"><?= h($z); ?></option><?php endforeach; ?>
        </select>
        <button name="action" value="delete_zone" class="btn secondary">Delete Zone</button>
      </form>
    </div>
    <p class="note">Deleting the current default zone is blocked.</p>
  </div>
</div>

<div class="grid" style="margin-top:16px;">
  <div class="panel">
    <h3>Zone Browser</h3>
    <form method="get" class="inline">
      <label class="muted">Pick a zone</label>
      <select name="z" onchange="this.form.submit()">
        <?php $zpick = $_GET['z'] ?? ($default_zone ?: (is_array($zones)&&count($zones)?$zones[0]:''));
          foreach ($zones as $z){ $sel = ($z===$zpick)?'selected':''; echo '<option value="'.h($z).'" '.$sel.'>'.h($z).'</option>'; } ?>
      </select>
    </form>
    <?php if ($zpick):
      $zi = fw_json('zone-info-json '.escapeshellarg($zpick));
    ?>
      <div class="hr"></div>
      <div class="kvs">
        <div>Zone:</div><div class="badge"><?= h($zpick); ?></div>
        <div>Services:</div>
        <div class="list">
          <?php foreach (($zi['services'] ?? []) as $s){ if(!$s) continue; echo '<span class="chip">'.h($s).'</span>'; } ?>
          <?php if (!count($zi['services'] ?? [])) echo '<span class="muted">None</span>'; ?>
        </div>
        <div>Ports:</div>
        <div class="list">
          <?php foreach (($zi['ports'] ?? []) as $p){ if(!$p) continue; echo '<span class="chip">'.h($p).'</span>'; } ?>
          <?php if (!count($zi['ports'] ?? [])) echo '<span class="muted">None</span>'; ?>
        </div>
        <div>Sources:</div>
        <div class="list">
          <?php foreach (($zi['sources'] ?? []) as $s){ if(!$s) continue; echo '<span class="chip">'.h($s).'</span>'; } ?>
          <?php if (!count($zi['sources'] ?? [])) echo '<span class="muted">None</span>'; ?>
        </div>
        <div>Interfaces:</div>
        <div class="list">
          <?php foreach (($zi['interfaces'] ?? []) as $i) { if(!$i) continue; echo '<span class="chip">'.h($i).'</span>'; } ?>
          <?php if (!count($zi['interfaces'] ?? [])) echo '<span class="muted">None</span>'; ?>
        </div>
        <div>Rich Rules:</div>
        <div>
        <pre style="white-space:pre-wrap; line-height:1.9; background:#0b1426;border:1px solid #22314d;padding:12px;border-radius:8px;"><?php
  echo h(implode("\n\n", $zi['rich_rules'] ?? []));
?></pre>

        </div>
      </div>
    <?php endif; ?>
  </div>


  <div class="panel">
    <h3>Modify Selected Zone</h3>
    <div class="row">

<!--      <form method="post" class="inline">
        <input type="hidden" name="zone" value="<?= h($zpick); ?>">
        <label class="muted">Permanent?</label>
        <select name="permanent"><option value="no">Runtime</option><option value="yes">Permanent</option></select>
      </form>
-->

    </div>
    <div class="hr"></div>


    <div class="row">
      <form method="post" class="inline">
        <input type="hidden" name="zone" value="<?= h($zpick); ?>">
        <label class="muted">Service</label>
        <select name="service_select" style="min-width:170px;">
          <?php foreach (($services ?? []) as $svc) { echo '<option value="'.h($svc).'">'.h($svc).'</option>'; } ?>
        </select>
        <input type="text" name="service_custom" placeholder="or custom service name">
        <select name="permanent"><option value="no">Runtime</option><option value="yes">Permanent</option></select>
        <button name="action" value="add_service">Add Service</button>
        <button name="action" value="remove_service" class="btn secondary">Remove Service</button>
      </form>
    </div>

    <div class="hr"></div>
    <div class="hr"></div>


 <label class="muted">Port</label>
    <div class="row">
      <form method="post" class="inline">
        <input type="hidden" name="zone" value="<?= h($zpick); ?>">
        <input type="number" name="port" placeholder="Port (e.g. 443)" min="1" max="65535" style="width:160px;">
        <select name="proto" style="width:120px;"><option value="tcp">tcp</option><option value="udp">udp</option></select>
        <select name="permanent"><option value="no">Runtime</option><option value="yes">Permanent</option></select>
        <button name="action" value="add_port">Add Port</button>
        <button name="action" value="remove_port" class="btn secondary">Remove Port</button>
      </form>
    </div>


    <div class="hr"></div>
    <div class="hr"></div>

 <label class="muted">CIDr/IP</label>
    <div class="row">
      <form method="post" class="inline">
        <input type="hidden" name="zone" value="<?= h($zpick); ?>">
        <input type="text" name="source" placeholder="CIDR or IP (e.g. 203.0.113.0/24)" style="min-width:280px;">
        <select name="permanent"><option value="no">Runtime</option><option value="yes">Permanent</option></select>
        <button name="action" value="add_source">Add Source</button>
        <button name="action" value="remove_source" class="btn secondary">Remove Source</button>
      </form>
    </div>


    <div class="hr"></div>
    <div class="hr"></div>

 <label class="muted">Rich Rule</label>
    <div class="row">
      <form method="post" class="inline">
        <input type="hidden" name="zone" value="<?= h($zpick); ?>">
        <input type="text" name="rule" placeholder="Rich rule (e.g. rule family='ipv4' source address='1.2.3.4/32' port port='22' protocol='tcp' accept)" style="min-width:420px;">
        <select name="permanent"><option value="no">Runtime</option><option value="yes">Permanent</option></select>
        <button name="action" value="add_rich">Add Rich Rule</button>
        <button name="action" value="remove_rich" class="btn secondary">Remove Rich Rule</button>
      </form>
    </div>

    <div class="hr"></div>
    <div class="hr"></div>

    <div class="row">
      <form method="post" class="inline">
        <label class="muted">Interface</label>
        <select name="iface" style="min-width:170px;">
          <?php foreach (($ifaces ?? []) as $i) { echo '<option value="'.h($i).'">'.h($i).'</option>'; } ?>
        </select>
        <input type="hidden" name="zone" value="<?= h($zpick); ?>">
        <select name="permanent"><option value="no">Runtime</option><option value="yes">Permanent</option></select>
        <button name="action" value="add_iface">Bind Interface</button>
        <button name="action" value="remove_iface" class="btn secondary">Unbind Interface</button>
      </form>
    </div>

    <div class="hr"></div>
    <div class="hr"></div>

    <div class="row">
      <form method="post" class="inline">
        <label class="muted">ICMP type</label>
        <select name="icmp_type">
          <option value="echo-request">echo-request (ping)</option>
          <option value="echo-reply">echo-reply</option>
          <option value="destination-unreachable">destination-unreachable</option>
          <option value="time-exceeded">time-exceeded</option>
        </select>
        <button name="action" value="icmp_add">Add ICMP Block</button>
        <button name="action" value="icmp_remove" class="btn secondary">Remove ICMP Block</button>
      </form>
    </div>
  </div>
</div>

<?php if ($message): ?>
  <div class="wrap"><div class="panel" style="margin-top:16px;"><div class="success" style="display:inline-block;padding:8px 12px;border-radius:8px;"><?= h($message); ?></div></div></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="wrap"><div class="panel" style="margin-top:16px;"><div class="danger" style="display:inline-block;padding:8px 12px;border-radius:8px;"><?= h($error); ?></div></div></div>
<?php endif; ?>

<?php require __DIR__.'/footer.php'; ?>
