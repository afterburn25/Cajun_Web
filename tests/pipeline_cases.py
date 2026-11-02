"""Pipeline transition rules and append-only stage history over real HTTP."""
import hashlib,requests
from release_cases import db
from staff_cases import OWNER,STAFF

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','submit','php','login']})
    db('DELETE FROM ci_rate_limits')
    root=BASE+'/existing/admin/index.php';board=root+'?view=studio-pipeline'
    owner=login('owner@example.invalid',OWNER)
    db("INSERT INTO ci_leads (reference,request_key,name,email,company,phone,service,budget,timeline,message,consent,ip_hash,status,notes,notification_status,created_at) VALUES ('PIPE-HTTP',?,?,?,?,?,?,?,?,?,1,?,?,?,?)",
       [hashlib.sha256(b'pipeline-http').hexdigest(),'Pipeline HTTP lead','pipeline-http@example.invalid','','','Design','Flexible','Discuss','Synthetic pipeline HTTP inquiry',hashlib.sha256(b'pipeline-http-ip').hexdigest(),'new','','not_configured','2026-10-16 12:00:00'])
    lead=db("SELECT id FROM ci_leads WHERE reference='PIPE-HTTP'")[0]['id'];card=board;lead_page=root+'?view=lead&id='+str(lead)
    r=submit(owner,board,'pipeline_setup',{'csrf':'wrong'});check('Pipeline history setup rejects CSRF','session expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='pipeline_schema_version'"))
    r=submit(owner,board,'pipeline_setup',{'owner_password':'wrong','confirm':'1'});check('Pipeline history setup requires owner password','current password' in r.text.lower())
    r=submit(owner,board,'pipeline_setup',{'owner_password':OWNER,'confirm':'1'});check('Owner enables pipeline stage history','Pipeline stage history is enabled' in r.text)
    def card_form():
        for f in soup(owner.get(card)).select('form'):
            if f.select_one('[name=action][value=studio_pipeline]') and f.select_one('[name=id]') and f.select_one('[name=id]').get('value')==str(lead):return f
        raise AssertionError('no card form for lead')
    options={o.get('value') for o in card_form().select('[name=status] option')}
    check('Pipeline card only offers configured moves',options=={'new','contacted','spam','closed'})
    def move(to,notes='HTTP move'):
        data=form_data(card_form());data.update({'status':to,'notes':notes,'follow_up':''});return owner.post(card,data=data)
    r=move('proposal');check('Illegal jump new→proposal refused','Pipeline' in r.text and db('SELECT status FROM ci_leads WHERE id=?',[lead])[0]['status']=='new' and not db('SELECT id FROM ci_lead_stage_history WHERE lead_id=?',[lead]))
    r=move('contacted');check('Legal pipeline move applies and records history',db('SELECT status FROM ci_leads WHERE id=?',[lead])[0]['status']=='contacted' and db('SELECT from_stage,to_stage,actor_id FROM ci_lead_stage_history WHERE lead_id=?',[lead])==[{'from_stage':'new','to_stage':'contacted','actor_id':1}])
    r=submit(owner,lead_page,'lead_save',{'status':'qualified','notes':'Detail editor honours the same rules'});check('Detail form legal move applies',db('SELECT status FROM ci_leads WHERE id=?',[lead])[0]['status']=='qualified')
    r=submit(owner,lead_page,'lead_save',{'status':'closed','notes':'not used'});check('Detail form honours qualified→closed',db('SELECT status FROM ci_leads WHERE id=?',[lead])[0]['status']=='closed')
    opts={o.get('value') for f in soup(owner.get(lead_page)).select('form') if f.select_one('[name=action][value=lead_save]') for o in f.select('[name=status] option')}
    check('Closed lead can only reopen to new',opts=={'closed','new'})
    r=submit(owner,lead_page,'lead_save',{'status':'new','notes':'Client returned','confirm':'1'});check('Closed inquiry reopens',db('SELECT status FROM ci_leads WHERE id=?',[lead])[0]['status']=='new')
    r=owner.get(lead_page);check('Lead detail shows attributed stage history','Stage history' in r.text and 'New inquiry → Consultation' in r.text)
    web=requests.Session();submit(web,root+'?view=login','login',{'email':'website-staff@example.invalid','password':STAFF})
    r=web.get(board);check('Website staff still sees the pipeline board',r.status_code==200 and 'Enable stage history' not in r.text)
    before=db('SELECT COUNT(*) AS c FROM ci_lead_stage_history')[0]['c']
    f=[f for f in soup(web.get(card)).select('form') if f.select_one('[name=action]') and f.select_one('[name=action]').get('value')=='pipeline_setup']
    check('History setup card hidden from non-owners',f==[])
    db('DELETE FROM ci_lead_stage_history WHERE lead_id=?',[lead]);db('DELETE FROM ci_leads WHERE id=?',[lead])
