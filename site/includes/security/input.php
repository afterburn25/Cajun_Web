<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function esc(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function posted(string $key, int $limit = 5000): string { $v = $_POST[$key] ?? ''; if (!is_string($v) || strlen($v) > $limit) throw new InvalidArgumentException('Please check the length and format of ' . str_replace('_',' ',$key) . '.'); return (str_contains($key,'password') || $key==='owner_confirm') ? $v : trim($v); }

function safe_url(string $url, bool $httpsOnly=false): string { if($url==='') return ''; $p=parse_url($url); if(!filter_var($url,FILTER_VALIDATE_URL) || !$p || !in_array(strtolower($p['scheme']??''),$httpsOnly?['https']:['https','http'],true) || isset($p['user']) || isset($p['pass']) || preg_match('/[\r\n]/',$url)) throw new InvalidArgumentException('Enter a valid ' . ($httpsOnly?'HTTPS':'HTTP or HTTPS') . ' URL.'); return $url; }

function slugify(string $text): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '', '-'); }

function validate_password(string $password): void { if(str_contains($password,"\0") || strlen($password)<14 || strlen($password)>72) throw new InvalidArgumentException('Use a password between 14 and 72 bytes. A long, unique passphrase works well.'); }

function format_date(?string $date): string { return $date ? gmdate('M j, Y',strtotime($date)) : '—'; }
