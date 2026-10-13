<?php
/** Optional private client workspace; database setup is explicit for existing sites. */
declare(strict_types=1);defined('CWP_BOOT') || exit;
require_once CWP_CODE_ROOT.'/app/support/sessions.php';
require_once CWP_CODE_ROOT.'/app/billing/catalog.php';
require_once CWP_CODE_ROOT.'/includes/layout/catalog.php';
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/projects/deliveries.php';
require_once CWP_CODE_ROOT.'/app/activity/events.php';
require_once CWP_CODE_ROOT.'/database/schema/workspace.php';
require_once CWP_CODE_ROOT.'/database/migrations/workspace.php';
require_once CWP_CODE_ROOT.'/app/clients/identity.php';
require_once CWP_CODE_ROOT.'/app/projects/reviews.php';
require_once CWP_CODE_ROOT.'/app/clients/files.php';
function studio_ready(): bool {return setting('studio_schema_version')==='2';}

function studio_stages(): array {return ['discovery'=>'Discovery','design'=>'Design','development'=>'Development','review'=>'Final review','launched'=>'Launched','care'=>'Ongoing care','paused'=>'Paused'];}

function studio_url(array $p=[]): string {if($ctx=support_current())$p=['support'=>$ctx['session']['id']]+$p;return site_url('portal/index.php').($p?'?'.http_build_query($p):'');}
