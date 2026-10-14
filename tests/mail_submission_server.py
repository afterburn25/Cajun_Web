"""Reusable synthetic loopback SMTP fixture; no external mail or DNS."""
from pathlib import Path
import base64,datetime,json,socket,ssl,threading,time
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import rsa

class SubmissionServer:
    host='submission-fixture.example.net'
    username='synthetic-submission@example.invalid'
    password='Synthetic combined SMTP password'
    def __init__(self,root):
        self.root=(Path(root)/'test-runtime/mail-submission').resolve()
        assert self.root.is_relative_to((Path(root)/'test-runtime').resolve())
        self.root.mkdir(parents=True,exist_ok=True)
        self.mode='accepted';self.encryption='ssl';self.connections=0;self.attempts=[];self.errors=[]
        self.stopped=threading.Event();self.idle=threading.Event();self.idle.set()
        key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
        name=x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,self.host)]);now=datetime.datetime.now(datetime.timezone.utc)
        certificate=x509.CertificateBuilder().subject_name(name).issuer_name(name).public_key(key.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(days=1)).not_valid_after(now+datetime.timedelta(days=1)).add_extension(x509.SubjectAlternativeName([x509.DNSName(self.host)]),critical=False).add_extension(x509.BasicConstraints(ca=True,path_length=None),critical=True).sign(key,hashes.SHA256())
        cert=self.root/'server.pem';cert.write_bytes(certificate.public_bytes(serialization.Encoding.PEM));private=self.root/'server.key';private.write_bytes(key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
        self.context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);self.context.minimum_version=ssl.TLSVersion.TLSv1_2;self.context.load_cert_chain(str(cert),str(private))
        self.listener=socket.socket();self.listener.bind(('127.0.0.1',0));self.listener.listen(8);self.listener.settimeout(.2)
        self.config={'ca':str(cert),'port':self.listener.getsockname()[1]}
        (self.root/'config.json').write_text(json.dumps(self.config),encoding='utf-8')
        self.thread=threading.Thread(target=self.serve,daemon=True);self.thread.start()
    def configure(self,encryption='ssl',mode='accepted'):
        assert self.idle.wait(6),'Previous SMTP fixture still active'
        assert encryption in ('ssl','starttls')
        assert mode in ('accepted','authentication','sender','recipient','data','final450','final550','disconnect_final','malformed_final','disconnect_body')
        self.encryption=encryption;self.mode=mode
    def serve(self):
        while not self.stopped.is_set():
            try:s,_=self.listener.accept()
            except socket.timeout:continue
            except OSError:return
            self.idle.clear();self.connections+=1;mode=self.mode;encryption=self.encryption
            attempt={'mode':mode,'encryption':encryption,'commands':[],'data':b'','terminator':False,'authenticated':False,'recipients':[]}
            self.attempts.append(attempt)
            try:
                s.settimeout(5)
                if encryption=='ssl':s=self.context.wrap_socket(s,server_side=True)
                def send(line):s.sendall((line+'\r\n').encode('ascii'))
                def read():
                    buf=b''
                    while not buf.endswith(b'\r\n'):
                        part=s.recv(1)
                        if not part:raise EOFError()
                        buf+=part
                        assert len(buf)<=2048,'Synthetic SMTP line overflow'
                    return buf[:-2].decode('ascii')
                def expect(value,label=None):
                    actual=read();attempt['commands'].append(label or actual)
                    assert actual==value,'Unexpected synthetic SMTP command'
                send('220 Synthetic isolated SMTP');expect('EHLO cajunwebpro.invalid')
                if encryption=='starttls':
                    send('250-fixture\r\n250 STARTTLS');expect('STARTTLS');send('220 Upgrade now')
                    s=self.context.wrap_socket(s,server_side=True);expect('EHLO cajunwebpro.invalid')
                send('250-fixture\r\n250 AUTH PLAIN');expect('AUTH PLAIN');send('334 ')
                expect(base64.b64encode(('\0'+self.username+'\0'+self.password).encode()).decode(),'CREDENTIAL')
                send('535 Synthetic rejection' if mode=='authentication' else '235 Authenticated')
                if mode=='authentication':continue
                attempt['authenticated']=True
                expect('MAIL FROM:<sender@example.invalid>');send('550 Sender refused' if mode=='sender' else '250 Sender accepted')
                if mode=='sender':continue
                for address in ['first@example.invalid','copy@example.invalid','private@example.invalid']:
                    expect('RCPT TO:<'+address+'>');attempt['recipients'].append(address)
                    refused=mode=='recipient' and address=='copy@example.invalid'
                    send('550 Recipient refused' if refused else '250 Recipient accepted')
                    if refused:
                        # A maintained adapter must close without issuing DATA after any refused recipient.
                        try:attempt['commands'].append(read())
                        except EOFError:pass
                        break
                if mode=='recipient':continue
                expect('DATA');send('554 DATA refused' if mode=='data' else '354 Continue')
                if mode in ('data','disconnect_body'):continue
                while True:
                    line=read()
                    if line=='.':attempt['terminator']=True;break
                    attempt['data']+=(line[1:] if line.startswith('..') else line).encode()+b'\r\n'
                    assert len(attempt['data'])<=65536,'Synthetic SMTP DATA overflow'
                if mode=='disconnect_final':continue
                send({'final450':'450 Temporary rejection','final550':'550 Permanent rejection','malformed_final':'Invalid final reply'}.get(mode,'250 Accepted responsibility'))
            except (EOFError,ConnectionError,socket.timeout,ssl.SSLError):pass
            except Exception as error:self.errors.append(type(error).__name__+': '+str(error))
            finally:
                s.close();self.idle.set()
    def close(self):
        self.stopped.set();self.listener.close();self.thread.join(timeout=7)
        assert not self.thread.is_alive(),'SMTP fixture failed to stop'
