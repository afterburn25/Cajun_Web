"""Real loopback TLS, pinned production options, synthetic headers only. No external mailbox."""
from pathlib import Path
import base64,datetime,json,socket,ssl,subprocess,tempfile,threading,time
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
                tag+=1;command(f'CWR{tag:03} EXAMINE "INBOX"');total=0 if case=='empty' else 100 if case=='twenty_large' else 2
                send(f'* {total} EXISTS');send('* OK [UIDVALIDITY 37] stable');send(f'CWR{tag:03} OK [READ-ONLY] examined')
                if total:
                    tag+=1;first=max(1,total-19);command(f'CWR{tag:03} FETCH {first}:{total} (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER])')
                    if case=='oversize_literal':send('* 1 FETCH (UID 44 BODY[HEADER] {16385}');return
                    if case=='malformed_literal':send('* 1 FETCH (UID 44 BODY[HEADER] {2+}');return
                    if case=='expunge':send('* 1 EXPUNGE');return
                    for seq in range(first,total+1):
                        if case=='missing' and seq==total:continue
                        h=header(case=='twenty_large')
                        if case=='header_injection':h=b'Subject: private\r\n\r\n<script>body</script>'
                        uid=seq+43 if case!='duplicate' else 44
                        send(f'* {seq} FETCH (UID {uid} FLAGS (\\Seen) RFC822.SIZE 20000 BODY[HEADER] {{{len(h)}}}')
                        if case=='truncated':s.sendall(h[:4]);return
                        # Deliberately split literals across TLS records to exercise exact byte reads.
                        width=11 if case=='fragmented' else 2048
                        for i in range(0,len(h),width):s.sendall(h[i:i+width])
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
        started=time.monotonic();p=subprocess.run(['php',str(HERE/'tls_driver.php')],input=json.dumps(data),capture_output=True,text=True,timeout=8);elapsed=time.monotonic()-started
        thread.join(5);label=encryption+'/'+case
        check(label+' fixture exits without assertion failures',not thread.is_alive() and not errors)
        check(label+' PHP has no warning or leaked server detail',p.returncode==0 and p.stderr=='' and 'private rejection' not in p.stdout)
        result=json.loads(p.stdout);success=case in ['ok','empty','twenty_large','fragmented']
        check(label+' expected bounded result',result['ok'] is success)
        check(label+' original numeric pin and strict TLS context',result['pinned'])
        if sni:check(label+' original hostname SNI',sni==[data['host']])
        check(label+' no mailbox writes or unbounded search',all(not any(' '+verb in c for verb in ['SELECT','SEARCH','STORE','APPEND','EXPUNGE','COPY','MOVE']) for c in commands))
        if not success:check(label+' finite error without upstream chain',result['reason'] in ['tls','protocol','limit','timeout','connection','upgrade','mechanism','authentication','access_changed','mailbox_changed'] and result['chain'] is False)
        if case in ['hostname','untrusted','expired','slow','eof','oversized','bare_lf','preauth','byte_flood','reply_flood','line_flood','deep','missing_upgrade','upgrade_injection','no_auth','revoke2']:
            check(label+' refuses before credentials','CREDENTIAL' not in commands)
        if case in ['revoke3','revoke4']:check(label+' revoked session never fetches',not any(' FETCH ' in c for c in commands))
        if case.startswith('revoke'):check(label+' current authorization required',result['reason']=='access_changed')
        if case in ['byte_flood','reply_flood','line_flood','deep','oversize_literal']:check(label+' finite resource limit',result['reason']=='limit')
        if case=='slow':check(label+' operation deadline enforced',elapsed<0.5 and result['reason']=='timeout')
        if success:
            expected=0 if case=='empty' else 20 if case=='twenty_large' else 2
            check(label+' complete exact bounded batch',result['result']['uidvalidity']=='37' and len(result['result']['messages'])==expected)
            if expected:check(label+' decoded headers and newest identity',result['result']['messages'][0]['subject']=='Café' and result['result']['messages'][0]['uid']==('143' if case=='twenty_large' else '45'))
    for enc in ['ssl','starttls']:
        for case in ['ok','empty','fragmented','twenty_large','wrong_auth','hostname','untrusted','expired','slow','eof','oversized','bare_lf','preauth','no_auth','byte_flood','reply_flood','line_flood','deep','oversize_literal','malformed_literal','expunge','missing','duplicate','truncated','header_injection','wrong_tag','revoke2','revoke3','revoke4','revoke5']:
            trial(enc,case)
        if enc=='starttls':
            for case in ['missing_upgrade','upgrade_injection']:trial(enc,case)
(ROOT/'qa').mkdir(exist_ok=True)
(ROOT/'qa/mail-receiving-tls-results.json').write_text(json.dumps({'scope':'Real isolated TLS and synthetic mailbox headers; no external mailbox, application or database acceptance','passed':True,'checks':CHECKS},indent=2)+'\n',encoding='utf-8')
print(len(CHECKS),'named actual TLS/header tests passed')
