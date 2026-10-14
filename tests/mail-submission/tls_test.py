"""Real loopback TLS with synthetic mail only; production options asserted before interception."""
from pathlib import Path
import base64,datetime,hashlib,json,os,socket,ssl,subprocess,sys,tempfile,threading,time
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import rsa
HERE=Path(__file__).resolve().parent;ROOT=HERE.parents[1]
RUNTIME=ROOT/'test-runtime';RUNTIME.mkdir(exist_ok=True);RESULTS=[];complete=False
NO_INI='--no-ini' in sys.argv
def check(name,ok):
    RESULTS.append({'name':name,'passed':bool(ok)});assert ok,name
def key():return rsa.generate_private_key(public_exponent=65537,key_size=2048)
def subject(name):return x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,name)])
def cert(k,name,issuer,signer,ca=False,expired=False):
    now=datetime.datetime.now(datetime.timezone.utc)
    b=x509.CertificateBuilder().subject_name(subject(name)).issuer_name(issuer).public_key(k.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(days=2)).not_valid_after(now+datetime.timedelta(days=-1 if expired else 2)).add_extension(x509.BasicConstraints(ca=ca,path_length=None),critical=True)
    if not ca:b=b.add_extension(x509.SubjectAlternativeName([x509.DNSName(name)]),critical=False)
    return b.sign(signer,hashes.SHA256())
try:
 with tempfile.TemporaryDirectory(prefix='cwp-submission-',dir=RUNTIME) as temp:
    temp=Path(temp).resolve();assert temp.is_relative_to(RUNTIME.resolve())
    cak=key();cac=cert(cak,'Synthetic SMTP CA',subject('Synthetic SMTP CA'),cak,True);(temp/'ca.pem').write_bytes(cac.public_bytes(serialization.Encoding.PEM))
    other=key();(temp/'other.pem').write_bytes(cert(other,'Other CA',subject('Other CA'),other,True).public_bytes(serialization.Encoding.PEM))
    for expired in [False,True]:
        k=key();stem='expired' if expired else 'server'
        (temp/(stem+'.pem')).write_bytes(cert(k,'smtp.example.net',cac.subject,cak,expired=expired).public_bytes(serialization.Encoding.PEM))
        (temp/(stem+'.key')).write_bytes(k.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
    def trial(encryption,case):
        listener=socket.socket();listener.bind(('127.0.0.1',0));listener.listen(1);listener.settimeout(6);port=listener.getsockname()[1]
        commands=[];errors=[];names=[];data=[];received_terminator=[]
        context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2
        context.set_servername_callback(lambda sock,name,ctx:names.append(name))
        stem='expired' if case=='expired' else 'server';context.load_cert_chain(str(temp/(stem+'.pem')),str(temp/(stem+'.key')))
        def serve():
            s=None
            try:
                s,_=listener.accept();s.settimeout(4)
                if encryption=='ssl':s=context.wrap_socket(s,server_side=True)
                def send(line):s.sendall((line+'\r\n').encode())
                def read():
                    buf=b''
                    while not buf.endswith(b'\r\n'):
                        chunk=s.recv(1)
                        if not chunk:raise EOFError()
                        buf+=chunk
                        if len(buf)>2048:raise AssertionError('fixture line overflow')
                    return buf[:-2].decode('ascii')
                def command(expected,label=None):
                    value=read();commands.append(label or value)
                    if value!=expected:raise AssertionError('unexpected fixture command')
                if case=='slow_greeting':time.sleep(.8);return
                if case=='eof_greeting':return
                if case=='oversized_greeting':send('220 '+('x'*600));return
                send('220 fixture');command('EHLO cajunwebpro.invalid')
                if encryption=='starttls':
                    send('250-fixture\r\n250 '+('NOUPGRADE' if case=='missing_upgrade' else 'STARTTLS'))
                    if case=='missing_upgrade':return
                    command('STARTTLS');send('220 ready'+('\r\n250 AUTH PLAIN' if case=='upgrade_injection' else ''))
                    s=context.wrap_socket(s,server_side=True);command('EHLO cajunwebpro.invalid')
                if case=='mixed_codes':send('250-fixture\r\n550 AUTH PLAIN');return
                if case=='reply_flood':send('250-fixture\r\n'*33+'250 AUTH PLAIN');return
                if case=='no_auth':send('250 fixture');return
                if case=='login':
                    send('250-fixture\r\n250 AUTH LOGIN');command('AUTH LOGIN');send('334 Username')
                    command(base64.b64encode(b'synthetic@example.net').decode(),'USERNAME');send('334 Password')
                    command(base64.b64encode(b'synthetic fixture password').decode(),'CREDENTIAL')
                else:
                    send('250-fixture\r\n250 AUTH PLAIN');command('AUTH PLAIN');send('334 ')
                    command(base64.b64encode(b'\0synthetic@example.net\0synthetic fixture password').decode(),'CREDENTIAL')
                send('535 rejected' if case=='wrong_auth' else '235 authenticated')
                if case=='wrong_auth':return
                command('MAIL FROM:<sender@example.invalid>');send('550 denied' if case=='sender_rejected' else '250 sender')
                if case=='sender_rejected':return
                for i,address in enumerate(['first','second','private'],1):
                    command(f'RCPT TO:<{address}@example.invalid>');send('550 denied' if case=='recipient'+str(i) else '250 recipient')
                    if case=='recipient'+str(i):
                        # Observe the peer close: rejected recipients must prevent DATA.
                        try:commands.append(read())
                        except EOFError:pass
                        return
                command('DATA');send('554 refused' if case=='data_rejected' else '354 continue')
                if case=='data_rejected':return
                if case=='disconnect_body':return
                while True:
                    line=read()
                    if line=='.':received_terminator.append(True);break
                    data.append((line[1:] if line.startswith('..') else line)+'\r\n')
                    if sum(map(len,data))>65536:raise AssertionError('fixture DATA cap')
                if case=='disconnect_final':return
                if case=='slow_final':time.sleep(.8);return
                if case=='malformed_final':send('not a reply');return
                if case=='truncated_final':send('250-incomplete');return
                if case=='mixed_final':send('250-pending\r\n550 conflict');return
                if case=='oversized_final':send('250 '+('x'*600));return
                send({'final450':'450 temporary','final550':'550 permanent','unexpected_final':'251 unexpected'}.get(case,'250 accepted'))
            except (ssl.SSLError,EOFError,ConnectionError,socket.timeout):pass
            except Exception as e:errors.append(type(e).__name__)
            finally:
                if s:s.close()
                listener.close()
        thread=threading.Thread(target=serve,daemon=True);thread.start();start=time.monotonic()
        request={'encryption':encryption,'port':port,'host':'wrong.example.net' if case=='hostname' else 'smtp.example.net','ca':str(temp/('other.pem' if case=='untrusted' else 'ca.pem')),'budget':.25 if case.startswith('slow_') else 4.0,'large':case=='large','revoke_at':2 if case=='revoked' else 0}
        p=subprocess.run([os.environ.get('PHP_BINARY','php'),*(['-n'] if NO_INI else []),str(HERE/'tls_driver.php')],input=json.dumps(request),capture_output=True,text=True,timeout=7)
        elapsed=time.monotonic()-start;thread.join(timeout=6);label=encryption+'/'+case
        check(label+' finite sanitized completion',p.returncode==0 and not p.stderr and not errors and not thread.is_alive())
        result=json.loads(p.stdout);check(label+' production pinned destination and TLS options',result['pinned'])
        o=result.get('outcome',{});status=o.get('status')
        if case in ['hostname','untrusted','expired']:
            check(label+' certificate refusal before credentials',result.get('open_failure')=='tls' if encryption=='ssl' else o.get('reason')=='tls')
        elif case in ['ok','login','large']:
            check(label+' verified acceptance',status=='accepted' and o['smtpCode']==250)
        elif case in ['final450','final550']:check(label+' explicit final refusal',status=='rejected')
        elif case in ['disconnect_final','slow_final','malformed_final','truncated_final','mixed_final','oversized_final','unexpected_final']:
            check(label+' uncertain final reply',status=='unknown' and o['phase']=='confirmation')
        elif case=='disconnect_body':check(label+' interrupted body never accepted',status in ['unknown','not_submitted'])
        else:check(label+' no completed submission',status=='not_submitted')
        if status in ['accepted','rejected'] or received_terminator:
            check(label+' received DATA exactly equals immutable prepared MIME',hashlib.sha256(''.join(data).encode()).hexdigest()==result['mime_sha256'])
        check(label+' Bcc stays out of message data','private@example.invalid' not in ''.join(data))
        check(label+' at most one DATA with no retry',commands.count('DATA')<=1 and len(received_terminator)<=1)
        if case.startswith('recipient'):check(label+' no DATA after recipient refusal','DATA' not in commands and not data)
        if case in ['hostname','untrusted','expired','missing_upgrade','upgrade_injection','no_auth','revoked']:
            check(label+' credentials never exposed','CREDENTIAL' not in commands)
        if case.startswith('slow_'):check(label+' overall deadline enforced',elapsed<1.5)
        if case not in ['slow_greeting','eof_greeting','oversized_greeting','missing_upgrade','upgrade_injection']:
            check(label+' original hostname used for TLS SNI',names==[request['host']])
    cases=['ok','large','login','hostname','untrusted','expired','slow_greeting','eof_greeting','oversized_greeting','mixed_codes','reply_flood','no_auth','revoked','wrong_auth','sender_rejected','recipient1','recipient2','recipient3','data_rejected','disconnect_body','disconnect_final','slow_final','malformed_final','truncated_final','mixed_final','oversized_final','unexpected_final','final450','final550']
    for encryption in ['ssl','starttls']:
        for case in cases+(['missing_upgrade','upgrade_injection'] if encryption=='starttls' else []):trial(encryption,case)
    complete=True
finally:
    out=ROOT/'qa';out.mkdir(exist_ok=True)
    (out/('submission-tls-no-ini-results.json' if NO_INI else 'submission-tls-results.json')).write_text(json.dumps({'passed':complete,'scope':'actual loopback SMTP/TLS with production policy interception, synthetic content only','no_ini':NO_INI,'checks':RESULTS},indent=2)+'\n',encoding='utf-8',newline='\n')
print(f'PASS: {len(RESULTS)} actual loopback SMTP/TLS checks')
