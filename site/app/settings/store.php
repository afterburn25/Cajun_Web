<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function setting(string $key,string $default=''): string { if(!isset($GLOBALS['cwp_settings'])) $GLOBALS['cwp_settings']=array_column(all('SELECT name,value FROM ' . table('settings')),'value','name'); return (string)($GLOBALS['cwp_settings'][$key] ?? $default); }

function save_setting(string $key,string $value): void { query('INSERT INTO ' . table('settings') . ' (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); unset($GLOBALS['cwp_settings']); }

function log_event(string $action,string $detail=''): void { try { query('INSERT INTO ' . table('audit') . ' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[(int)($_SESSION['uid'] ?? 0),substr($action,0,80),substr($detail,0,400),gmdate('Y-m-d H:i:s')]); } catch(Throwable $e) { error_log('CWP audit write failed'); } }

function report_error(Throwable $e): string { $ref=strtoupper(bin2hex(random_bytes(4))); error_log('CWP ['.$ref.'] '.get_class($e).' code='.$e->getCode().' in '.basename($e->getFile()).':'.$e->getLine()); return $ref; }
