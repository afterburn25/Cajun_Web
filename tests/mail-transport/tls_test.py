"""Real loopback TLS and synthetic credentials only; no external mailbox or message."""
from pathlib import Path
import base64,datetime,json,socket,ssl,subprocess,tempfile,threading,time
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import rsa

HERE=Path(__file__).resolve().parent; ROOT=HERE.parents[1]
RUNTIME=ROOT/'test-runtime';RUNTIME.mkdir(exist_ok=True)
RESULTS=[]
def check(name,ok):
    RESULTS.append({'name':name,'passed':bool(ok)})
    assert ok,name
def key():return rsa.generate_private_key(public_exponent=65537,key_size=2048)
def subject(name):return x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,name)])
def cert(k,name,issuer,signer,ca=False,expired=False):
    now=datetime.datetime.now(datetime.timezone.utc)
    b=x509.CertificateBuilder().subject_name(subject(name)).issuer_name(issuer).public_key(k.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(days=2)).not_valid_after(now+datetime.timedelta(days=-1 if expired else 2)).add_extension(x509.BasicConstraints(ca=ca,path_length=None),critical=True)
    if not ca:b=b.add_extension(x509.SubjectAlternativeName([x509.DNSName(name)]),critical=False)
    return b.sign(signer,hashes.SHA256())

with tempfile.TemporaryDirectory(prefix='cwp-tls-',dir=RUNTIME) as temp:
    temp=Path(temp).resolve();assert temp.is_relative_to(RUNTIME.resolve())
    cak=key();cac=cert(cak,'Synthetic test CA',subject('Synthetic test CA'),cak,True);(temp/'ca.pem').write_bytes(cac.public_bytes(serialization.Encoding.PEM))
    other=key();(temp/'other.pem').write_bytes(cert(other,'Other test CA',subject('Other test CA'),other,True).public_bytes(serialization.Encoding.PEM))
    for expired in [False,True]:
        k=key();stem='expired' if expired else 'server'
        (temp/(stem+'.pem')).write_bytes(cert(k,'probe.example.net',cac.subject,cak,expired=expired).public_bytes(serialization.Encoding.PEM))
        (temp/(stem+'.key')).write_bytes(k.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
    def trial(protocol,encryption,case='ok'):
        listener=socket.socket();listener.bind(('127.0.0.1',0));listener.listen(1);listener.settimeout(6);port=listener.getsockname()[1];commands=[];errors=[]
        context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2
        stem='expired' if case=='expired' else 'server';context.load_cert_chain(str(temp/(stem+'.pem')),str(temp/(stem+'.key')))
        def serve():
            s=None
            try:
                s,_=listener.accept();s.settimeout(5)
                if encryption=='ssl':s=context.wrap_socket(s,server_side=True)
                def send(line):s.sendall((line+'\r\n').encode())
                def read():
                    data=b''
                    while not data.endswith(b'\r\n'):
                        chunk=s.recv(1)
                        if not chunk:raise EOFError()
                        data+=chunk
                        if len(data)>4096:raise AssertionError('fixture command limit')
                    return data[:-2].decode()
                def command(expected,label=None):
                    value=read();commands.append(label or value)
                    if value!=expected:raise AssertionError('unexpected diagnostic command')
                if case=='slow':time.sleep(1);return
                if case=='oversized':send('X'*9000);return
                if protocol=='smtp':
                    send('220 synthetic');command('EHLO cajunwebpro.invalid')
                    if encryption=='starttls':
                        send('250-synthetic\r\n250 '+('NOUPGRADE' if case=='missing_upgrade' else 'STARTTLS'))
                        if case=='missing_upgrade':return
                        command('STARTTLS');send('220 ready');s=context.wrap_socket(s,server_side=True);command('EHLO cajunwebpro.invalid')
                    if case=='mixed_codes':send('250-synthetic\r\n550 AUTH PLAIN');return
                    if case=='no_auth':send('250 synthetic');return
                    send('250-synthetic\r\n250 AUTH PLAIN');command('AUTH PLAIN');send('334 ')
                    command(base64.b64encode(b'\0synthetic@example.net\0synthetic fixture password').decode(),'CREDENTIAL')
                    send('535 rejected' if case=='wrong_auth' else '235 authenticated')
                    if case!='wrong_auth':command('QUIT')
                else:
                    send('* PREAUTH synthetic' if case=='preauth' else '* OK synthetic')
                    if case=='preauth':return
                    tag=1;command(f'CWP{tag:03} CAPABILITY');send(f'* CAPABILITY IMAP4rev1 AUTH=PLAIN'+(' STARTTLS' if case!='missing_upgrade' else '')+f'\r\nCWP{tag:03} OK done');tag+=1
                    if encryption=='starttls':
                        if case=='missing_upgrade':return
                        command(f'CWP{tag:03} STARTTLS');send(f'CWP{tag:03} OK upgrade');tag+=1;s=context.wrap_socket(s,server_side=True)
                        command(f'CWP{tag:03} CAPABILITY');send(f'* CAPABILITY IMAP4rev1 AUTH=PLAIN\r\nCWP{tag:03} OK done');tag+=1
                    command(f'CWP{tag:03} AUTHENTICATE PLAIN');send('+ ')
                    command(base64.b64encode(b'\0synthetic@example.net\0synthetic fixture password').decode(),'CREDENTIAL')
                    if case=='wrong_tag':send('OTHER OK authenticated');return
                    send(f'CWP{tag:03} '+('NO rejected' if case=='wrong_auth' else 'OK authenticated'));tag+=1
                    if case!='wrong_auth':command(f'CWP{tag:03} LOGOUT')
            except (ssl.SSLError,EOFError,ConnectionError,socket.timeout):pass
            except Exception as e:errors.append(type(e).__name__)
            finally:
                if s:s.close()
                listener.close()
        thread=threading.Thread(target=serve,daemon=True);thread.start();start=time.monotonic()
        data={'protocol':protocol,'encryption':encryption,'port':port,'host':'wrong.example.net' if case=='hostname' else 'probe.example.net','ca':str(temp/('other.pem' if case=='untrusted' else 'ca.pem')),'budget':.25 if case=='slow' else 4,'revoke':case=='revoked'}
        p=subprocess.run(['php',str(HERE/'tls_driver.php')],input=json.dumps(data),capture_output=True,text=True,timeout=7)
        elapsed=time.monotonic()-start;thread.join(timeout=6);check(f'{protocol}/{encryption}/{case} finite sanitized output',p.returncode==0 and not p.stderr and not errors and not thread.is_alive())
        result=json.loads(p.stdout);check(f'{protocol}/{encryption}/{case} actual TLS outcome',result['ok']==(case=='ok'))
        check(f'{protocol}/{encryption}/{case} production IP pinning and verified TLS options',result['pinned'])
        check(f'{protocol}/{encryption}/{case} no message operations',all(not c.startswith(('MAIL','RCPT','DATA','SELECT','FETCH','APPEND','STORE','EXPUNGE')) for c in commands))
        if case in ['hostname','untrusted','expired','missing_upgrade','preauth','mixed_codes','no_auth','slow','oversized','revoked']:check(f'{protocol}/{encryption}/{case} no credentials before valid negotiation','CREDENTIAL' not in commands)
        if case=='slow':check(f'{protocol}/{encryption}/{case} operation budget',elapsed<1)
    for proto in ['imap','smtp']:
        for enc in ['ssl','starttls']:
            for scenario in ['ok','wrong_auth','hostname','untrusted','expired','slow','oversized','revoked']:
                trial(proto,enc,scenario)
            if enc=='starttls':trial(proto,enc,'missing_upgrade')
        for scenario in (['preauth','wrong_tag'] if proto=='imap' else ['mixed_codes','no_auth']):trial(proto,'ssl',scenario)
(ROOT/'qa').mkdir(exist_ok=True)
(ROOT/'qa/mail-transport-tls-results.json').write_text(json.dumps({'passed':True,'checks':RESULTS},indent=2)+'\n')
print(len(RESULTS),'real isolated TLS/protocol checks passed')
