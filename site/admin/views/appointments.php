<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
$filter=is_string($_GET['status']??null)?$_GET['status']:'active';if (!in_array($filter,['active','all','requested','confirmed','declined','cancelled'],true))$filter='active';
$page=max(1,min((int)($_GET['page']??1),100000));$params=[];$where='';
if ($filter==='active') {$where=" WHERE a.status IN ('requested','confirmed') AND a.ends_at>?";$params[] = time();}elseif ($filter!=='all') {$where=' WHERE a.status=?';$params[]=$filter;}
$rows=all('SELECT a.*,l.name,l.email,l.reference FROM '.table('appointments').' a JOIN '.table('leads').' l ON l.id=a.lead_id'.$where.' ORDER BY a.starts_at,a.id LIMIT 26 OFFSET '.(($page-1)*25),$params);$settings=appointments_settings();
?>
<p class="notice"><?=(int)$settings['enabled']?'Public slot requests are enabled.':'Public slot requests are disabled; visitors can send time preferences.'?> Pending requests hold their time until a staff decision. Contact prospects separately about all appointment decisions.</p>
<form method="get"><input type="hidden" name="view" value="consultations"><?php admin_select('status','Show appointments',['active'=>'Upcoming active','all'=>'All appointments','requested'=>'Pending requests','confirmed'=>'Confirmed','declined'=>'Declined','cancelled'=>'Cancelled'],$filter); ?><button class="btn outline">Show</button></form>
<?php if (!$rows): ?><p>No appointments match this view. Open a Consultation request in Inquiries to schedule an older request.</p><?php endif; ?>
<?php foreach (array_slice($rows,0,25) as $row): ?><article class="workspace-card"><h2><a href="<?=esc(admin_url('consultation',['id'=>$row['lead_id']]))?>"><?=esc($row['name'])?></a></h2><p><?=esc(appointments_label((int)$row['starts_at'],(int)$row['ends_at'],$row['timezone']))?></p><p><?=esc(ucfirst($row['status']))?> · <?=esc($row['email'])?> · Inquiry <?=esc($row['reference'])?></p></article><?php endforeach; ?>
<nav aria-label="Appointment pages"><?php if ($page>1): ?><a class="btn outline" href="<?=esc(admin_url('consultations',['page'=>$page-1,'status'=>$filter]))?>">Previous page</a><?php endif; ?><?php if (count($rows)>25): ?><a class="btn outline" href="<?=esc(admin_url('consultations',['page'=>$page+1,'status'=>$filter]))?>">Next page</a><?php endif; ?></nav>
