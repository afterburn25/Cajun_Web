<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function appointments_schema(string $prefix): array {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix)) throw new InvalidArgumentException('Invalid prefix.');
    $t=fn(string $name): string=>'`'.$prefix.$name.'`';$engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'appointment_settings'=>'CREATE TABLE '.$t('appointment_settings').' (id INT UNSIGNED PRIMARY KEY,revision INT UNSIGNED NOT NULL,timezone VARCHAR(80) NOT NULL,slot_minutes INT UNSIGNED NOT NULL,buffer_minutes INT UNSIGNED NOT NULL,notice_minutes INT UNSIGNED NOT NULL,enabled INT NOT NULL,rules LONGTEXT NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL)'.$engine,
        'appointment_settings_revisions'=>'CREATE TABLE '.$t('appointment_settings_revisions').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,revision INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE appointment_settings_revision(revision))'.$engine,
        'appointments'=>'CREATE TABLE '.$t('appointments').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,lead_id INT UNSIGNED NOT NULL,status VARCHAR(20) NOT NULL,starts_at BIGINT UNSIGNED NOT NULL,ends_at BIGINT UNSIGNED NOT NULL,timezone VARCHAR(80) NOT NULL,requested_at BIGINT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,updated_by INT UNSIGNED NOT NULL,updated_at DATETIME NOT NULL,created_at DATETIME NOT NULL,UNIQUE appointment_lead(lead_id),INDEX active_window(status,starts_at,ends_at),FOREIGN KEY (lead_id) REFERENCES '.$t('leads').'(id) ON DELETE CASCADE)'.$engine,
        'appointment_revisions'=>'CREATE TABLE '.$t('appointment_revisions').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,appointment_id INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL,snapshot LONGTEXT NOT NULL,action VARCHAR(30) NOT NULL,actor_id INT UNSIGNED NOT NULL,recorded_at DATETIME NOT NULL,UNIQUE appointment_revision(appointment_id,revision),FOREIGN KEY (appointment_id) REFERENCES '.$t('appointments').'(id) ON DELETE CASCADE)'.$engine,
    ];
}
