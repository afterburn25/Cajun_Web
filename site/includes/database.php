<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function db(): PDO {
    static $connection;
    if (!$connection) { $c=config()['db']; $dsn='mysql:host=' . $c['host'] . ';port=' . (int)$c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $connection = new PDO($dsn,$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]); }
    return $connection;
}

function table(string $name): string { if (!preg_match('/^[a-z_]+$/D', $name)) throw new LogicException('Invalid table'); return '`' . config()['db']['prefix'] . $name . '`'; }

function query(string $sql,array $params=[]): PDOStatement { $stmt=db()->prepare($sql); $stmt->execute($params); return $stmt; }

function all(string $sql,array $params=[]): array { return query($sql,$params)->fetchAll(); }

function one(string $sql,array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
