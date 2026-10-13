<?php
/** 1.1.0 public growth workflows. Existing settings/leads schema is retained. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;
function growth_routes(): array { return ['why-custom','compare-wix','compare-squarespace','switch','packages','faq','industries','estimate','website-review','consultation']; }
function growth_types(): array { return ['business'=>'Business website','redesign'=>'Website redesign','store'=>'Online store','custom'=>'Custom application']; }
function growth_options(): array { return ['copy'=>'Copywriting','brand'=>'Brand identity','migration'=>'Content migration','booking'=>'Booking integration','accounts'=>'Member accounts','integration'=>'Custom integration']; }
function gtext(array $a,string $k,int $limit=500): string { $v=$a[$k]??'';if(!is_string($v)||strlen($v)>$limit||str_contains($v,"\0"))throw new InvalidArgumentException('Check '.str_replace('_',' ',$k).'.');return trim($v); }
function gint(array $a,string $k,int $min,int $max,int $default=0): int { $v=$a[$k]??$default;if((!is_string($v)&&!is_int($v))||!preg_match('/^[0-9]{1,7}$/D',(string)$v)||(int)$v<$min||(int)$v>$max)throw new InvalidArgumentException('Check '.str_replace('_',' ',$k).' ('.$min.'–'.$max.').');return (int)$v; }
function gjson(string $key,array $fallback=[]): array {try{$v=json_decode(setting($key),true,32,JSON_THROW_ON_ERROR);return is_array($v)?$v:$fallback;}catch(JsonException){return $fallback;} }
function gcents(string $s): ?int { if($s==='')return null;if(!preg_match('/^(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,2}))?$/D',$s,$m))throw new InvalidArgumentException('Enter prices without commas or currency symbols, up to 999999.99.');return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0'); }
function gmoney(?int $n): string {return $n===null?'':number_format($n/100,2,'.','');}
function grange($p): array {if(!is_array($p)||!array_is_list($p)||count($p)!==2)throw new InvalidArgumentException('Invalid price range.');foreach($p as $n)if($n!==null&&(!is_int($n)||$n<0||$n>99999999))throw new InvalidArgumentException('Invalid price.');if(($p[0]===null)!==($p[1]===null)||($p[0]!==null&&$p[0]>$p[1]))throw new InvalidArgumentException('Complete both ends of each range, minimum first.');return $p;}
function growth_rules(): array {return gjson('growth_prices');}
function growth_estimate(array $a,array $r): array {
 $type=gtext($a,'project_type',20);if(!isset(growth_types()[$type]))throw new InvalidArgumentException('Choose a project type.');
 $pages=gint($a,'pages',1,100,5);$products=$type==='store'?gint($a,'products',0,10000):0;$opts=$a['options']??[];
 if(!is_array($opts)||!array_is_list($opts)||count($opts)>6)throw new InvalidArgumentException('Invalid project options.');foreach($opts as $o)if(!is_string($o)||!isset(growth_options()[$o]))throw new InvalidArgumentException('Invalid project option.');$opts=array_values(array_unique($opts));
 $out=['mode'=>'consultation','label'=>'Let’s scope your project together','low'=>null,'high'=>null,'type'=>$type,'pages'=>$pages,'products'=>$products,'options'=>$opts,'note'=>'An estimate is not a final quote. Hosting, third-party fees, taxes and ongoing care are scoped separately.'];
 if(($r['enabled']??false)!==true||$type==='custom'||in_array('integration',$opts,true))return $out;
 $keys=[$type=>1];if($pages>5)$keys['page']=$pages-5;if($products>0)$keys['product']=$products;foreach($opts as $o)$keys[$o]=1;
 $lo=$hi=0;foreach($keys as $k=>$qty){$p=grange($r[$k]??[null,null]);if($p[0]===null)return $out;$lo+=$p[0]*$qty;$hi+=$p[1]*$qty;}
 if($hi>99999999)return $out;$out['mode']='range';$out['low']=$lo;$out['high']=$hi;$out['label']='$'.number_format($lo/100,2).' – $'.number_format($hi/100,2).' USD';return $out;
}
function growth_faq_defaults(): array {return [
 ['Why hire CajunWebPro rather than build it myself?','You bring the business knowledge. We plan the structure, design the experience and develop the agreed features. You get a scoped, done-for-you service—not just access to an editor.'],
 ['Is custom development always the right choice?','No. A hosted builder can suit a simple website, an early-stage budget or someone who enjoys building their own pages. Custom work becomes valuable when your brand, workflows or migration need more individual attention.'],
 ['Can I keep my domain and business email?','Usually the new website can use your existing domain. We review domain access, DNS and email before changes. Keep existing subscriptions active until the replacement is tested and the changeover is agreed.'],
 ['What will a website cost?','Pages, content, integrations and complexity shape the quote. Use the project planner to send your requirements. Online ranges appear only after the studio configures them; the written proposal defines the final scope and price.'],
 ['How long will it take?','We agree milestones after discovery. Content readiness, review rounds and integrations influence timing. A requested date is not a delivery commitment until it is included in the agreed plan.'],
 ['Will I be able to update content?','Administration access and training are included according to your proposal. We discuss the content you need to manage before choosing the editing tools. New features or code changes are scoped separately.'],
 ['Who owns the finished website?','The proposal defines source-code handover, content ownership, licenses and payment conditions. Third-party assets and services retain their own license terms. We discuss access and handover before development begins.'],
 ['Can you maintain the website after launch?','Optional care can include agreed updates, content assistance and improvements. Scope, response expectations, backup responsibilities and pricing are set in writing. Sending an inquiry does not start a subscription.'],
 ['Will a redesign guarantee higher rankings or more sales?','No. We can address the agreed design and technical requirements, plan redirects and measure outcomes. Search rankings and business results also depend on content, competition, promotion and other factors.'],
 ];}
function growth_package_defaults(): array {return [
 ['Business presence','A memorable first impression, with a clear next step.','Page strategy and responsive design\nService and contact journeys\nAgreed content editing\nLaunch testing and handover'],
 ['Commerce & bookings','Make product discovery and customer action feel natural.','Store or booking journey planning\nAgreed provider integrations\nProduct or service presentation\nCheckout and launch coordination'],
 ['Custom systems','Build around how your business actually operates.','Workflow discovery\nInterface and data planning\nAgreed customer accounts or integrations\nPhased development and review']
 ];}
function growth_sources(): array {return [
 ['Wix: hosting and exporting a site','https://support.wix.com/en/article/exporting-or-embedding-your-wix-site-elsewhere'],
 ['Wix Studio: design and development tools','https://www.wix.com/studio'],
 ['Squarespace: adding custom code','https://support.squarespace.com/hc/en-us/articles/205815928-Add-custom-code-to-your-site'],
 ['Squarespace: exporting site content','https://support.squarespace.com/hc/en-us/articles/206566687-Export-your-site-content']
 ];}
function growth_token(): string {$_SESSION['growth_tokens']=array_filter($_SESSION['growth_tokens']??[],fn($t)=>is_int($t)&&$t>time()-7200);if(count($_SESSION['growth_tokens'])>=20)array_shift($_SESSION['growth_tokens']);$t=bin2hex(random_bytes(24));$_SESSION['growth_tokens'][$t]=time();return $t;}
function growth_submit(array $a): array {
 check_csrf();$token=gtext($a,'submission_token',48);$start=$_SESSION['growth_tokens'][$token]??0;
 if(!preg_match('/^[a-f0-9]{48}$/D',$token)||!$start||$start<time()-7200)throw new InvalidArgumentException('This form expired. Reload before sending.');
 if($start>time()-2||gtext($a,'website_address',500)!=='')throw new InvalidArgumentException('Wait a moment before sending.');
 $key=hash('sha256','growth|'.$token);$old=one('SELECT reference FROM '.table('leads').' WHERE request_key=?',[$key]);if($old)return ['reference'=>$old['reference'],'message'=>'Your request is already saved.'];
 $kind=gtext($a,'kind',30);if(!in_array($kind,['estimate','website-review','consultation'],true))throw new InvalidArgumentException('Invalid request type.');
 $d=['name'=>gtext($a,'name',120),'email'=>strtolower(gtext($a,'email',190)),'company'=>gtext($a,'company',160),'phone'=>gtext($a,'phone',80),'timeline'=>gtext($a,'timeline',100),'message'=>gtext($a,'message',5000)];
 if($d['name']===''||!filter_var($d['email'],FILTER_VALIDATE_EMAIL)||strlen($d['message'])<20||gtext($a,'consent',1)!=='1')throw new InvalidArgumentException('Add your name, valid email, at least 20 characters about the project, and accept the privacy notice.');
 $d['budget']='Tailored quote';$detail='';
 if($kind==='estimate'){$e=growth_estimate($a,growth_rules());$d['service']='Estimate: '.growth_types()[$e['type']];$d['budget']=$e['mode']==='range'?$e['label']:'Tailored quote';$detail='Pages: '.$e['pages'].'; products: '.$e['products'].'; options: '.implode(', ',$e['options'])."\nServer estimate: ".$e['label']."\n".$e['note'];}
 elseif($kind==='website-review'){$url=gtext($a,'current_url',500);if($url==='')throw new InvalidArgumentException('Add the current website address.');safe_url($url);$d['service']='Website review';$detail='Website: '.$url."\nHuman-reviewed request; no automated audit performed.";}
 else{$tz=gtext($a,'timezone',80);if(!in_array($tz,DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Choose a time zone.');$date=gtext($a,'preferred_date',10);$zone=new DateTimeZone($tz);$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$zone);$today=new DateTimeImmutable('today',$zone);if(!$dt||$dt->format('Y-m-d')!==$date||$dt<$today||$dt>$today->modify('+365 days'))throw new InvalidArgumentException('Choose a preferred date within the next year.');$period=gtext($a,'period',20);if(!in_array($period,['Morning','Afternoon','Evening','Flexible'],true))throw new InvalidArgumentException('Choose a preferred time.');$d['service']='Consultation request';$detail='Requested: '.$date.' '.$period.' ('.$tz.").\nAppointment is not confirmed until the studio replies.";}
 if(!consume_rate(ip_key('growth-hour'),5,3600)||!consume_rate(ip_key('growth-day'),12,86400))throw new RuntimeException('Please wait before sending another request.');
 $d['reference']='CWP-'.strtoupper(bin2hex(random_bytes(5)));$d['message']=$detail."\n\nBUSINESS GOALS\n".$d['message'];
 try{query('INSERT INTO '.table('leads')." (reference,request_key,name,email,company,phone,service,budget,timeline,message,consent,ip_hash,status,notes,notification_status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,'new','','pending',?)",[$d['reference'],$key,$d['name'],$d['email'],$d['company'],$d['phone'],$d['service'],$d['budget'],$d['timeline'],$d['message'],ip_key('lead'),gmdate('Y-m-d H:i:s')]);$id=(int)db()->lastInsertId();}catch(PDOException $e){$old=one('SELECT reference FROM '.table('leads').' WHERE request_key=?',[$key]);if($old)return ['reference'=>$old['reference'],'message'=>'Your request is saved.'];throw $e;}
 require_once CWP_CODE_ROOT.'/app/mail/transport.php';try{$n=notify_lead($d);}catch(Throwable $e){report_error($e);$n='failed';}try{query('UPDATE '.table('leads').' SET notification_status=? WHERE id=?',[$n,$id]);}catch(Throwable $e){report_error($e);}
 return ['reference'=>$d['reference'],'message'=>$kind==='consultation'?'Your consultation request is saved. We will reply to arrange a time; this is not a confirmed booking.':'Your brief is saved for review. No payment or subscription has been created.'];
}
require_once CWP_CODE_ROOT.'/includes/layout/growth_home.php';
function growth_rows(string $name,array $fallback,int $width): array {
 $value=gjson($name);if(!array_is_list($value)||count($value)<1||count($value)>12)return $fallback;
 foreach($value as $row){if(!is_array($row)||!array_is_list($row)||count($row)!==$width)return $fallback;foreach($row as $text)if(!is_string($text)||strlen($text)>6000)return $fallback;}return $value;
}
function growth_faq(): array {return growth_rows('growth_faq',growth_faq_defaults(),2);}
function growth_packages(): array {return growth_rows('growth_packages',growth_package_defaults(),3);}
function growth_preview_url(string $id): string {return preg_match('/^[a-f0-9]{32}$/D',$id)&&one('SELECT id FROM '.table('media').' WHERE id=?',[$id])?site_url('media/index.php?id='.$id):'';}
function growth_media_used(string $id): bool {foreach(all('SELECT value FROM '.table('settings')." WHERE name LIKE 'growth_portfolio_%'") as $v){$j=json_decode($v['value'],true);if(is_array($j))foreach(['desktop','mobile','before'] as $key)if(($j[$key]??'')===$id)return true;}return false;}
require_once CWP_CODE_ROOT.'/includes/layout/growth_portfolio_view.php';
