<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function gphead(string $eyebrow,string $main,string $gold,string $text): void { ?><section class="page-hero growth-hero"><div class="container"><span class="eyebrow"><?=esc($eyebrow)?></span><h1><?=esc($main)?><br><span class="gold-text"><?=esc($gold)?></span></h1><p><?=esc($text)?></p></div></section><?php }
function gpcta(): void { ?><div class="growth-cta"><div><span class="eyebrow">YOUR NEXT CHAPTER</span><h2>Let’s make it unmistakably yours.</h2><p>Tell us what is working, what is not, and where you want to go.</p></div><a class="btn gold" href="<?=esc(page_url('estimate'))?>">Plan your project →</a></div><?php }
function gpfaq(int $limit=99): void { ?><div class="growth-faq"><?php foreach(array_slice(growth_faq(),0,$limit) as [$q,$ans]):?><details><summary><?=esc($q)?><span>+</span></summary><p><?=esc($ans)?></p></details><?php endforeach;?></div><?php }
