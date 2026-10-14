"""Latest-published 1.23 populated financial terms, invoices and receipts survive recovery."""
import datetime,json
from release_version import RECOVERY


def run(env):
    url,label,check=(env[k] for k in ['url','label','check'])
    owner,client,records,action=(env[k] for k in ['session','client','records','action'])
    fields,submit,password,private=(env[k] for k in ['fields','submit','password','private'])
    admin,portal=url+'/admin/index.php',url+'/portal/index.php'
    editor,target=admin+'?view=studio-job&id=1',portal+'?job=1'
    original=records();keys={name:(private/name).read_bytes() for name in ['mail-master.key','provider-master.key','retained-customer.php']}
    check(label+' upgraded financial terms require deliberate owner preparation','Prepare financial agreement storage' in owner.get(editor).text)
    submit(owner,editor,'design_financial_setup',{'owner_password':password,'confirm':'1'})
    check(label+' additive financial setup preserves existing invoices and payments',not records('financial')['design_payment_events'] and records()['studio_invoices']==original['studio_invoices'])
    submit(owner,editor,'studio_publish',{'kind':'proposal','title':'Retained financial scope','body':'Website service after upgrade','preview_url':''})
    submit(client,target,'decision',{'choice':'approved','confirm':'1','feedback':'Approve source scope'})
    offer=fields(owner,editor,'design_financial_form');offer.update({'title':'Retained website payment','terms':'Exact retained terms after 1.23 update','items[0][description]':'Website delivery','items[0][unit]':'80.01','form_action':'publish'})
    owner.post(editor,data=offer)
    decision=dict(fields(client,target,'financial_decide'),choice='accepted',confirm='1',comment='Accept exact retained amount')
    client.post(target,data=decision)
    issue=fields(owner,editor,'design_financial_issue');owner.post(editor,data=issue)
    snapshot=records('financial');issued=snapshot['design_payment_invoices'][0]['invoice_id'];invoice=admin+'?view=invoice-payments&invoice_id='+str(issued)
    receipt=dict(fields(owner,invoice,'payments_ledger_receive'),amount='25.00',method='bank',reference='Synthetic retained financial receipt',received_on=datetime.datetime.now(datetime.timezone.utc).date().isoformat(),confirm='1')
    owner.post(invoice,data=receipt)
    activity=portal+'?activity=1';staff_activity=admin+'?view=activity'
    if 'Enable workspace activity' in owner.get(staff_activity).text:
        submit(owner,staff_activity,'activity_setup',{'password':password})
    client_read=dict(fields(client,activity,'activity_mark'),keys=json.dumps(['financial:'+offer['request_id']]),state='read')
    staff_read=dict(fields(owner,staff_activity,'activity_mark'),keys=json.dumps(['financial:'+decision['request_id']]),state='read')
    client.post(activity,data=client_read);owner.post(staff_activity,data=staff_read)
    check(label+' financial notifications retain separate client and staff reads',len(records('financial')['financial_activity_reads'])==2 and {r['actor_type'] for r in records('financial')['financial_activity_reads']}=={'client','admin'})
    saved=records('financial');stable=records();earlier={name:records(name) for name in ['planning','progress','tickets','plans','submissions','drafts','provider','mail-accounts','templates']}
    check(label+' upgraded accepted offer issues one bound invoice with real ledger balance',len(saved['design_payment_events'])==3 and len(saved['design_payment_invoices'])==1 and 'Recorded payments: $25.00 USD' in client.get(target).text and 'Invoice balance: $55.01 USD' in client.get(target).text)
    def retained(expected):
        return records('financial')==expected and all(records(name)==data for name,data in earlier.items()) and all(records()[name]==stable[name] for name in ['studio_clients','studio_jobs']) and all((private/name).read_bytes()==data for name,data in keys.items())
    journal=private/RECOVERY/'journal.php';before=journal.read_bytes();records('owner-rate-exhaust');r=action('recover')
    check(label+' throttled recovery retains financial agreements invoices receipts and keys','Please wait before another owner authentication attempt.' in r.text and journal.read_bytes()==before and retained(saved))
    records('owner-rate-reset');r=action('recover')
    check(label+' old release preserves financial data without claiming financial interface','<h2>Rolled-back</h2>' in r.text and retained(saved) and 'id="financial"' not in client.get(target).text)
    r=action('apply')
    check(label+' reapplication restores accepted terms and balances without setup','<h2>Complete</h2>' in r.text and retained(saved) and 'Invoice balance: $55.01 USD' in client.get(target).text)
    owner.post(editor,data=offer);client.post(target,data=decision)
    check(label+' original offer and consent replays after recovery preserve frozen terms',retained(saved))
    r=owner.post(editor,data=issue)
    check(label+' original issuance replay after recovery opens original unique invoice',retained(saved) and 'invoice_id='+str(issued) in r.url)
    owner.post(invoice,data=receipt)
    check(label+' original receipt replay after recovery cannot duplicate payment',retained(saved))
    client.post(activity,data=client_read);owner.post(staff_activity,data=staff_read)
    check(label+' original financial read actions replay after recovery without duplication',retained(saved))
    opened=client.get(activity+'&open=financial:'+offer['request_id'])
    check(label+' recovered financial notice opens its original frozen offer',retained(saved) and '#financial-event-'+offer['request_id'] in opened.url)
    submit(owner,invoice,'payments_ledger_receive',{'amount':'1.00','method':'bank','reference':'Synthetic additional recovered receipt','received_on':datetime.datetime.now(datetime.timezone.utc).date().isoformat(),'confirm':'1'})
    active=records('financial')
    check(label+' recovered financial invoice accepts one authorized additional receipt',len(active['invoice_payments'])==len(saved['invoice_payments'])+1 and active['design_payment_events']==saved['design_payment_events'] and retained(active) and 'Invoice balance: $54.01 USD' in client.get(target).text)
    r=action('recover')
    check(label+' second recovery preserves new financial receipt and previous business data','<h2>Rolled-back</h2>' in r.text and retained(active))
    r=action('apply')
    check(label+' second reapplication restores financial balance without duplicate invoices','<h2>Complete</h2>' in r.text and retained(active) and 'Invoice balance: $54.01 USD' in client.get(target).text)
