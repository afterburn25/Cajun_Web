<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/includes/foundation/pipeline/PipelineStage.php';
require_once CWP_CODE_ROOT.'/includes/foundation/pipeline/PipelineBoard.php';
require_once CWP_CODE_ROOT.'/database/schema/pipeline_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/pipeline_migrate.php';
use CajunWebPro\Foundation\Pipeline\{PipelineBoard,PipelineStage};

/** Canonical sales board — transition rules are the single source of truth for both pipeline cards and the inquiry detail form. */
function pipeline_board(): PipelineBoard {
    static $board=null;
    if($board===null){$labels=studio_pipeline_stages();$rules=['new'=>['contacted','spam','closed'],'contacted'=>['qualified','closed','spam'],'qualified'=>['proposal','closed'],'proposal'=>['won','closed'],'won'=>[],'closed'=>['new'],'spam'=>['new']];
        $stages=[];$pos=0;foreach($labels as $id=>$label)$stages[]=new PipelineStage($id,$label,$pos++,$rules[$id]??[],$id==='won',in_array($id,['closed','spam'],true));
        $board=new PipelineBoard($stages,'new');}
    return $board;
}
/** Whether the append-only stage history table is enabled. */
function pipeline_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['pipeline_schema_version'])['value']??'')==='1';}
/** Stage options a lead may move to from $current, with the current stage always present. */
function pipeline_choices(string $current): array {
    $board=pipeline_board();$choices=[$current=>$board->stage($current)?->label??studio_pipeline_stages()[$current]??$current];
    foreach($board->allowedMoves($current) as $stage)$choices[$stage->id]=$stage->label;
    return $choices;
}
/** Append-only transition history for one lead — newest first. */
function pipeline_history(int $leadId): array {return pipeline_ready()?all('SELECT h.*,u.name AS actor_name FROM '.table('lead_stage_history').' h LEFT JOIN '.table('users').' u ON u.id=h.actor_id WHERE h.lead_id=? ORDER BY h.id DESC LIMIT 100',[$leadId]):[];}
/**
 * Validate and apply an inquiry stage update through the board. Same-stage saves update notes/follow-up only;
 * real transitions must be configured moves and are recorded in the append-only history when it is enabled.
 */
function pipeline_transition(int $leadId,string $to,string $notes,string $follow,array $actor): void {
    $lead=one('SELECT * FROM '.table('leads').' WHERE id=?',[$leadId]);if(!$lead)throw new InvalidArgumentException('Inquiry not found.');
    if(!isset(studio_pipeline_stages()[$to]))throw new InvalidArgumentException('Choose a valid stage.');
    if($follow!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$follow)||date('Y-m-d',strtotime($follow))!==$follow))throw new InvalidArgumentException('Use a valid follow-up date.');
    $from=$lead['status'];$moved=$to!==$from;
    if($moved&&!pipeline_board()->canTransition($from,$to))throw new InvalidArgumentException('Inquiries cannot move from "'.(studio_pipeline_stages()[$from]??$from).'" to "'.studio_pipeline_stages()[$to].'".');
    staff_transaction(function() use($leadId,$to,$notes,$follow,$from,$moved,$actor): void {
        query('UPDATE '.table('leads').' SET status=?,notes=? WHERE id=?',[$to,$notes,$leadId]);save_setting('growth_follow_'.$leadId,$follow);
        if($moved&&pipeline_ready())query('INSERT INTO '.table('lead_stage_history').' (lead_id,from_stage,to_stage,actor_id,note,created_at) VALUES (?,?,?,?,?,?)',[$leadId,$from,$to,(int)$actor['id'],substr($notes,0,500),gmdate('Y-m-d H:i:s')]);
    });
    if($moved)log_event('pipeline.move','Inquiry '.$leadId.' '.$from.' → '.$to);
}
