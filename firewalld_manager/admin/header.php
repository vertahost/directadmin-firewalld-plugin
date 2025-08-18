<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function plugin_base(){ return dirname(__DIR__); }
function sudo_bin(){
  foreach (['/usr/bin/sudo','/bin/sudo','sudo'] as $p) { if ($p==='sudo' || is_executable($p)) return $p; }
  return 'sudo';
}
function fw_exec($args){
  $helper = plugin_base() . '/scripts/fwctl.sh';
  $sudo = sudo_bin();
  $cmd = escapeshellcmd($sudo) . ' -n ' . escapeshellarg($helper) . ' ' . $args . ' 2>&1';
  return shell_exec($cmd);
}
function fw_json($args){ $out = fw_exec($args); $j = json_decode($out, true); return $j ? $j : ['error'=>trim($out)]; }
?>
<div class="wrap"><div class="title">🔥 Firewalld Manager</div>
