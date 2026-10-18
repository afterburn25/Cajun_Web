<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function icon(string $name, string $class=''): string {
    $paths=[
        'monitor'=>'<rect x="3" y="4" width="26" height="18" rx="2"/><path d="M10 28h12M16 22v6"/>',
        'code'=>'<path d="m10 8-8 8 8 8m12-16 8 8-8 8M19 4l-6 24"/>',
        'bag'=>'<path d="M6 10h20l2 19H4L6 10Z"/><path d="M11 11V7a5 5 0 0 1 10 0v4"/>',
        'diamond'=>'<path d="m2 11 6-7h16l6 7-14 18L2 11Zm0 0h28M8 4l8 25 8-25M8 4l8 7 8-7"/>',
        'gear'=>'<path d="m12 3 1-2h6l1 4 4 2 4-1 3 5-3 3v4l3 3-3 5-4-1-4 2-1 4h-6l-1-4-4-2-4 1-3-5 3-3v-4l-3-3 3-5 4 1 4-2Z"/><circle cx="16" cy="16" r="5"/>',
        'growth'=>'<path d="M5 28V19h5v9M14 28V12h5v16M23 28V4h5v24M3 10l8-5 7 1 9-5"/>',
        'arrow'=>'<path d="M5 16h22m-8-8 8 8-8 8"/>',
        'check'=>'<path d="m6 16 7 7L27 8"/>',
        'shield'=>'<path d="M16 2 29 7v9c0 7-13 14-13 14S3 23 3 16V7L16 2Z"/><path d="m9 15 5 5 9-9"/>',
        'globe'=>'<circle cx="16" cy="16" r="13"/><ellipse cx="16" cy="16" rx="6" ry="13"/><path d="M3 16h26M5 9h22M5 23h22"/>',
        'layers'=>'<path d="m16 3 14 8-14 8L2 11l14-8ZM3 18l13 8 13-8M3 24l13 7 13-7"/>',
        'mail'=>'<rect x="3" y="6" width="26" height="21" rx="3"/><path d="m3 7 13 11L29 7"/>',
        'dashboard'=>'<rect x="3" y="3" width="11" height="11" rx="2"/><rect x="18" y="3" width="11" height="7" rx="2"/><rect x="3" y="18" width="11" height="11" rx="2"/><rect x="18" y="14" width="11" height="15" rx="2"/>',
        'user'=>'<circle cx="16" cy="10" r="6"/><path d="M4 29v-3a12 10 0 0 1 24 0v3"/>',
        'quote'=>'<path d="M4 17h8v10H2V16C2 9 5 5 12 4M21 17h8v10H19V16c0-7 3-11 10-12"/>',
    ];
    return '<svg class="icon '.esc($class).'" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['diamond']).'</svg>';
}
function site_head(string $title, string $active='home', string $description=''): void {
    $description = $description ?: setting('meta_description');
    $brand = setting('brand_name', 'CajunWebPro');
    $canonical = $active === 'project' ? page_url('project', ['slug'=>$_GET['slug'] ?? '']) : page_url($active);
    if (setting('site_published') !== '1') header('X-Robots-Tag: noindex, nofollow');
    ?>
<!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=esc($title)?> · <?=esc($brand)?></title>
<meta name="description" content="<?=esc($description)?>">
<link rel="canonical" href="<?=esc($canonical)?>">
<meta property="og:title" content="<?=esc($title.' · '.$brand)?>">
<meta property="og:description" content="<?=esc($description)?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?=esc($canonical)?>">
<meta property="og:image" content="<?=esc(asset('logo.png'))?>">
<meta name="theme-color" content="#030c1a">
<link rel="icon" type="image/png" sizes="32x32" href="<?=esc(asset('favicon.png'))?>">
<link rel="stylesheet" href="<?=esc(asset('site.css'))?>">
<link rel="stylesheet" href="<?=esc(asset('premium.css'))?>">
<?php if($active==='portal'): ?><link rel="stylesheet" href="<?=esc(asset('tickets.css'))?>"><?php endif; ?>
<?php if ($active === 'home'): ?><link rel="preload" as="image" href="<?=esc(asset('hero-detail.webp'))?>"><?php endif; ?>
<link rel="stylesheet" href="<?=esc(asset('growth.css'))?>"><script defer src="<?=esc(asset('growth.js'))?>"></script><script defer src="<?=esc(asset('site.js'))?>"></script><script defer src="<?=esc(asset('activity.js'))?>"></script><link rel="stylesheet" href="<?=esc(asset('mail-notices.css'))?>"><script defer src="<?=esc(asset('mail-notices.js'))?>"></script>
<script type="application/ld+json" nonce="<?=nonce()?>"><?=json_encode(['@context'=>'https://schema.org','@type'=>'Organization','name'=>$brand,'url'=>site_url(),'logo'=>asset('logo.png')],JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
</head>
<body class="cwp-site <?= $active === 'home' ? 'is-home' : 'is-inner' ?>" data-release="<?=esc(CWP_VERSION)?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="reading-progress" aria-hidden="true"><i></i></div>
<header class="site-header">
    <div class="container nav-inner">
        <a class="brand" href="<?=esc(page_url())?>" aria-label="CajunWebPro home"><img src="<?=esc(asset('logo.png'))?>" width="1080" height="302" alt="CajunWebPro — Web Design & Development"></a>
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="site-nav" aria-expanded="false"><span></span><span></span><span></span></button>
        <nav id="site-nav" aria-label="Main navigation">
        <?php foreach(['home'=>'Home','about'=>'About','hosting'=>'Hosting','services'=>'Web Design','products'=>'Products','work'=>'Portfolio','process'=>'Process','contact'=>'Contact'] as $key=>$label): $selected=$active===$key||($active==='project'&&$key==='work'); ?>
            <a class="<?=$selected?'active':''?>" href="<?=esc(page_url($key))?>" <?=$selected?'aria-current="page"':''?>><?=$label?></a>
        <?php endforeach; ?>
        </nav>
        <?php if(!array_key_exists('support',$_GET)&&(!function_exists('support_current')||!support_current())): ?><span hidden data-mail-notice-bell data-mail-notice-endpoint="<?=esc(site_url('api/mail-notices.php'))?>"></span><?php endif; ?>
        <a class="btn outline nav-cta" href="<?=esc(page_url('contact'))?>">Start a Project <?=icon('arrow')?></a>
    </div>
</header><nav class="growth-subnav" aria-label="Explore our services"><a href="<?=esc(page_url('why-custom'))?>">Why custom</a><a href="<?=esc(page_url('compare-wix'))?>">Wix comparison</a><a href="<?=esc(page_url('compare-squarespace'))?>">Squarespace comparison</a><a href="<?=esc(page_url('packages'))?>">Packages</a><a href="<?=esc(page_url('faq'))?>">Questions</a><a href="<?=esc(page_url('estimate'))?>">Plan a project</a><a href="<?=esc(page_url('consultation'))?>">Consultation</a><a href="<?=esc(site_url('portal/index.php'))?>">Client Portal</a></nav>
<?php if(!function_exists('support_current')||!support_current()):?><div class="container activity-strip" hidden data-activity-bell data-activity-endpoint="<?=esc(site_url('api/activity.php').'?audience='.($active==='portal'?'client':'admin'))?>"></div><?php endif;?>
<main id="main">
<?php
}
function contact_form(string $class='', ?array $availableServices=null): void {
    if(!isset($_SESSION['inquiry_tokens']))$_SESSION['inquiry_tokens']=[];
    $_SESSION['inquiry_tokens']=array_filter($_SESSION['inquiry_tokens'],fn($v)=>$v>time()-7200);
    if(count($_SESSION['inquiry_tokens'])>=12)array_shift($_SESSION['inquiry_tokens']);
    $token=bin2hex(random_bytes(24));$_SESSION['inquiry_tokens'][$token]=time();
    $services=$availableServices ?? all('SELECT title FROM '.table('services').' WHERE active=1 ORDER BY sort_order,id');
    ?><form class="inquiry-form <?=esc($class)?>" action="<?=esc(site_url('api/index.php'))?>" method="post" data-inquiry><?=csrf_field()?><input type="hidden" name="action" value="inquiry"><input type="hidden" name="submission_token" value="<?=esc($token)?>"><div class="honey-field" aria-hidden="true"><label>Leave this field empty<input name="website_address" tabindex="-1" autocomplete="off"></label></div><div class="field-grid"><label>Your name <span>*</span><input name="name" required maxlength="120" autocomplete="name" placeholder="Your name"></label><label>Email address <span>*</span><input name="email" type="email" required maxlength="190" autocomplete="email" placeholder="you@company.com"></label></div><div class="field-grid"><label>Business name<input name="company" maxlength="160" autocomplete="organization" placeholder="Your business"></label><label>Phone <span class="optional">optional</span><input name="phone" maxlength="80" autocomplete="tel" type="tel" placeholder="Your phone number"></label></div><div class="field-grid three"><label>What do you need? <span>*</span><select name="service" required><option value="">Select a service</option><option>Not sure yet</option><option>Website redesign</option><option>Custom web application</option><option>Multiple services</option><?php foreach($services as $s):?><option><?=esc($s['title'])?></option><?php endforeach;?></select></label><label>Project budget <span>*</span><select name="budget" required><option value="">Select a range</option><option>Under $2,500</option><option>$2,500–$5,000</option><option>$5,000–$10,000</option><option>$10,000+</option><option>Help me estimate</option></select></label><label>Ideal timeline<select name="timeline"><option>Flexible / exploring</option><option>Within a month</option><option>1–3 months</option><option>3+ months</option></select></label></div><label>Tell us about your project <span>*</span><textarea name="message" rows="5" minlength="20" maxlength="6000" required placeholder="What are you building, and what would success look like?"></textarea></label><label class="check consent"><input type="checkbox" name="consent" required value="1"><span>I agree to be contacted about this inquiry and have read the <a href="<?=esc(page_url('privacy'))?>">privacy notice</a>.</span></label><div class="form-actions"><button class="btn gold" type="submit">Let's Build Something Great <?=icon('arrow')?></button><small>No obligation. Just a conversation.</small></div><div class="form-result" role="status" tabindex="-1" hidden></div></form><?php
}
/** Published database project cards. Demo labels are never hidden by the theme. */
function project_card(array $p, bool $visible=true): void {
    $url=page_url('project',['slug'=>$p['slug']]);
    ?>
<article class="project-card glow-card" data-category="<?=esc($p['category'])?>" <?=$visible?'':'hidden'?>>
    <a class="project-art" href="<?=esc($url)?>" aria-label="Explore <?=esc($p['title'])?>">
        <img src="<?=esc(image_url($p))?>" alt="<?=esc($p['title'])?> website design" loading="lazy" decoding="async" width="800" height="450">
        <?php if($p['is_demo']): ?><span class="project-badge">DESIGN CONCEPT</span><?php endif; ?>
        <span class="project-open"><?=icon('arrow')?></span>
    </a>
    <a href="<?=esc($url)?>"><h3><?=esc($p['title'])?></h3></a>
    <div class="project-meta"><?=esc($p['category'])?> <span>/</span> WEB DESIGN & DEVELOPMENT</div>
</article>
<?php }
function service_card(array $s): void { ?>
<a class="service-card glow-card" href="<?=esc(page_url('services',['service'=>$s['slug']]))?>">
    <span class="service-icon"><?=icon($s['icon'])?></span>
    <div class="service-copy"><h3><?=esc($s['title'])?></h3><p><?=esc($s['summary'])?></p><span class="circle-arrow" aria-hidden="true">↗</span></div>
</a>
<?php }
function cta_section(): void { ?>
<section class="bayou-cta" aria-labelledby="cta-title">
    <div class="cta-scene" aria-hidden="true"></div>
    <div class="container cta-layout">
        <div class="cta-local"><span>LOUISIANA ROOTS.</span><strong>Limitless<br>possibilities.</strong><i></i></div>
        <div class="cta-inner">
            <span class="eyebrow">LET'S BUILD WHAT'S NEXT</span>
            <h2 id="cta-title"><?=esc(setting('cta_title'))?></h2>
            <p><?=esc(setting('cta_text'))?></p>
            <div class="button-row"><a class="btn gold" href="<?=esc(page_url('contact'))?>">Start a Project <?=icon('arrow')?></a><a class="btn outline" href="<?=esc(page_url('work'))?>">View Our Work <?=icon('arrow')?></a></div>
        </div>
        <div class="cta-emblem" aria-hidden="true"><img src="<?=esc(asset('emblem.png'))?>" width="250" height="300" alt="" loading="lazy"><span>IDEAS. DESIGN. IMPACT.</span></div>
    </div>
</section>
<?php }
function site_foot(bool $cta=true): void {
    if($cta) cta_section(); ?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div><a class="brand" href="<?=esc(page_url())?>"><img src="<?=esc(asset('logo.png'))?>" alt="CajunWebPro" width="1080" height="302" loading="lazy"></a><p><?=esc(setting('footer_text'))?></p></div>
        <div><h3>Explore</h3><a href="<?=esc(page_url('work'))?>">Our work</a><a href="<?=esc(page_url('services'))?>">Our capabilities</a><a href="<?=esc(page_url('process'))?>">Our process</a></div>
        <div><h3>Let's talk</h3>
        <?php if(setting('contact_email')!==''): ?><a href="mailto:<?=esc(setting('contact_email'))?>"><?=esc(setting('contact_email'))?></a><?php endif; ?>
        <?php if(setting('phone')!==''): ?><a href="tel:<?=esc(preg_replace('/[^+0-9]/','',setting('phone')))?>"><?=esc(setting('phone'))?></a><?php endif; ?>
        <a href="<?=esc(page_url('contact'))?>">Start your project ↗</a><span><?=esc(setting('location'))?></span>
        </div>
    </div>
    <div class="container footer-bottom">
        <small>© <?=gmdate('Y')?> <?=esc(setting('brand_name'))?>. Built with intention.</small>
        <div>
            <?php foreach(['instagram'=>'Instagram','facebook'=>'Facebook','linkedin'=>'LinkedIn'] as $key=>$label): if(setting($key.'_url')!==''): ?><a href="<?=esc(setting($key.'_url'))?>" target="_blank" rel="noopener noreferrer"><?=$label?></a><?php endif; endforeach; ?>
            <button class="motion-toggle" type="button" aria-pressed="false" hidden>Motion on</button>
            <a href="<?=esc(page_url('privacy'))?>">Privacy</a><a href="<?=esc(admin_url())?>">Studio access</a>
        </div>
    </div>
</footer>
<button class="back-top" type="button" aria-label="Back to top">↑</button>
</body>
</html>
<?php }
