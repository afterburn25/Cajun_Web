<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function design_financial_input(string $name,string $label,string $value,string $id,int $max=160,string $mode='text'):void {
    echo '<label for="'.esc($id).'">'.esc($label).'</label><input id="'.esc($id).'" name="'.esc($name).'" value="'.esc($value).'" maxlength="'.$max.'" type="text"'.($mode!=='text'?' inputmode="'.esc($mode).'"':'').'>';
}
function design_financial_textarea(string $name,string $label,string $value,string $id,int $max,int $rows):void {
    echo '<label for="'.esc($id).'">'.esc($label).'</label><textarea id="'.esc($id).'" name="'.esc($name).'" maxlength="'.$max.'" rows="'.$rows.'">'.esc($value).'</textarea>';
}
function design_financial_select(string $name,string $label,array $options,string $value,string $id):void {
    echo '<label for="'.esc($id).'">'.esc($label).'</label><select id="'.esc($id).'" name="'.esc($name).'">';
    if(!array_key_exists($value,$options))echo '<option value="'.esc($value).'" selected>Retained value: '.esc($value).'</option>';
    foreach($options as $key=>$text)echo '<option value="'.esc((string)$key).'"'.((string)$key===$value?' selected':'').'>'.esc($text).'</option>';echo '</select>';
}
function design_financial_row_controls(string $group,string $id,int $index,int $count):void {
    echo '<div class="button-row financial-row-actions">';foreach(['up'=>'Move up','down'=>'Move down','remove'=>'Remove'] as $action=>$label)echo '<button class="btn outline" type="submit" name="form_action" value="'.esc($action.'_'.$group.':'.$id).'" formnovalidate'.(($action==='up'&&$index===0)||($action==='down'&&$index===$count-1)||($action==='remove'&&$count===1)?' disabled':'').'>'.$label.'</button>';echo '</div>';
}
function design_financial_editor(array $view):void {
    $attempt=$GLOBALS['cwp_financial_offer_attempt']??null;
    if(!$attempt&&!$view['proposal']){echo '<p>The client must approve the latest proposal before you can publish financial terms.</p>';return;}
    $a=$attempt??design_financial_seed($view);$stale=$a['expected']!==$view['expected'];
    echo '<details class="financial-editor"'.($attempt?' open':'').'><summary>'.($attempt?'Your unsaved financial draft':'Prepare financial terms').'</summary><p>Use exact USD amounts with up to two decimal places. Discount and tax are whole-item amounts. No payment is recorded by publishing an offer.</p>';
    if($attempt){echo '<p class="notice info" role="status" id="financial-draft-notice">'.esc($GLOBALS['cwp_financial_draft_notice']??'Your original request and entered values are retained. Correct the issue above.').'</p>';if($stale)echo '<p class="notice error" role="alert">The financial agreement changed. Your earlier draft remains below; reload and review before publishing a new offer.</p>';design_financial_reload($view,true);}
    if($a['invalid'])echo '<p class="notice error" role="alert">Some malformed or oversized values could not be retained. Reload and review the current agreement before submitting.</p>';
    echo '<form method="post" class="financial-offer-form"'.($attempt?' aria-describedby="financial-draft-notice"':'').'>';admin_action('design_financial_form');
    foreach(['job_id','client_id','request_id','expected','proposal_id','proposal_version','proposal_sha256'] as $key)design_financial_hidden($key,$a[$key]);
    echo '<p>Based on approved proposal version '.esc($a['proposal_version']).'. Each new offer needs a separate client financial decision.</p>';
    design_financial_input('title','Agreement title',$a['title'],'financial-title');design_financial_textarea('terms','Complete financial terms',$a['terms'],'financial-terms',30000,6);
    echo '<h3>Priced items</h3>';
    foreach($a['items'] as $n=>$row){$prefix='items['.$n.']';$id='financial-item-'.$n;echo '<fieldset class="financial-row"><legend>Item '.($n+1).'</legend>';design_financial_hidden($prefix.'[id]',$row['id']);
        design_financial_textarea($prefix.'[description]','Description',$row['description'],$id.'-description',2000,2);echo '<div class="financial-grid">';
        foreach(['quantity'=>'Quantity','unit'=>'Unit price (USD)','discount'=>'Item discount (USD)','tax'=>'Item tax (USD)'] as $key=>$label){echo '<div>';design_financial_input($prefix.'['.$key.']',$label,$row[$key],$id.'-'.$key,$key==='quantity'?3:20,$key==='quantity'?'numeric':'decimal');echo '</div>';}echo '</div>';design_financial_row_controls('items',$row['id'],$n,count($a['items']));echo '</fieldset>';
    }echo '<button class="btn outline" type="submit" name="form_action" value="add_item" formnovalidate>Add priced item</button><h3>Payment stages</h3><p>A single full payment uses the remainder. For installments, use an optional first deposit, milestone payments and a final remainder. Percentages apply to the complete agreement total. Remainder value must be 0.00.</p>';
    foreach($a['stages'] as $n=>$row){$prefix='stages['.$n.']';$id='financial-stage-'.$n;echo '<fieldset class="financial-row"><legend>Stage '.($n+1).'</legend>';design_financial_hidden($prefix.'[id]',$row['id']);design_financial_input($prefix.'[title]','Stage title',$row['title'],$id.'-title');echo '<div class="financial-grid"><div>';
        design_financial_select($prefix.'[kind]','Purpose',['full'=>'Full payment','deposit'=>'Deposit','milestone'=>'Milestone','final'=>'Final balance'],$row['kind'],$id.'-kind');echo '</div><div>';
        design_financial_select($prefix.'[calculation]','Calculation',['remainder'=>'Remaining total','fixed'=>'Fixed USD amount','percent'=>'Percent of total'],$row['calculation'],$id.'-calculation');echo '</div><div>';
        design_financial_input($prefix.'[value]','Amount (USD) or percentage',$row['value'],$id.'-value',20,'decimal');echo '</div><div>';
        design_financial_input($prefix.'[due_date]','Agreed due date (YYYY-MM-DD, optional)',$row['due_date'],$id.'-date',10);echo '</div></div>';
        design_financial_select($prefix.'[milestone_id]','Project milestone (milestone installments only)',[''=>'No milestone']+$view['milestones'],$row['milestone_id'],$id.'-milestone');design_financial_row_controls('stages',$row['id'],$n,count($a['stages']));echo '</fieldset>';
    }echo '<button class="btn outline" type="submit" name="form_action" value="add_stage" formnovalidate>Add installment before the final balance</button><p>Row controls update this draft only and work without JavaScript. Review the calculated preview before publishing.</p><div class="button-row"><button class="btn outline" type="submit" name="form_action" value="preview">Preview exact amounts</button><button class="btn gold" type="submit" name="form_action" value="publish"'.($stale||$a['invalid']?' disabled':'').'>Publish financial offer</button></div></form>';
    if(isset($GLOBALS['cwp_financial_preview'])){$p=$GLOBALS['cwp_financial_preview'];echo '<div class="financial-preview"><h3>Unsaved preview</h3>';design_financial_terms($p['fields'],$p['quote'],$p['stages'],[],true,$view['milestones']);echo '</div>';}
    echo '</details>';
}
/** Keep concurrent failed drafts visible even when issuance has closed editing. */
function design_financial_retained_draft(array $a):void {
    echo '<details class="financial-retained" open><summary>Your earlier unsaved draft</summary><p>These submitted values are retained for reference. The issued agreement cannot be replaced.</p>';
    foreach(['request_id','expected'] as $key)design_financial_hidden($key,$a[$key]);
    echo '<h3>'.esc($a['title']).'</h3><div class="financial-text">'.esc($a['terms']).'</div>';
    foreach(['items'=>['description'=>'Description','quantity'=>'Quantity','unit'=>'Unit price','discount'=>'Item discount','tax'=>'Item tax'],'stages'=>['title'=>'Stage title','kind'=>'Purpose','calculation'=>'Calculation','value'=>'Amount or percentage','due_date'=>'Due date','milestone_id'=>'Milestone reference']] as $name=>$fields){echo '<h4>'.($name==='items'?'Priced items':'Payment stages').'</h4>';foreach($a[$name] as $row){echo '<dl class="financial-facts">';foreach($fields as $key=>$label)echo '<dt>'.esc($label).'</dt><dd class="financial-text">'.esc($row[$key]).'</dd>';echo '</dl>';}}
    echo '</details>';
}
