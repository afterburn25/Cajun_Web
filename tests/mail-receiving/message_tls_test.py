"""Real loopback TLS, pinned production options, synthetic headers only. No external mailbox."""
from pathlib import Path
import base64,datetime,hashlib,json,socket,ssl,subprocess,tempfile,threading,time
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import rsa

HERE=Path(__file__).resolve().parent;ROOT=HERE.parents[1]
RUNTIME=ROOT/'test-runtime';RUNTIME.mkdir(exist_ok=True);CHECKS=[]
def check(name,passed):
    CHECKS.append({'name':name,'passed':bool(passed)})
    assert passed,name
def key():return rsa.generate_private_key(public_exponent=65537,key_size=2048)
def subject(name):return x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,name)])
def cert(k,name,issuer,signer,ca=False,expired=False):
    now=datetime.datetime.now(datetime.timezone.utc)
    b=x509.CertificateBuilder().subject_name(subject(name)).issuer_name(issuer).public_key(k.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(days=2)).not_valid_after(now+datetime.timedelta(days=-1 if expired else 2)).add_extension(x509.BasicConstraints(ca=ca,path_length=None),critical=True)
    if not ca:b=b.add_extension(x509.SubjectAlternativeName([x509.DNSName(name)]),critical=False)
    return b.sign(signer,hashes.SHA256())
def header(large=False):
    h=b'From: Synthetic <synthetic@example.net>\r\nSubject: =?UTF-8?B?Q2Fmw6k=?=\r\nDate: Tue, 13 Oct 2026 10:30:00 -0500\r\n'
    if large:
        for n in range(4):
            length=min(3990,16384-len(h)-2-7)
            h+=f'X-{n}: '.encode()+b'x'*length+b'\r\n'
        h+=b'X-End: '+b'y'*(16384-len(h)-11)+b'\r\n'
    return h+b'\r\n'

with tempfile.TemporaryDirectory(prefix='cwp-receiving-tls-',dir=RUNTIME) as temp:
    temp=Path(temp).resolve();assert temp.is_relative_to(RUNTIME.resolve())
    cak=key();cac=cert(cak,'Synthetic receiving CA',subject('Synthetic receiving CA'),cak,True)
    (temp/'ca.pem').write_bytes(cac.public_bytes(serialization.Encoding.PEM))
    other=key();(temp/'other.pem').write_bytes(cert(other,'Other CA',subject('Other CA'),other,True).public_bytes(serialization.Encoding.PEM))
    for expired in [False,True]:
        k=key();stem='expired' if expired else 'server'
        (temp/(stem+'.pem')).write_bytes(cert(k,'inbox.example.net',cac.subject,cak,expired=expired).public_bytes(serialization.Encoding.PEM))
        (temp/(stem+'.key')).write_bytes(k.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
    def trial(encryption,case):
        listener=socket.socket();listener.bind(('127.0.0.1',0));listener.listen(1);listener.settimeout(4)
        port=listener.getsockname()[1];commands=[];errors=[];sni=[]
        ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);ctx.minimum_version=ssl.TLSVersion.TLSv1_2
        ctx.set_servername_callback(lambda sock,name,c:sni.append(name))
        stem='expired' if case=='expired' else 'server';ctx.load_cert_chain(str(temp/(stem+'.pem')),str(temp/(stem+'.key')))
        def serve():
            s=None
            try:
                s,_=listener.accept();s.settimeout(4)
                if encryption=='ssl':s=ctx.wrap_socket(s,server_side=True)
                def send(line):s.sendall((line+'\r\n').encode())
                def read():
                    data=b''
                    while not data.endswith(b'\r\n'):
                        part=s.recv(1)
                        if not part:raise EOFError()
                        data+=part
                        assert len(data)<=4096,'fixture command bound'
                    return data[:-2].decode()
                def command(expected,label=None):
                    value=read();commands.append(label or value);assert value==expected,'unexpected command: '+value
                if case=='slow':time.sleep(0.6);return
                if case=='eof':return
                if case=='oversized':send('X'*9000);return
                if case=='bare_lf':s.sendall(b'* OK synthetic\n');return
                send('* PREAUTH synthetic' if case=='preauth' else '* OK synthetic')
                if case=='preauth':return
                tag=1;command(f'CWR{tag:03} CAPABILITY')
                if case=='byte_flood':send(('* OK '+('x'*8000)+'\r\n')*50+f'CWR{tag:03} OK done');return
                if case=='reply_flood':send('* OK synthetic\r\n'*161+f'CWR{tag:03} OK done');return
                if case=='line_flood':send('* X ('+'x\r\n'*260+')');return
                if case=='deep':send('* X '+('('*40)+'x'+(')'*40));return
                if encryption=='starttls':
                    send('* CAPABILITY IMAP4rev1 '+('NOUPGRADE' if case=='missing_upgrade' else 'STARTTLS'));send(f'CWR{tag:03} OK done')
                    if case=='missing_upgrade':return
                    tag+=1;command(f'CWR{tag:03} STARTTLS');send(f'CWR{tag:03} OK upgrade'+('\r\n* CAPABILITY AUTH=PLAIN' if case=='upgrade_injection' else ''))
                    s=ctx.wrap_socket(s,server_side=True);tag+=1;command(f'CWR{tag:03} CAPABILITY')
                send('* CAPABILITY IMAP4rev1 '+('AUTH=XOAUTH2' if case=='no_auth' else 'AUTH=PLAIN'));send(f'CWR{tag:03} OK done')
                if case=='no_auth':return
                tag+=1;command(f'CWR{tag:03} AUTHENTICATE PLAIN');send('+ ')
                command(base64.b64encode(b'\0synthetic@example.net\0synthetic fixture password').decode(),'CREDENTIAL')
                send(f'CWR{tag:03} '+('NO private rejection detail' if case=='wrong_auth' else 'OK authenticated'))
                if case=='wrong_auth':return
                tag+=1;command(f'CWR{tag:03} EXAMINE "INBOX"');total=0 if case=='empty' else 2
                epoch=38 if case=='wrong_epoch' else 37
                send(f'* {total} EXISTS');send(f'* OK [UIDVALIDITY {epoch}] stable');send(f'CWR{tag:03} OK [READ-ONLY] examined')
                if not total or case=='wrong_epoch':return
                raw=b'Subject: Synthetic message\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nActual body'
                if case=='max_message':raw=b'a'*262144
                expected_size=len(raw)
                size=262145 if case=='oversize_preflight' else expected_size
                tag+=1;command(f'CWR{tag:03} UID FETCH 45 (UID FLAGS RFC822.SIZE)')
                if case=='expunge':send('* 1 EXPUNGE');return
                if case!='missing_preflight':
                    uid=46 if case=='wrong_uid_preflight' else 45
                    send(f'* 2 FETCH (UID {uid} FLAGS (\\Seen) RFC822.SIZE {size})')
                send(f'CWR{tag:03} OK size')
                if case in ['oversize_preflight','wrong_uid_preflight','missing_preflight']:return
                tag+=1;command(f'CWR{tag:03} UID FETCH 45 (UID FLAGS RFC822.SIZE BODY.PEEK[])')
                if case=='oversize_literal':send('* 2 FETCH (UID 45 FLAGS () RFC822.SIZE 1 BODY[] {262145}');return
                if case=='malformed_literal':send('* 2 FETCH (UID 45 FLAGS () RFC822.SIZE 1 BODY[] {2+}');return
                if case!='missing_body':
                    uid=46 if case=='wrong_uid_body' else 45
                    size=expected_size+1 if case=='size_changed' else expected_size
                    payload=raw+b'x' if case=='size_mismatch' else raw
                    for unused in range(2 if case=='duplicate' else 1):
                        send(f'* 2 FETCH (UID {uid} FLAGS (\\Seen) RFC822.SIZE {size} BODY[] {{{len(payload)}}}')
                        if case=='truncated':s.sendall(payload[:4]);return
                        width=11 if case=='fragmented' else 2048
                        for i in range(0,len(payload),width):s.sendall(payload[i:i+width])
                        send(')')
                send(('WRONG' if case=='wrong_tag' else f'CWR{tag:03}')+' OK done')
                tag+=1;command(f'CWR{tag:03} LOGOUT')
            except (OSError,EOFError):pass # Expected when the client refuses hostile fixtures.
            except AssertionError as e:errors.append(str(e))
            finally:
                if s:s.close()
                listener.close()
        thread=threading.Thread(target=serve,daemon=True);thread.start()
        data={'port':port,'encryption':encryption,'host':'wrong.example.net' if case=='hostname' else 'inbox.example.net','ca':str(temp/('other.pem' if case=='untrusted' else 'ca.pem'))}
        if case=='slow':data['budget']=0.1
        if case.startswith('revoke'):data['revoke']=int(case[-1])
        started=time.monotonic();p=subprocess.run(['php',str(HERE/'message_tls_driver.php')],input=json.dumps(data),capture_output=True,text=True,timeout=8);elapsed=time.monotonic()-started
        thread.join(5);label=encryption+'/'+case
        check(label+' fixture exits without assertion failures',not thread.is_alive() and not errors)
        check(label+' PHP has no warning or leaked server detail',p.returncode==0 and p.stderr=='' and 'private rejection' not in p.stdout)
        result=json.loads(p.stdout);success=case in ['ok','max_message','fragmented']
        check(label+' expected bounded result',result['ok'] is success)
        check(label+' original numeric pin and strict TLS context',result['pinned'])
        if sni:check(label+' original hostname SNI',sni==[data['host']])
        check(label+' no mailbox writes or unbounded search',all(not any(' '+verb in c for verb in ['SELECT','SEARCH','STORE','APPEND','EXPUNGE','COPY','MOVE']) for c in commands))
        if not success:check(label+' finite error without upstream chain',result['reason'] in ['tls','protocol','limit','timeout','connection','upgrade','mechanism','authentication','access_changed','mailbox_changed'] and result['chain'] is False)
        if case in ['hostname','untrusted','expired','slow','eof','oversized','bare_lf','preauth','byte_flood','reply_flood','line_flood','deep','missing_upgrade','upgrade_injection','no_auth','revoke2']:
            check(label+' refuses before credentials','CREDENTIAL' not in commands)
        if case in ['revoke3','revoke4']:check(label+' revoked session never fetches',not any(' UID FETCH ' in c for c in commands))
        if case in ['oversize_preflight','wrong_epoch','empty','missing_preflight','wrong_uid_preflight','revoke5']:check(label+' refuses before body command',not any('BODY.PEEK[]' in c for c in commands))
        if case.startswith('revoke'):check(label+' current authorization required',result['reason']=='access_changed')
        if case in ['byte_flood','reply_flood','line_flood','deep','oversize_literal','oversize_preflight']:check(label+' finite resource limit',result['reason']=='limit')
        if case=='slow':check(label+' operation deadline enforced',elapsed<0.5 and result['reason']=='timeout')
        if success:
            raw=b'a'*262144 if case=='max_message' else b'Subject: Synthetic message\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nActual body'
            check(label+' exact identity byte count and payload hash',result['result']['uidvalidity']=='37' and result['result']['uid']=='45' and result['result']['bytes']==str(len(raw)) and result['result']['raw_bytes']==len(raw) and result['result']['raw_sha256']==hashlib.sha256(raw).hexdigest())
            check(label+' all six current authorization boundaries',result['calls']==6)
    for enc in ['ssl','starttls']:
        for case in ['ok','fragmented','max_message','wrong_auth','hostname','untrusted','expired','slow','eof','oversized','bare_lf','preauth','no_auth','byte_flood','reply_flood','line_flood','deep','oversize_literal','malformed_literal','expunge','duplicate','truncated','wrong_tag','revoke2','revoke3','revoke4','revoke5','revoke6','empty','wrong_epoch','oversize_preflight','missing_preflight','wrong_uid_preflight','missing_body','wrong_uid_body','size_changed','size_mismatch']:
            trial(enc,case)
        if enc=='starttls':
            for case in ['missing_upgrade','upgrade_injection']:trial(enc,case)
(ROOT/'qa').mkdir(exist_ok=True)
(ROOT/'qa/mail-message-tls-results.json').write_text(json.dumps({'scope':'Exact-message reader over real isolated TLS; no external mailbox, application or database acceptance','passed':True,'checks':CHECKS},indent=2)+'\n',encoding='utf-8')
print(len(CHECKS),'named exact-message TLS tests passed')
