"""Synthetic HTTP coverage of the proposal lifecycle — never live client terms."""
import json
import requests
from release_cases import db
from staff_cases import OWNER

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    admin=requests.Session();root=BASE+'/existing/admin/index.php';portal=BASE+'/existing/portal/index.php'
    submit(admin,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    url=root+'?view=proposals'
    r=admin.get(url);check('Proposals setup screen explains additive opt-in','Enable proposals' in r.text and 'append-only' in r.text)
    r=submit(admin,url,'proposal_setup',{'owner_password':OWNER,'confirm':'1','csrf':'invalid'});check('Proposal setup rejects CSRF','expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='proposals_schema_version'"))
    r=submit(admin,url,'proposal_setup',{'owner_password':'wrong','confirm':'1'});check('Proposal setup reauthenticates owner','password' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='proposals_schema_version'"))
    r=submit(admin,url,'proposal_setup',{'owner_password':OWNER,'confirm':'1'});check('HTTP owner enables proposals','Proposals enabled' in r.text)
    alice_id=str(db("SELECT id FROM ci_studio_clients WHERE email='alice@example.invalid'")[0]['id']);bob_id=str(db("SELECT id FROM ci_studio_clients WHERE email='bob@example.invalid'")[0]['id'])
    draft={'client_id':alice_id,'title':'Synthetic storefront proposal','deliverables':'Five-page storefront\nCheckout wiring','exclusions':'Product photography','milestones':'Design and build | 2400.00\nHosting year one | 95.88','schedule_notes':'Preview within three weeks.'}
    r=submit(admin,url,'proposal_create',draft);check('Draft proposal created','Draft proposal saved' in r.text)
    pid=db('SELECT id FROM ci_proposals')[0]['id']
    detail=root+'?view=proposal&id='+pid
    r=admin.get(detail)
    if not('VERSION 1' in r.text and '$2,495.88' in r.text and 'Draft' in r.text):
        json.dump({'pid':pid,'url':r.url,'status':r.status_code,'missing':[s for s in ['VERSION 1','$2,495.88','Draft'] if s not in r.text],'excerpt':r.text[:600]},open(QA+'/proposal-detail-diagnostics.json','w'))
    check('Draft detail shows milestone pricing','VERSION 1' in r.text and '$2,495.88' in r.text and 'Draft' in r.text)
    r=submit(admin,detail,'proposal_send',{});check('Proposal sent','sent to the client' in r.text.lower())
    alice=requests.Session();login_r=submit(alice,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    r=alice.get(portal)
    if not('Studio proposals' in r.text and 'Synthetic storefront proposal' in r.text):
        json.dump({'login_url':login_r.url,'login_status':login_r.status_code,'login_welcome':'Welcome, ' in login_r.text,
                   'url':r.url,'status':r.status_code,'studio_proposals':'Studio proposals' in r.text,
                   'title_present':'Synthetic storefront proposal' in r.text,
                   'rows':db("SELECT id,client_id,status,current_version,title FROM ci_proposals"),
                   'client':db("SELECT id,active,invite_hash IS NULL AS no_invite,password_hash<>'' AS has_password FROM ci_studio_clients WHERE email='alice@example.invalid'"),
                   'excerpt':r.text[:600]},open(QA+'/proposal-workspace-diagnostics.json','w'))
    check('Client workspace lists the sent proposal','Studio proposals' in r.text and 'Synthetic storefront proposal' in r.text)
    r=alice.get(portal+'?proposal='+pid);check('Client sees exact sent version content','$2,495.88' in r.text and 'Approve this version' in r.text)
    r=submit(alice,portal+'?proposal='+pid,'proposal_decide',{'choice':'changes','note':'   ','confirm':'1'});check('Change request requires a real note','describing what must change' in r.text or 'need' in r.text.lower())
    r=submit(alice,portal+'?proposal='+pid,'proposal_decide',{'choice':'changes','note':'Swap the milestone order','confirm':'1'});check('Client change request recorded','decision was recorded' in r.text)
    check('Client decision moved proposal to changes-requested',db("SELECT status FROM ci_proposals WHERE id=?",[pid])[0]['status']=='changes-requested')
    r=admin.get(detail);check('Staff sees client change note','Swap the milestone order' in r.text and 'Changes requested' in r.text)
    r=submit(admin,detail,'proposal_revise',{'title':draft['title'],'deliverables':draft['deliverables'],'exclusions':draft['exclusions'],'milestones':'Hosting year one | 95.88\nDesign and build | 2400.00','schedule_notes':draft['schedule_notes']});check('Revision saved as v2','new proposal version' in r.text.lower() and db('SELECT version FROM ci_proposal_versions WHERE proposal_id=? ORDER BY version',[pid])[-1]['version']==2)
    r=submit(admin,detail,'proposal_send',{});r=alice.get(portal+'?proposal='+pid);check('Client sees the revised version','VERSION 2' in r.text and 'Hosting year one — $95.88' in r.text)
    r=submit(alice,portal+'?proposal='+pid,'proposal_decide',{'choice':'approve','note':'','confirm':'1'});check('Client approval recorded','decision was recorded' in r.text)
    check('Approval pinned to exact current version',db("SELECT status FROM ci_proposals WHERE id=?",[pid])[0]['status']=='approved' and db('SELECT decision FROM ci_proposal_decisions WHERE proposal_id=? AND version=2',[pid])[0]['decision']=='approved')
    r=alice.get(portal+'?proposal='+pid);check('Approved proposal is read-only for the client','You approved version 2' in r.text and 'name="choice"' not in r.text)
    bob=requests.Session();submit(bob,portal,'login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    r=bob.get(portal+'?proposal='+pid);check('Other client cannot read the proposal',r.status_code in [403,404] and 'Synthetic storefront proposal' not in r.text)
    r=bob.post(portal,data={'action':'proposal_decide','proposal_id':pid,'choice':'approve','confirm':'1','csrf':token(bob.get(portal))},allow_redirects=True);check('Other client cannot decide the proposal',db('SELECT COUNT(*) AS n FROM ci_proposal_decisions WHERE proposal_id=?',[pid])[0]['n']==1)
    r=admin.post(detail,data={'action':'proposal_withdraw','id':pid,'confirm':'1','csrf':token(admin.get(detail))});check('Approved proposal cannot be withdrawn','approved proposal cannot be withdrawn' in r.text.lower() and db("SELECT status FROM ci_proposals WHERE id=?",[pid])[0]['status']=='approved')
    submit(admin,url,'proposal_create',{**draft,'client_id':alice_id,'title':'Synthetic withdrawn proposal'})
    wid=[x['id'] for x in db('SELECT id FROM ci_proposals') if x['id']!=pid][0]
    r=submit(admin,root+'?view=proposal&id='+wid,'proposal_withdraw',{'confirm':'1'});check('Draft proposal withdrawn','Proposal withdrawn' in r.text)
    r=alice.get(portal);check('Withdrawn proposal hidden from client list','Synthetic withdrawn proposal' not in r.text)
    r=alice.get(portal+'?proposal='+wid);check('Withdrawn proposal is not client-visible',r.status_code in [403,404])
