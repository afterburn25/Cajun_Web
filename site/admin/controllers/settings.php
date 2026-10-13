<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='settings_save'){
        $keys=['hero_line1'=>120,'hero_line2'=>120,'hero_text'=>1500,'eyebrow'=>180,'meta_description'=>350,'about_title'=>200,'about_text'=>16000,'contact_email'=>190,'phone'=>80,'location'=>200,'cta_title'=>160,'cta_text'=>1000,'footer_text'=>1000,'instagram_url'=>500,'facebook_url'=>500,'linkedin_url'=>500,'privacy_text'=>20000];$values=[];
        foreach($keys as $k=>$limit){$value=posted($k,$limit);if(str_ends_with($k,'_url'))safe_url($value);if($k==='contact_email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid public business email.');$values[$k]=$value;}
        foreach(['hero_line1','hero_line2','hero_text','meta_description','privacy_text'] as $k)if($values[$k]==='')throw new InvalidArgumentException('The hero text, search description and privacy notice cannot be blank.');
        db()->beginTransaction();try{foreach($values as $k=>$value)save_setting($k,$value);save_setting('site_published',isset($_POST['site_published'])?'1':'0');db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        log_event('settings.save');flash('Website settings saved.');redirect(admin_url('settings'));
    }
