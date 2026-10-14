"""Real PHP/cURL TLS against synthetic loopback fixtures; never contacts 20i."""
from pathlib import Path
import base64, datetime, json, os, socket, ssl, subprocess, sys, tempfile, threading, time
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa

HERE=Path(__file__).resolve().parent; ROOT=HERE.parents[1]
RUNTIME=ROOT/'test-runtime'; RUNTIME.mkdir(exist_ok=True)
RESULTS=[]; LIMIT=1048576
NO_INI='--no-ini' in sys.argv[1:]
PHP=['php','-n'] if NO_INI else ['php']

def check(name,ok):
    RESULTS.append({'name':name,'passed':bool(ok)})
    assert ok,name

def key():return rsa.generate_private_key(public_exponent=65537,key_size=2048)
def subject(name):return x509.Name([x509.NameAttribute(NameOID.COMMON_NAME,name)])
def cert(k,name,issuer,signer,ca=False,expired=False):
    now=datetime.datetime.now(datetime.timezone.utc)
    builder=x509.CertificateBuilder().subject_name(subject(name)).issuer_name(issuer).public_key(k.public_key()).serial_number(x509.random_serial_number()).not_valid_before(now-datetime.timedelta(days=2)).not_valid_after(now+datetime.timedelta(days=-1 if expired else 2)).add_extension(x509.BasicConstraints(ca=ca,path_length=None),critical=True)
    if not ca:builder=builder.add_extension(x509.SubjectAlternativeName([x509.DNSName(name)]),critical=False)
    return builder.sign(signer,hashes.SHA256())

def run():
    with tempfile.TemporaryDirectory(prefix='twentyi-tls-',dir=RUNTIME) as temp:
        temp=Path(temp).resolve();assert temp.is_relative_to(RUNTIME.resolve())
        ca=key();cac=cert(ca,'Synthetic 20i fixture CA',subject('Synthetic 20i fixture CA'),ca,True)
        (temp/'ca.pem').write_bytes(cac.public_bytes(serialization.Encoding.PEM))
        other=key();(temp/'other.pem').write_bytes(cert(other,'Other fixture CA',subject('Other fixture CA'),other,True).public_bytes(serialization.Encoding.PEM))
        for stem,host,expired in [('server','api.20i.com',False),('wrong','wrong.invalid',False),('expired','api.20i.com',True)]:
            k=key();(temp/(stem+'.pem')).write_bytes(cert(k,host,cac.subject,ca,expired=expired).public_bytes(serialization.Encoding.PEM))
            (temp/(stem+'.key')).write_bytes(k.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))

        def trial(case,state='unknown',code='unexpected_response'):
            listener=socket.socket();listener.bind(('127.0.0.1',0));listener.listen(2);listener.settimeout(1)
            port=listener.getsockname()[1];requests=[];errors=[];server_names=[]
            proxy=socket.socket();proxy.bind(('127.0.0.1',0));proxy.listen(2);proxy.settimeout(.05)
            proxy_port=proxy.getsockname()[1];proxy_calls=[];proxy_stop=threading.Event()
            def proxy_canary():
                try:
                    while not proxy_stop.is_set():
                        try:
                            connection,_=proxy.accept();proxy_calls.append(True)
                            with connection:connection.sendall(b'HTTP/1.1 502 Fixture proxy\r\nContent-Length: 0\r\n\r\n')
                        except socket.timeout:pass
                finally:proxy.close()
            proxy_thread=threading.Thread(target=proxy_canary,daemon=True);proxy_thread.start()
            context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2
            stem={'hostname':'wrong','expired':'expired'}.get(case,'server')
            context.load_cert_chain(str(temp/(stem+'.pem')),str(temp/(stem+'.key')))
            context.set_servername_callback(lambda sock,name,ctx:server_names.append(name))
            def serve():
                connection=None
                try:
                    connection,_=listener.accept();connection.settimeout(3)
                    connection=context.wrap_socket(connection,server_side=True)
                    raw=b''
                    while b'\r\n\r\n' not in raw:
                        chunk=connection.recv(4096)
                        if not chunk:return
                        raw+=chunk
                        if len(raw)>8192:raise AssertionError('oversized synthetic request')
                    requests.append(raw)
                    def send(data):connection.sendall(data)
                    def response(status=200,body=b'[]',extra=b''):
                        send(b'HTTP/1.1 '+str(status).encode()+b' Fixture\r\nContent-Length: '+str(len(body)).encode()+b'\r\nConnection: close\r\n'+extra+b'\r\n'+body)
                    bodies={'object':b'{}','numeric_object':b'{"0":"synthetic"}','malformed':b'not json',
                        'deep':b'['*40+b']'*40,'invalid_utf8':b'["\xff"]','empty':b''}
                    if case in bodies:response(body=bodies[case])
                    elif case=='credential_echo':response(body=b'["SYNTHETIC_RAW_GENERAL_KEY"]')
                    elif case=='encoded_echo':response(body=b'["'+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY')+b'"]')
                    elif case in ['auth401','auth403','rate','server','redirect','partial_status']:
                        status={'auth401':401,'auth403':403,'rate':429,'server':503,'redirect':302,'partial_status':206}[case]
                        response(status,extra=b'Location: https://never-contact.invalid/\r\n')
                    elif case=='sensitive_headers':response(extra=b'Set-Cookie: synthetic-private-cookie\r\nX-Private: synthetic-private-header\r\n')
                    elif case=='chunked':send(b'HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n1\r\n[\r\n1\r\n]\r\n0\r\nX-Trailer: synthetic-private\r\n\r\n')
                    elif case=='exact_body':response(body=b'["'+b'x'*(LIMIT-4)+b'"]')
                    elif case=='oversized_body':response(body=b'["'+b'x'*(LIMIT-3)+b'"]')
                    elif case=='oversized_header':response(extra=b'X-Large: '+b'x'*17000+b'\r\n')
                    elif case=='interim_flood':
                        for _ in range(100):send(b'HTTP/1.1 100 Continue\r\nX-Pad: '+b'x'*200+b'\r\n\r\n')
                        response()
                    elif case=='partial_body':send(b'HTTP/1.1 200 OK\r\nContent-Length: 100\r\nConnection: close\r\n\r\n[]')
                    elif case=='malformed_http':send(b'not an HTTP response\r\n\r\n[]')
                    elif case=='slow_headers':time.sleep(1);response()
                    elif case=='slow_drip':
                        send(b'HTTP/1.1 200 OK\r\nContent-Length: 50\r\nConnection: close\r\n\r\n')
                        for _ in range(50):send(b' ');time.sleep(.1)
                    elif case=='stalled_body':
                        send(b'HTTP/1.1 200 OK\r\nContent-Length: 100\r\nConnection: close\r\n\r\n[');time.sleep(9)
                    else:response()
                except (ssl.SSLError,ConnectionError,TimeoutError,socket.timeout):pass
                except Exception as exc:errors.append(type(exc).__name__)
                finally:
                    if connection:connection.close()
                    listener.close()
            thread=threading.Thread(target=serve,daemon=True);thread.start()
            payload={'port':port,'ca':str(temp/('other.pem' if case=='untrusted' else 'ca.pem'))}
            if case=='ipv6_pin':payload['address']='2001:4860:4860::8888'
            if case in ['slow_headers','slow_drip']:payload['budget']=.35
            if case=='revoked_before':payload['revoke']=2
            if case=='revoked_after':payload['revoke']=3
            env=os.environ.copy();proxy_url='http://127.0.0.1:'+str(proxy_port)
            env.update({name:proxy_url for name in ['HTTPS_PROXY','https_proxy','HTTP_PROXY','http_proxy','ALL_PROXY','all_proxy']});env.update({'NO_PROXY':'','no_proxy':''})
            begin=time.monotonic()
            try:process=subprocess.run([*PHP,str(HERE/'tls_driver.php')],input=json.dumps(payload),encoding='utf-8',capture_output=True,env=env,timeout=18)
            finally:proxy_stop.set();proxy_thread.join(timeout=1)
            elapsed=time.monotonic()-begin;thread.join(timeout=5)
            check(case+' finite process and fixture completion',process.returncode==0 and not process.stderr and not errors and not thread.is_alive())
            result=json.loads(process.stdout)
            check(case+' exact classified result',result['state']==state and result['code']==code)
            check(case+' production fixed pin TLS proxy deadline and callback options',result['policy'])
            check(case+' no raw provider/header/credential disclosure',all(x not in process.stdout for x in ['SYNTHETIC_RAW_GENERAL_KEY','synthetic-private','never-contact.invalid']))
            check(case+' no retries or redirects',len(requests)<=1)
            check(case+' listening proxy canary receives zero connections',not proxy_calls and not proxy_thread.is_alive())
            if case in ['hostname','expired','untrusted','revoked_before']:
                check(case+' no HTTP credential request',not requests)
            else:
                check(case+' exactly one fixed credential request',len(requests)==1)
            if requests:
                lines=requests[0].split(b'\r\n');authorization=b'Authorization: Bearer '+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY')
                check(case+' actual fixed GET and exactly one authorization encoding',lines[0]==b'GET /package HTTP/1.1' and lines.count(authorization)==1 and b'\r\n\r\n'==requests[0][-4:])
                check(case+' actual original hostname Host header',b'Host: api.20i.com:'+str(port).encode() in lines)
            if server_names:check(case+' fixed hostname SNI',server_names==['api.20i.com'])
            if result['returned_headers'] is not None:check(case+' remote headers discarded',result['returned_headers']==0)
            if case in ['slow_headers','slow_drip']:check(case+' total deadline despite silence or trickle',elapsed<.9)
            if case=='stalled_body':check(case+' low-speed read deadline before total timeout',elapsed<8.5)
            if case=='revoked_before':check(case+' authority checked before any network call',result['authorizations']==2)
            elif case=='revoked_after':check(case+' changed authority refuses completed body',result['authorizations']==3 and result['returned_headers'] is None)

        for case in ['ok','sensitive_headers','chunked','exact_body','ipv6_pin']:trial(case,'verified','connected')
        for case in ['object','numeric_object','malformed','deep','invalid_utf8','empty']:trial(case)
        for case in ['auth401','auth403']:trial(case,'authentication_failed','authentication')
        for case,code in [('rate','rate_limited'),('server','remote_error'),('redirect','unexpected_status'),('partial_status','unexpected_status')]:trial(case,'unknown',code)
        for case in ['oversized_body','oversized_header','interim_flood','partial_body','malformed_http','credential_echo','encoded_echo']:trial(case,'unknown','protocol')
        for case in ['hostname','untrusted','expired']:trial(case,'unreachable','tls')
        for case in ['slow_headers','slow_drip','stalled_body']:trial(case,'unknown','timeout')
        for case in ['revoked_before','revoked_after']:trial(case,'denied','access_changed')

if __name__=='__main__':
    passed=False
    try:run();passed=True
    finally:
        (ROOT/'qa').mkdir(exist_ok=True)
        name='twentyi-tls-no-ini-results.json' if NO_INI else 'twentyi-tls-results.json'
        (ROOT/'qa'/name).write_text(json.dumps({'passed':passed,'php':PHP,'scope':'Actual PHP/cURL with production options checked before test-only loopback and CA remapping; no real provider call','checks':RESULTS},indent=2)+'\n',encoding='utf-8')
    print(len(RESULTS),'actual isolated 20i HTTP/TLS checks passed')
