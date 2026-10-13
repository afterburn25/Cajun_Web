<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
    if($action==='testimonial_save'){
        $recordId=(int)posted('id',12);$name=posted('name',120);$role=posted('role',160);$quote=posted('quote',3000);$rating=(int)posted('rating',1);$pub=isset($_POST['published'])?1:0;$consent=isset($_POST['consent'])?1:0;$order=(int)posted('sort_order',6);
        if($name===''||$quote===''||$rating<1||$rating>5)throw new InvalidArgumentException('Add the client name, genuine quotation and a rating from 1 to 5.');if($pub&&!$consent)throw new InvalidArgumentException('Confirm that you have permission to publish this genuine testimonial.');
        $values=[$name,$role,$quote,$rating,$pub,$consent,$order];if($recordId)query('UPDATE '.table('testimonials').' SET name=?,role=?,quote=?,rating=?,published=?,consent=?,sort_order=? WHERE id=?',[...$values,$recordId]);else query('INSERT INTO '.table('testimonials').' (name,role,quote,rating,published,consent,sort_order) VALUES (?,?,?,?,?,?,?)',$values);
        log_event('testimonial.save');flash('Testimonial saved.');redirect(admin_url('testimonials'));
    }
    if($action==='testimonial_delete'){query('DELETE FROM '.table('testimonials').' WHERE id=?',[(int)posted('id',12)]);log_event('testimonial.delete','Testimonial '.posted('id',12));flash('Testimonial deleted.');redirect(admin_url('testimonials'));}
