"""Loopback TLS fixture for the real packaged owner HTTP workflow; no provider API."""
from pathlib import Path
import base64,importlib.util,json,ssl,threading
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
from cryptography.hazmat.primitives import serialization

class ProviderServer:
    def __init__(self,root):
        runtime=(root/'test-runtime/provider-http').resolve()
        assert runtime.is_relative_to((root/'test-runtime').resolve())
        runtime.mkdir(parents=True,exist_ok=True)
        spec=importlib.util.spec_from_file_location('provider_tls_fixture',root/'tests/twentyi/tls_test.py')
        helpers=importlib.util.module_from_spec(spec);spec.loader.exec_module(helpers)
        ca=helpers.key();cac=helpers.cert(ca,'Provider HTTP fixture CA',helpers.subject('Provider HTTP fixture CA'),ca,True)
        key=helpers.key();certificate=helpers.cert(key,'api.20i.com',cac.subject,ca)
        (runtime/'ca.pem').write_bytes(cac.public_bytes(serialization.Encoding.PEM))
        (runtime/'server.pem').write_bytes(certificate.public_bytes(serialization.Encoding.PEM))
        (runtime/'server.key').write_bytes(key.private_bytes(serialization.Encoding.PEM,serialization.PrivateFormat.PKCS8,serialization.NoEncryption()))
        self.mode='success';self.write_mode='success';self.calls=0;self.errors=[];self.names=[];self.packages=[];self.posts=[];fixture=self
        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                fixture.calls+=1
                if self.path!='/package' or self.headers.get('Authorization')!='Bearer '+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode() or self.headers.get('Accept')!='application/json':
                    fixture.errors.append('Unexpected fixed request')
                status=401 if fixture.mode=='authentication' else 200
                listed=json.dumps(fixture.packages).encode()
                body={'success':listed,'object':b'{}','authentication':b'private synthetic response','echo':b'["SYNTHETIC_RAW_GENERAL_KEY"]'}.get(fixture.mode,listed)
                self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.send_header('Connection','close');self.end_headers();self.wfile.write(body)
            def do_POST(self):
                fixture.calls+=1
                length=int(self.headers.get('Content-Length') or '0')
                raw=self.rfile.read(length) if 0<length<=8192 else b''
                import re
                if not re.fullmatch(r'/reseller/[A-Za-z0-9_.*-]+/(addWeb|updatePackage|renewDomain)',self.path or '') or self.headers.get('Authorization')!='Bearer '+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode() or self.headers.get('Content-Type')!='application/json' or self.headers.get('Accept')!='application/json':
                    fixture.errors.append('Unexpected write request '+str(self.path))
                try:payload=json.loads(raw)
                except Exception:payload=None;fixture.errors.append('Unparseable write body')
                fixture.posts.append({'path':self.path,'body':payload})
                if fixture.write_mode in ('success','uncertain_landed'):
                    status=200 if fixture.write_mode=='success' else 202;result={'id':100+len(fixture.packages)}
                    if self.path.endswith('/addWeb') and isinstance(payload,dict) and isinstance(payload.get('domain_name'),str):
                        name=payload['domain_name'];fixture.packages.append({'id':result['id'],'typeRef':1,'name':name,'names':[name]+(payload.get('extra_domain_names') or []),'enabled':True,'created':'2000-01-01T00:00:00Z'})
                    body=json.dumps(result).encode()
                else:
                    status,body={'uncertain':(202,b'{"status":"accepted"}'),'failure':(400,b'{"error":"refused"}'),'authentication':(401,b'private synthetic response'),'echo':(200,b'"SYNTHETIC_RAW_GENERAL_KEY"')}[fixture.write_mode]
                self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.send_header('Connection','close');self.end_headers();self.wfile.write(body)
            def log_message(self,*args):pass
        self.server=ThreadingHTTPServer(('127.0.0.1',0),Handler)
        context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2
        context.load_cert_chain(str(runtime/'server.pem'),str(runtime/'server.key'))
        context.set_servername_callback(lambda sock,name,ctx:self.names.append(name))
        self.server.socket=context.wrap_socket(self.server.socket,server_side=True)
        (runtime/'config.json').write_text(json.dumps({'port':self.server.server_port,'ca':str(runtime/'ca.pem')}),encoding='utf-8')
        self.thread=threading.Thread(target=self.server.serve_forever,daemon=True);self.thread.start()
    def close(self):
        self.server.shutdown();self.server.server_close();self.thread.join(timeout=5)
