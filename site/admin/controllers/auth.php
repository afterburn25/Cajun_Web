<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='login'){
        $email=strtolower(posted('email',190));$password=posted('password',1000);
        $ipAllowed=consume_rate(ip_key('login'),15,900);$accountAllowed=consume_rate(hash_hmac('sha256','login-account|'.$email,config()['key']),8,900);
        if(!$ipAllowed||!$accountAllowed){usleep(200000);throw new InvalidArgumentException('Too many sign-in attempts. Wait 15 minutes before trying again.');}
        $u=one('SELECT * FROM '.table('users').' WHERE email=?',[$email]);
        $hash=($u['password_hash']??'')?:'$2y$12$k41gRE1TEYzHWgNYkc/yDuDTWYfPr6HvCaDvQfmUTDpAWPSgwOI6u';
        $verified=password_verify($password,$hash);
        if(!$u||$u['password_hash']===''||!$verified||!staff_profile($u)){log_event('auth.failed','Sign-in rejected');throw new InvalidArgumentException('The email or password is not correct.');}
        if(password_needs_rehash($hash,PASSWORD_DEFAULT)){query('UPDATE '.table('users').' SET password_hash=? WHERE id=? AND auth_version=?',[password_hash($password,PASSWORD_DEFAULT),$u['id'],$u['auth_version']]);}
        support_end_browser('admin-login');login_user($u);query('DELETE FROM '.table('rate_limits').' WHERE expires<?',[time()]);query('DELETE FROM '.table('resets').' WHERE expires<?',[time()]);log_event('auth.login');redirect(master_portal_login_destination($u));
    }
    if($action==='forgot'){
        $email=strtolower(posted('email',190));
        if(consume_rate(ip_key('forgot'),4,900)&&consume_rate(hash_hmac('sha256','reset|'.$email,config()['key']),3,3600)){
            $reset=setting('email_mode')!=='disabled'?staff_reset_token($email):null;
            if($reset){
                $token=$reset['token'];$hash=hash('sha256',$token);
                try{send_message($email,'Reset your CajunWebPro Studio password',"A password reset was requested for your account.\n\nOpen this link within one hour:\n".admin_url('reset',['token'=>$token])."\n\nIf you did not request this, you can ignore this message. Your password has not changed.");}catch(Throwable $e){report_error($e);query('DELETE FROM '.table('resets').' WHERE token_hash=?',[$hash]);}
            }
        }
        flash('If that account exists and email delivery is configured, a reset link will be sent. Check your spam folder.');redirect(admin_url('forgot'));
    }
    if($action==='reset'){
        if(!consume_rate(ip_key('reset-submit'),10,900))throw new InvalidArgumentException('Please wait before trying another reset.');
        $token=posted('token',64);$password=posted('password',72);validate_admin_password($password);if($password!==posted('confirm_password',72))throw new InvalidArgumentException('The passwords do not match.');
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('This reset link is not valid.');
        staff_reset($token,$password);
        unset($_SESSION['uid']);session_regenerate_id(true);flash('Your password has been changed. Sign in with your new password.');redirect(admin_url('login'));
    }
    if($action==='staff_activate'){
        if(!consume_rate(ip_key('staff-setup'),10,900))throw new InvalidArgumentException('Please wait before trying another setup code.');
        $password=posted('password',72);if($password!==posted('confirm_password',72))throw new InvalidArgumentException('The passwords do not match.');
        staff_activate(posted('token',64),$password);unset($_SESSION['uid'],$_SESSION['auth_version']);session_regenerate_id(true);flash('Account setup is complete. Sign in with your email and new password.');redirect(admin_url('login'));
    }
