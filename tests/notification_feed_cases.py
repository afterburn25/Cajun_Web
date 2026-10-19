"""Synthetic HTTP coverage of the per-recipient notification feed — no live mail."""
import requests
from release_cases import db
from staff_cases import OWNER

def run_browser(env,browser):
    globals().update({k:env[k] for k in ['ROOT','WEB','BASE','QA','check','soup','token','form_data','form_for','submit','php']})
    admin=requests.Session();root=BASE+'/existing/admin/index.php';portal=BASE+'/existing/portal/index.php'
    submit(admin,root+'?view=login','login',{'email':'owner@example.invalid','password':OWNER})
    url=root+'?view=notifications'
    r=admin.get(url);check('Notifications setup screen explains private feeds','private' in r.text.lower() and 'Enable notifications' in r.text)
    r=submit(admin,url,'notifications_setup',{'owner_password':OWNER,'confirm':'1','csrf':'invalid'});check('Notification setup rejects CSRF','expired' in r.text.lower() and not db("SELECT value FROM ci_settings WHERE name='notification_feed_version'"))
    r=submit(admin,url,'notifications_setup',{'owner_password':'wrong','confirm':'1'});check('Notification setup reauthenticates owner','password' in r.text.lower())
    r=submit(admin,url,'notifications_setup',{'owner_password':OWNER,'confirm':'1'});check('HTTP owner enables the feed','Notification center enabled' in r.text)
    staff_row=db("SELECT u.id FROM ci_users u WHERE u.email='owner@example.invalid'")[0]
    client_row=db("SELECT id FROM ci_studio_clients WHERE email='alice@example.invalid'")[0]
    db('DELETE FROM ci_rate_limits')
    prospect=requests.Session();public=BASE+'/existing/index.php?page=website-review'
    data=form_data(soup(prospect.get(public)).select_one('[data-growth-form]'));data.update(name='Notify Browser',email='notify-browser@example.invalid',message='Synthetic browser inquiry for the notification feed.',consent='1',current_url='https://example.invalid/fixture')
    import time;time.sleep(2.1);r=prospect.post(public,data=data,headers={'Accept':'application/json'})
    check('Public inquiry saved','ok' in r.json() and r.json()['ok'])
    n=db("SELECT * FROM ci_notification_feed WHERE audience='staff' AND recipient_id=?",[staff_row['id']])
    check('Lead submission notifies website.manage staff with a typed ref',len(n)>=1 and n[0]['ref'].startswith('inquiry:') and n[0]['kind']=='service-event')
    r=admin.get(url);check('Staff feed renders the notification','New project inquiry' in r.text and 'Open related item' in r.text)
    check('Portal delivery recorded as sent',db("SELECT state FROM ci_notification_deliveries WHERE notification_id=? AND channel='portal'",[n[0]['id']])[0]['state']=='sent')
    r=submit(admin,url,'notification_mark',{'id':n[0]['id'],'read':'1'});check('Staff mark-read persists',db('SELECT read_at FROM ci_notification_feed WHERE id=?',[n[0]['id']])[0]['read_at'] is not None)
    r=submit(admin,url,'notification_prefs',{'email_service-event':'','email_billing':'1','email_project-update':'1','email_approval-request':'1','email_download-ready':'1','email_support-reply':'1'})
    check('Per-kind email opt-out persists',db("SELECT email FROM ci_notification_prefs WHERE recipient_id=? AND kind='service-event'",[staff_row['id']])[0]['email']==0)
    alice=requests.Session();submit(alice,portal,'login',{'email':'alice@example.invalid','password':'Synthetic client passphrase 2026'})
    job=root+'?view=studio-job&id=1'
    r=submit(admin,job,'studio_message',{'job_id':'1','body':'Synthetic update for the notification lane.'})
    feed=db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=?",[client_row['id']])
    check('Staff message emits a client project-update',len(feed)>=1 and feed[0]['kind']=='project-update' and feed[0]['ref']=='job:1')
    r=alice.get(portal+'?notifications=1');check('Client feed shows the message notification','New studio message' in r.text and 'Directed to you' in r.text)
    r=submit(admin,job,'studio_publish',{'job_id':'1','kind':'design','title':'Synthetic v1','body':'Notification publish body'})
    feed=db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=? AND kind='approval-request' AND ref='job:1'",[client_row['id']])
    check('Publishing a pending version notifies the owning client',len(feed)>=1 and 'ready for your review' in feed[0]['body'])
    with (ROOT/'site/images/icons/favicon.png').open('rb') as f:r=submit(admin,job,'studio_upload',{'job_id':'1'},files={'document':('notify-share.png',f,'image/png')})
    feed=db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=? AND kind='project-update' AND ref='job:1' AND title LIKE 'New private file%'",[client_row['id']])
    check('Admin file share notifies the owning client',len(feed)>=1 and 'notify-share.png' in feed[0]['body'])
    r=submit(admin,job,'studio_invoice',{'job_id':'1','title':'Synthetic invoice','amount':'10.00'})
    cur=db('SELECT stage FROM ci_studio_jobs WHERE id=1')[0]['stage'];nxt='development' if cur!='development' else 'design'
    r=submit(admin,job,'studio_job',{'id':'1','client_id':str(client_row['id']),'title':'Retained website project','stage':nxt})
    feed=db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=? AND ref='job:1' AND title LIKE 'Project stage updated%'",[client_row['id']])
    check('Stage change notifies the owning client',len(feed)>=1 and 'Moved to' in feed[0]['body'])
    r=submit(admin,job,'studio_job',{'id':'1','client_id':str(client_row['id']),'title':'Retained website project','stage':cur})
    feed=db("SELECT * FROM ci_notification_feed WHERE audience='client' AND recipient_id=? AND kind='billing' AND ref LIKE 'invoice:%'",[client_row['id']])
    check('Issuing an invoice notifies the owning client',len(feed)>=1 and 'INV-' in feed[0]['body'])
    with (ROOT/'site/images/icons/favicon.png').open('rb') as f:r=submit(alice,portal+'?job=1','upload',files={'document':('notify-client.png',f,'image/png')})
    feed=db("SELECT * FROM ci_notification_feed WHERE audience='staff' AND recipient_id=? AND ref='job:1' AND title LIKE 'Client file%'",[staff_row['id']])
    check('Client upload notifies clients.manage staff',len(feed)>=1 and 'notify-client.png' in feed[0]['body'])
    check('Client workspace links notifications','?notifications=1' in alice.get(portal).text)
    r=submit(alice,portal+'?notifications=1','notification_client_mark',{'id':feed[0]['id'],'read':'1'});check('Client mark-read persists',db('SELECT read_at FROM ci_notification_feed WHERE id=?',[feed[0]['id']])[0]['read_at'] is not None)
    bob=requests.Session();submit(bob,portal,'login',{'email':'bob@example.invalid','password':'Synthetic client passphrase 2026'})
    r=bob.post(portal,data={'action':'notification_client_mark','id':feed[0]['id'],'read':'0','csrf':token(bob.get(portal))});check('Another client cannot alter foreign read state',db('SELECT read_at FROM ci_notification_feed WHERE id=?',[feed[0]['id']])[0]['read_at'] is not None)
    r=alice.get(portal+'?notifications=1');check('Client sees only own notifications','New project inquiry' not in r.text)
    r=admin.get(root+'?view=health')
    check('Health page renders honest freshness metrics','Operational freshness' in r.text and 'notification.deliveries' in r.text and 'absent' in r.text)
    check('Client session cannot open staff health metrics',alice.get(root+'?view=health').status_code==403)


    r=submit(admin,url,'notification_announce',{'title':'Synthetic maintenance window','body':'Synthetic service notice for the notification lane.','confirm':'1'})
    a=db("SELECT * FROM ci_notification_announcements WHERE title='Synthetic maintenance window'")
    check('Announcement recorded with actor and recipient count',bool(a) and a[0]['recipients']>=2)
    r=alice.get(portal+'?notifications=1');check('Client feed shows the service announcement','Synthetic maintenance window' in r.text and 'Service' in r.text)
    r=bob.get(portal+'?notifications=1');check('Second client receives the same announcement','Synthetic maintenance window' in r.text)
    r=admin.get(url);check('Admin notifications history lists the announcement','Synthetic maintenance window' in r.text and 'recipients' in r.text)
    r=bob.post(root,data={'action':'notification_announce','csrf':token(bob.get(portal)),'title':'Denied announce','body':'This should be denied for authentication.'});check('A client session cannot announce to clients',db("SELECT COUNT(*) AS n FROM ci_notification_announcements WHERE title='Denied announce'")[0]['n']==0)
