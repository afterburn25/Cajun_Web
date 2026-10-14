"""Synthetic loopback IMAP server for the unchanged packaged admin HTTP application."""
from pathlib import Path
import base64,datetime,json,socket,ssl,threading
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import rsa

class InboxServer:
    def __init__(self,root):
        self.root=Path(root)/'test-runtime/mail-inbox-http';self.root.mkdir(parents=True,exist_ok=True)
        self.mode='ok';self.commands=[];self.errors=[];self.connections=0;self.stopped=threading.Event()
        key=rsa.generate_private_key(public_exponent=65537,key_size=2048)
        name=x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,'inbox-fixture.example.net')]);now=datetime.datetime.now(datetime.timezone.utc)
        certificate=x509.CertificateBuilder().subject_name(name).issuer_name(name).public_key(key.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(days=1)).not_valid_after(now+datetime.timedelta(days=1)).add_extension(x509.SubjectAlternativeName([x509.DNSName('inbox-fixture.example.net')]),critical=False).add_extension(x509.BasicConstraints(ca=True,path_length=None),critical=True).sign(key,hashes.SHA256())
        cert=self.root/'server.pem';cert.write_bytes(certificate.public_bytes(serialization.Encoding.PEM));private=self.root/'server.key';private.write_bytes(key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
        self.context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);self.context.minimum_version=ssl.TLSVersion.TLSv1_2;self.context.load_cert_chain(str(cert),str(private))
        self.listener=socket.socket();self.listener.bind(('127.0.0.1',0));self.listener.listen(8);self.listener.settimeout(.2)
        (self.root/'config.json').write_text(json.dumps({'ca':str(cert),'port':self.listener.getsockname()[1]}))
        self.thread=threading.Thread(target=self.serve,daemon=True);self.thread.start()
    def serve(self):
        while not self.stopped.is_set():
            try:s,_=self.listener.accept()
            except socket.timeout:continue
            except OSError:return
            self.connections+=1;mode=self.mode
            try:
                s.settimeout(4);s=self.context.wrap_socket(s,server_side=True)
                def send(line):s.sendall((line+'\r\n').encode())
                def expect(expected,label=None):
                    data=b''
                    while not data.endswith(b'\r\n'):
                        part=s.recv(1)
                        if not part:raise EOFError()
                        data+=part
                        assert len(data)<4096
                    value=data[:-2].decode();assert value==expected,'Unexpected synthetic IMAP command';self.commands.append(label or value)
                send('* OK Synthetic HTTP inbox fixture');expect('CWR001 CAPABILITY');send('* CAPABILITY IMAP4rev1 AUTH=PLAIN');send('CWR001 OK done')
                expect('CWR002 AUTHENTICATE PLAIN');send('+ ');expect(base64.b64encode(b'\0synthetic@example.net\0Synthetic receiving HTTP password').decode(),'CREDENTIAL');send('CWR002 OK authenticated')
                expect('CWR003 EXAMINE "INBOX"');send('* '+('0' if mode=='empty' else '2')+' EXISTS');send('* OK [UIDVALIDITY '+('1235' if mode=='message-epoch' else '1234')+'] stable');send('CWR003 OK [READ-ONLY] examined')
                if mode.startswith('message'):
                    if mode=='message-epoch':continue
                    head='From: Synthetic sender <synthetic@example.net>\r\nSubject: SYNTHETIC PRIVATE MESSAGE <img src="https://untrusted.example.invalid/header">\r\nDate: Tue, 13 Oct 2026 12:00:00 +0000\r\nMIME-Version: 1.0\r\n'
                    formatted='<html><head><title>Hidden title</title><style>@import "https://untrusted.example.invalid/style";</style></head><body onload="window.mailInjected=1"><p>Formatted Café 👩‍💻</p><a href="https://external.example.invalid/reference">Referenced website</a><script>parent.mailInjected=1</script><img src="https://untrusted.example.invalid/pixel"><img src="/admin/index.php?view=settings"><form action="https://untrusted.example.invalid/form"><input name=x></form><svg onload="alert(1)"><text>hidden</text></svg></body></html>'
                    raw=(head+'Content-Type: multipart/alternative; boundary=m\r\n\r\n--m\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSYNTHETIC PRIVATE BODY\r\nSecond line 👩‍💻 <script>inert text</script>\r\n--m\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n'+formatted+'\r\n--m--\r\n').encode()
                    if mode=='message-attachment':raw=(head+'Content-Type: text/plain\r\nContent-Disposition: attachment; filename=secret.txt\r\n\r\nSYNTHETIC ATTACHMENT SECRET').encode()
                    if mode=='message-bad-mime':raw=b'No MIME header separator'
                    if mode=='message-html-only':raw=(head+'Content-Type: text/html; charset=UTF-8\r\n\r\n'+formatted).encode()
                    size=262145 if mode=='message-limit' else len(raw)
                    expect('CWR004 UID FETCH 82 (UID FLAGS RFC822.SIZE)');send(f'* 2 FETCH (UID 82 FLAGS () RFC822.SIZE {size})');send('CWR004 OK size')
                    if mode=='message-limit':continue
                    expect('CWR005 UID FETCH 82 (UID FLAGS RFC822.SIZE BODY.PEEK[])')
                    uid=81 if mode=='message-wrong-uid' else 82
                    send(f'* 2 FETCH (UID {uid} FLAGS () RFC822.SIZE {size} BODY[] {{{len(raw)}}}')
                    for at in range(0,len(raw),17):s.sendall(raw[at:at+17])
                    send(')');send('CWR005 OK fetched')
                    if mode!='message-wrong-uid':expect('CWR006 LOGOUT')
                    continue
                if mode!='empty':
                    expect('CWR004 FETCH 1:2 (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER])')
                    if mode=='limit':send('* 1 FETCH (UID 81 BODY[HEADER] {16385}');continue
                    for seq in [1,2]:
                        subject='SYNTHETIC PRIVATE Café <img src="https://untrusted.example.invalid/image" onerror="window.mailInjected=1">' if seq==2 else 'Earlier synthetic note'
                        header=('From: Synthetic sender <synthetic@example.net>\r\nSubject: '+subject+'\r\nDate: Tue, 13 Oct 2026 10:30:00 -0500\r\n\r\n').encode()
                        if mode=='malformed':header+=b'unexpected body'
                        flag='\\Seen' if seq==1 else '';send(f'* {seq} FETCH (UID {seq+80} FLAGS ({flag}) RFC822.SIZE 2048 BODY[HEADER] {{{len(header)}}}')
                        for at in range(0,len(header),17):s.sendall(header[at:at+17])
                        send(')')
                    send('CWR004 OK fetched')
                expect('CWR004 LOGOUT' if mode=='empty' else 'CWR005 LOGOUT')
            except (OSError,EOFError):pass
            except AssertionError as e:self.errors.append(str(e))
            finally:s.close()
    def close(self):
        self.stopped.set();self.listener.close();self.thread.join(5)
