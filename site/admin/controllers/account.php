<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='account_save'){
        if(!consume_rate('account-change-'.$user['id'],10,900))throw new InvalidArgumentException('Please wait before trying another account change.');
        $pass=posted('new_password',72);if($pass!==''&&$pass!==posted('confirm_password',72))throw new InvalidArgumentException('The new passwords do not match.');
        $version=staff_account_save((int)$user['id'],posted('name',120),posted('email',190),posted('current_password',1000),$pass);$_SESSION['auth_version']=$version;session_regenerate_id(true);flash('Account saved. Other sessions and setup/reset links are invalidated.');redirect(admin_url('security'));
    }
