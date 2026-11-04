<?php
/** Approved Concept 01: server-rendered homepage. Data is supplied by index.php. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
?>
<section class="hero" aria-labelledby="hero-title">
    <div class="hero-atmosphere" aria-hidden="true"></div>
    <div class="hero-visual" aria-hidden="true" data-parallax>
        <img src="<?=esc(asset('hero-detail.webp'))?>" width="770" height="459" alt="" fetchpriority="high" decoding="async">
    </div>
    <div class="hero-devices" aria-hidden="true" data-parallax-near>
        <div class="hero-device hero-device-desktop"><i></i><i></i><i></i></div>
        <div class="hero-device hero-device-mobile"><i></i><i></i></div>
    </div>
    <div class="container hero-inner">
        <div class="hero-copy">
            <div class="eyebrow hero-eyebrow">STRATEGY <b>×</b> DESIGN <b>×</b> DEVELOPMENT <b>×</b> GROWTH</div>
            <h1 id="hero-title"><span class="silver-text"><?=esc(setting('hero_line1','Websites Built'))?></span><br><span class="gold-text"><?=esc(setting('hero_line2','to Dominate'))?></span></h1>
            <p><?=esc(setting('hero_text'))?></p>
            <div class="button-row">
                <a class="btn gold" href="<?=esc(page_url('work'))?>">View Our Work <?=icon('arrow')?></a>
                <a class="btn outline" href="<?=esc(page_url('contact'))?>">Start a Project <?=icon('arrow')?></a>
            </div>
            <div class="hero-values">
                <div><?=icon('diamond')?><span>PREMIUM<br>DESIGN</span></div>
                <div><?=icon('code')?><span>CUSTOM<br>DEVELOPMENT</span></div>
                <div><?=icon('growth')?><span>BUILT FOR<br>YOUR BUSINESS</span></div>
            </div>
        </div>
    </div>
    <div class="hero-bottom"><span>LOUISIANA ROOTS. DIGITAL AMBITION.</span><a href="#capabilities">EXPLORE THE POSSIBILITIES <span>↓</span></a></div>
</section>
<section class="signature-strip" aria-label="Our approach">
    <svg class="signature-contour" viewBox="0 0 1440 128" preserveAspectRatio="none" fill="none" aria-hidden="true"><defs><linearGradient id="contour-shine"><stop stop-color="#72bfff"/><stop offset=".53" stop-color="#d7edff"/><stop offset=".64" stop-color="#ffc95c"/><stop offset=".79" stop-color="#fff0be"/><stop offset="1" stop-color="#be872b"/></linearGradient></defs><path d="M0 1 H780 C838 1 836 10 865 31 L947 94 Q990 127 1030 127 H1440" stroke="url(#contour-shine)" stroke-width="1.7"/></svg>
    <div class="container signature-inner">
        <div><strong>Custom.</strong><span>DESIGNED AROUND YOUR BRAND</span></div>
        <div><strong>Full stack.</strong><span>DESIGN MEETS DEVELOPMENT</span></div>
        <div><strong>Every screen.</strong><span>RESPONSIVE FROM THE START</span></div>
        <div class="strip-quote"><em>Extraordinary websites.<br>Distinctively yours.</em><span>THE CAJUNWEBPRO APPROACH</span></div>
    </div>
</section>
<section class="section capabilities" id="capabilities" aria-labelledby="capabilities-title">
    <div class="container">
        <div class="section-heading">
            <div><span class="eyebrow">OUR SERVICES</span><h2 id="capabilities-title">Everything You Need<br>to <span>Stand Out Online.</span></h2></div>
            <p>Strategy, design, and development working together<br>to create a powerful digital experience.</p>
            <a class="btn outline small" href="<?=esc(page_url('services'))?>">View All Services <?=icon('arrow')?></a>
        </div>
        <div class="service-grid"><?php foreach(array_slice($services,0,5) as $s) service_card($s); ?></div>
        <div class="engineering-rail">
            <span><i class="status-light" aria-hidden="true"></i> THE DETAIL MAKES THE DIFFERENCE</span>
            <div><span>01 <b>DISCOVER</b></span><i>→</i><span>02 <b>DESIGN</b></span><i>→</i><span>03 <b>DEVELOP</b></span><i>→</i><span>04 <b>LAUNCH</b></span></div>
            <a href="<?=esc(page_url('process'))?>">OUR PROCESS ↗</a>
        </div>
    </div>
</section>
<section class="section featured-work" aria-labelledby="featured-title">
    <div class="container">
        <div class="work-intro">
            <div class="work-heading">
                <span class="eyebrow">FEATURED WORK</span>
                <h2 id="featured-title">Different Brands. <br><span>Extraordinary <br>Possibilities.</span></h2>
                <p>A closer look at the ideas, craft, and possibilities behind the pixels.</p>
                <a class="btn outline small" href="<?=esc(page_url('work'))?>">View Our Work <?=icon('arrow')?></a>
            </div>
            <div class="project-grid">
                <?php foreach($projects as $p) project_card($p); ?>
                <?php if(!$projects): ?><div class="empty-card"><span class="eyebrow">YOUR NEXT CHAPTER</span><h3>The next great website could be yours.</h3><p>New project stories are on the way. Let's talk about what we could build for your business.</p><a class="btn outline" href="<?=esc(page_url('contact'))?>">Start a conversation <?=icon('arrow')?></a></div><?php endif; ?>
            </div>
        </div>
        <?php if(array_filter($projects,fn($p)=>!empty($p['is_demo']))): ?><p class="concept-note">Projects labeled “Design concept” are illustrative studies—not commissioned client work or claims of client results.</p><?php endif; ?>
    </div>
</section>
<section class="section confidence-section" aria-labelledby="confidence-title">
    <div class="container confidence-layout">
        <div class="confidence-heading">
            <span class="eyebrow"><?= $testimonials ? 'CLIENT TESTIMONIALS' : 'MORE THAN GOOD LOOKS' ?></span>
            <h2 id="confidence-title"><?= $testimonials ? 'Good Work. <br>Great Relationships.' : 'Designed to Impress. <br><span>Built to Perform.</span>' ?></h2>
            <p><?= $testimonials ? 'Their words. Their experience.' : 'Creative thinking. Solid engineering. A website that is yours.' ?></p>
        </div>
        <?php if($testimonials): ?>
        <div class="testimonial-grid">
            <?php foreach($testimonials as $t): ?>
            <figure class="testimonial glow-card">
                <?=icon('quote')?>
                <blockquote><?=esc($t['quote'])?></blockquote>
                <figcaption><strong><?=esc($t['name'])?></strong><span><?=esc($t['role'])?></span><span class="stars" aria-label="<?=max(1,min(5,(int)$t['rating']))?> out of 5 stars"><?=str_repeat('★',max(1,min(5,(int)$t['rating'])))?></span></figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="promise-grid">
            <article class="promise-card glow-card"><div class="promise-symbol"><?=icon('diamond')?></div><div><h3>Distinct by design.</h3><p>A visual identity with character—not another business squeezed into a template.</p><span>CREATIVITY WITH PURPOSE</span></div></article>
            <article class="promise-card glow-card"><div class="promise-symbol"><?=icon('code')?></div><div><h3>Power behind the polish.</h3><p>Purpose-built functionality connects the experience to the way your business works.</p><span>DESIGN + ENGINEERING</span></div></article>
            <article class="promise-card glow-card"><div class="promise-symbol"><?=icon('shield')?></div><div><h3>Yours to manage.</h3><p>Content tools and a clear handover keep you in control of your website after launch.</p><span>YOUR WEBSITE. YOUR CONTROL.</span></div></article>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php growth_home(); ?>
