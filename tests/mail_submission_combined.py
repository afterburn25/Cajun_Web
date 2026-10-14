"""Real MariaDB + default application coordinator + maintained SMTP + pinned loopback TLS."""
from pathlib import Path
import email,email.policy,email.utils,hashlib,json,os,subprocess,sys
from mail_submission_server import SubmissionServer

ROOT=Path(__file__).resolve().parents[1];checks=[];passed=False;server=None;original_keys=None
def check(name,condition):
    checks.append({'name':name,'passed':bool(condition)})
    assert condition,name
def call(**request):
    p=subprocess.run([os.environ.get('PHP_BINARY','php'),str(ROOT/'tests/mail_submission_combined_driver.php')],input=json.dumps(request),capture_output=True,text=True,timeout=40)
    assert p.returncode==0 and not p.stderr,'Combined PHP fixture failed: '+p.stderr
    return json.loads(p.stdout)
def keys(label,result):
    global original_keys
    current=(result['key_sha256'],result['provider_key_sha256'])
    if original_keys is None:original_keys=current
    check(label+' original mail/provider keys retained',current==original_keys)
    check(label+' audit excludes private message and credentials',result['audit_private'] is False)
def replay(label,request,result):
    count=server.connections
    for name,identity in [('stable',request['id']),('different-form',os.urandom(16).hex())]:
        again=call(action='send',**{**request,'id':identity})
        check(label+' '+name+' replay returns existing identity',again['returned']==result['returned'] and again['error'] is None)
        check(label+' '+name+' replay performs no resolver or socket',again['observations']==[] and server.connections==count)
        check(label+' '+name+' replay leaves immutable intent/fence/result',all(again[k]==result[k] for k in ['intent','fence','result']))
def boundary(label,result):
    stages=result['observations']
    check(label+' DNS outside transaction and staff mutex',stages[0]=={'stage':'dns','transaction':False,'lock':None})
    check(label+' audited committed fence before pinned TLS socket',stages[1]=={'stage':'socket','transaction':False,'held':True,'fence':True,'audit':True})
    check(label+' result binds reserved attempt private snapshot and MIME',result['fence']['id']==result['intent']['attempt']==result['result']['id'] and result['fence']['submission_id']==result['intent']['id']==result['result']['submission_id'] and result['fence']['snapshot_fingerprint']==result['intent']['fingerprint']==result['result']['snapshot_fingerprint'] and result['fence']['mime_sha256']==result['intent']['mime_sha256']==result['result']['mime_sha256'])
def wire(label,result,attempt):
    check(label+' all envelope recipients precede DATA',attempt['recipients']==['first@example.invalid','copy@example.invalid','private@example.invalid'])
    check(label+' exact immutable MIME reaches server',attempt['data']==result['intent']['mime_bytes'].encode('ascii') and hashlib.sha256(attempt['data']).hexdigest()==result['intent']['mime_sha256'])
    message=email.message_from_bytes(attempt['data'],policy=email.policy.default)
    check(label+' Bcc remains private from transmitted headers',message['Bcc'] is None and b'private@example.invalid' not in attempt['data'])
    private=result['intent']['private']
    check(label+' exact Unicode subject and decoded saved body retained',str(message['Subject'])==private['subject'] and message.get_payload(decode=True)==private['body'].replace('\n','\r\n').encode('utf-8'))
    check(label+' immutable Message-ID and Date retained',str(message['Message-ID'])==private['message_id'] and int(email.utils.parsedate_to_datetime(str(message['Date'])).timestamp())==private['prepared_epoch'])
try:
    assert os.environ.get('CWP_TEST_DATABASE')=='cwp_test','Disposable database required'
    setup=call(action='setup');server=SubmissionServer(ROOT)
    cases={'accepted':('accepted','confirmation'),'authentication':('not_submitted','authentication'),'sender':('not_submitted','sender'),'recipient':('not_submitted','recipient'),'data':('not_submitted','data'),'final450':('rejected','confirmation'),'final550':('rejected','confirmation'),'disconnect_final':('unknown','confirmation'),'malformed_final':('unknown','confirmation'),'disconnect_body':(None,None),'revoked':('not_submitted',None)}
    for encryption,account in setup['accounts'].items():
        for case,(status,phase) in cases.items():
            server.configure(encryption,'accepted' if case=='revoked' else case)
            request=call(action='draft',account=account);before=server.connections
            result=call(action='send',**request,**({'revoke_after_open':True} if case=='revoked' else {}));label=encryption+'/'+case
            check(label+' finite successful coordinator return',result['error'] is None and result['returned']==request['id'])
            check(label+' exactly one real TCP attempt',server.connections==before+1)
            check(label+' fixture protocol has no unexpected commands',not server.errors)
            check(label+' finite conservative stored outcome',result['result']['status']==status if status else result['result']['status'] in ['not_submitted','unknown'])
            if phase:check(label+' recorded phase matches protocol boundary',result['result']['phase']==phase)
            boundary(label,result);attempt=server.attempts[-1]
            if attempt['terminator']:wire(label,result,attempt)
            if case in ['recipient','authentication','sender','revoked']:
                check(label+' refused prerequisite prevents all DATA','DATA' not in attempt['commands'] and not attempt['data'])
            if case=='revoked':check(label+' grant loss after socket prevents credentials and private disclosure','CREDENTIAL' not in attempt['commands'] and not result['disclosure'] and result['result']['reason']=='access_changed')
            keys(label,result);replay(label,request,result)
            if case in ['disconnect_final','final550']:
                edited=call(action='edit',draft=request['draft']);next_request={'draft':request['draft'],**edited};count=server.connections;server.configure(encryption,'accepted')
                updated=call(action='send',**next_request)
                if case=='disconnect_final':
                    check(label+' uncertain real transmission blocks edited draft resend',updated['error']=='refused' and updated['intent'] is None and updated['observations']==[] and server.connections==count)
                else:
                    check(label+' known rejection permits separately reviewed new version',updated['error'] is None and updated['result']['status']=='accepted' and updated['intent']['id']!=result['intent']['id'] and server.connections==count+1)
                    wire(label+' reviewed-new-version',updated,server.attempts[-1])
        for action in ['prepared','dispatched','finished']:
            server.configure(encryption,'accepted');request=call(action='draft',account=account);before=server.connections
            result=call(action='send',**request,fail_audit=action);label=encryption+'/audit-'+action
            check(label+' strict audit error is surfaced',result['error']=='database' and result['returned'] is None)
            check(label+' no terminal result invented',result['result'] is None)
            if action=='prepared':
                check(label+' no intent DNS socket or SMTP',result['intent'] is None and result['fence'] is None and result['observations']==[] and server.connections==before)
            elif action=='dispatched':
                check(label+' fence rolled back before any SMTP',result['intent'] is not None and result['fence'] is None and len(result['observations'])==1 and server.connections==before)
            else:
                attempt=server.attempts[-1]
                check(label+' actual accepted transmission remains unresolved',result['fence'] is not None and attempt['terminator'] and server.connections==before+1)
                wire(label,result,attempt)
            if action!='prepared':
                count=server.connections;again=call(action='send',**request)
                check(label+' interruption replay cannot repeat network',again['returned']==request['id'] and again['observations']==[] and again['result'] is None and server.connections==count)
                check(label+' interrupted records remain immutable',again['intent']==result['intent'] and again['fence']==result['fence'])
            keys(label,result)
    check('All combined fixture sessions remained local and protocol-valid',not server.errors)
    passed=True
finally:
    if server:server.close()
    qa=ROOT/'qa';qa.mkdir(exist_ok=True)
    (qa/'mail-submission-combined-results.json').write_text(json.dumps({'passed':passed,'scope':'Real MariaDB + default application coordinator + maintained SMTP + pinned loopback TLS; synthetic credentials/content only; no HTTP/browser or customer mail acceptance','checks':checks},indent=2)+'\n',encoding='utf-8',newline='\n')
print(f'PASS: {len(checks)} combined MariaDB/SMTP/TLS checks')
