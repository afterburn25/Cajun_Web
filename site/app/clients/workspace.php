<?php
/** Optional private client workspace; database setup is explicit for existing sites. */
declare(strict_types=1);if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
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
function studio_ready(): bool {return setting('studio_schema_version')==='4';}

/** Structured milestones arrive through a later studio_migrate run on older workspace installs. */
function studio_milestones_ready(): bool {return studio_ready()&&(bool)one('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'studio_milestones']);}
function studio_milestone_states(): array {return ['pending'=>'Pending','progress'=>'In progress','done'=>'Done','blocked'=>'Blocked'];}
function studio_milestones(int $job): array {return studio_milestones_ready()?all('SELECT * FROM '.table('studio_milestones').' WHERE job_id=? ORDER BY id',[$job]):[];}

function studio_stages(): array {return ['discovery'=>'Discovery','design'=>'Design','development'=>'Development','review'=>'Final review','launched'=>'Launched','care'=>'Ongoing care','paused'=>'Paused'];}

function studio_url(array $p=[]): string {if($ctx=support_current())$p=['support'=>$ctx['session']['id']]+$p;return site_url('portal/index.php').($p?'?'.http_build_query($p):'');}
