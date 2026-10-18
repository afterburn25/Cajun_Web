"""Loopback TLS fixture for the real packaged owner HTTP workflow; no provider API."""
from pathlib import Path
import base64,importlib.util,json,re,ssl,threading
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
        self.mode='success';self.write_mode='success';self.calls=0;self.errors=[];self.names=[];self.packages=[];self.posts=[];self.cloud_servers=[];self.cloud_mode='success';self.get_paths=[];self.domain_mode='success';self.registrar_mode='success';self.registrar_domains=[];self.cloud_providers=[];self.cloud_providers_mode='success';self.contract_dates={};self.contract_mode='success';self.expiry_dates={};self.expiry_mode='success';self.php_version='php82';self.php_version_mode='success';self.document_roots={};self.document_roots_mode='success';self.force_ssl=True;self.force_ssl_mode='success';self.directory_indexing=True;self.directory_indexing_mode='success';self.wp_installed=True;self.wp_installed_mode='success';fixture=self
        self.stackcp_membership=[];self.stackcp_grants=[]
        self.package_types=[];self.package_types_mode='success'
        self.renewal_mode='success';self.renewal_posts=[]
        self.period_mode='success';self.period_map={'.invalid':[1,3],'.co.uk':[1,2],'.com':[1,5]}
        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                fixture.calls+=1
                fixture.get_paths.append(self.path)
                expiry=re.fullmatch(r'/domain/([a-z0-9.-]+)/upstreamExpiryDate',self.path or '')
                contract=re.fullmatch(r'/domain/([a-z0-9.-]+)/contract',self.path or '')
                php_version_path=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/phpVersion',self.path or '')
                document_roots_path=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/documentRoots',self.path or '')
                force_ssl_path=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/forceSSL',self.path or '')
                directory_indexing_path=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/directoryIndexing',self.path or '')
                wp_installed_path=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/wordpressIsInstalled',self.path or '')
                stackcp_membership_path=re.fullmatch(r'/package/[1-9][0-9]{0,9}/stackUserList',self.path or '')
                stackcp_grants_path=re.fullmatch(r'/reseller/\*/stackUser/[1-9][0-9]{0,9}/grants',self.path or '')
                if (not stackcp_membership_path and not stackcp_grants_path and not contract and not expiry and not php_version_path and not document_roots_path and not force_ssl_path and not directory_indexing_path and not wp_installed_path and self.path not in ['/package','/cloud_server','/domain-search/example.invalid','/domain','/domain-period','/reseller/*/cloudProviders','/reseller/*/packageTypes']) or self.headers.get('Authorization')!='Bearer '+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode() or self.headers.get('Accept')!='application/json':
                    fixture.errors.append('Unexpected fixed request')
                mode=fixture.registrar_mode if self.path=='/domain' else (fixture.cloud_mode if self.path=='/cloud_server' else fixture.mode)
                status=401 if mode=='authentication' else 200
                listed=json.dumps(fixture.registrar_domains if self.path=='/domain' else (fixture.cloud_servers if self.path=='/cloud_server' else fixture.packages)).encode()
                body={'success':listed,'object':b'{}','authentication':b'private synthetic response','echo':b'["SYNTHETIC_RAW_GENERAL_KEY"]'}.get(mode,listed)
                if expiry:
                    if expiry.group(1) not in fixture.expiry_dates:fixture.errors.append('Unexpected expiry domain')
                    status=403 if fixture.expiry_mode=='authentication' else 200
                    body=(b'private expiry marker' if status==403 else json.dumps(None if fixture.expiry_mode=='null' else fixture.expiry_dates.get(expiry.group(1))).encode())
                if contract:
                    if contract.group(1) not in fixture.contract_dates:fixture.errors.append('Unexpected contract domain')
                    status=403 if fixture.contract_mode=='authentication' else 200
                    body=(b'private contract marker' if status==403 else json.dumps({'renewalDate':fixture.contract_dates.get(contract.group(1)),'contact':'CONTRACT_PRIVATE_MARKER'}).encode())
                if self.path=='/reseller/*/cloudProviders':
                    status=401 if fixture.cloud_providers_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.cloud_providers).encode(),'object':b'{}','authentication':b'private cloud provider response'}.get(fixture.cloud_providers_mode,b'["invalid",false]')
                if self.path=='/reseller/*/packageTypes':
                    status=401 if fixture.package_types_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.package_types).encode(),'object':b'{}','authentication':b'private synthetic catalog response'}.get(fixture.package_types_mode,b'null')
                if self.path=='/domain-search/example.invalid':
                    packets=[{'header':{'names':['example.invalid']}},{'name':'suggested.invalid','can':'register'},{'name':'unsold.com','can':'register'},{'name':'example.invalid','can':'register'}]
                    status=429 if fixture.domain_mode=='limited' else 200
                    body=('\n'.join(json.dumps(p) for p in (packets if fixture.domain_mode=='success' else packets[1:]))+'\n').encode()
                    if fixture.domain_mode=='limited':body=b'private synthetic domain response'
                if self.path=='/domain-period':
                    status=401 if fixture.period_mode=='authentication' else 200
                    body=json.dumps(fixture.period_map).encode() if status==200 else b'private synthetic period response'
                if php_version_path:
                    status=401 if fixture.php_version_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.php_version).encode(),'object':b'{"value":"php82"}','authentication':b'private synthetic PHP response'}.get(fixture.php_version_mode,b'null')
                if document_roots_path:
                    status=401 if fixture.document_roots_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.document_roots).encode(),'error_array':b'[]','empty_object':b'{}','authentication':b'private synthetic roots response'}.get(fixture.document_roots_mode,b'null')
                if force_ssl_path:
                    status=401 if fixture.force_ssl_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.force_ssl).encode(),'null':b'null','object':b'{"value":true}','authentication':b'private synthetic Force SSL response'}.get(fixture.force_ssl_mode,b'null')
                if directory_indexing_path:
                    status=401 if fixture.directory_indexing_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.directory_indexing).encode(),'null':b'null','object':b'{"value":true}','authentication':b'private synthetic Directory listing response'}.get(fixture.directory_indexing_mode,b'null')
                if wp_installed_path:
                    status=401 if fixture.wp_installed_mode=='authentication' else 200
                    body={'success':json.dumps(fixture.wp_installed).encode(),'null':b'null','object':b'{"value":true}','authentication':b'private synthetic WordPress installation response'}.get(fixture.wp_installed_mode,b'null')
                if stackcp_membership_path:status,body=200,json.dumps(fixture.stackcp_membership).encode()
                if stackcp_grants_path:status,body=200,json.dumps(fixture.stackcp_grants).encode()
                self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.send_header('Connection','close');self.end_headers();self.wfile.write(body)
            def do_POST(self):
                fixture.calls+=1
                length=int(self.headers.get('Content-Length') or '0')
                raw=self.rfile.read(length) if 0<length<=8192 else b''
                import re
                mailbox_path=re.fullmatch(r'/package/([1-9][0-9]*)/email/([a-z0-9.-]+)',self.path or '')
                force_ssl_write=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/forceSSL',self.path or '')
                directory_write=re.fullmatch(r'/package/[1-9][0-9]{0,17}/web/directoryIndexing',self.path or '')
                renewal_write=self.path=='/reseller/*/renewDomain'
                if not (renewal_write or force_ssl_write or directory_write or mailbox_path or re.fullmatch(r'(?:/reseller/[A-Za-z0-9_.*-]+/(addWeb|updatePackage|deleteWeb)|/cloud_server/[1-9][0-9]{0,17}/addWeb)',self.path or '')) or self.headers.get('Authorization')!='Bearer '+base64.b64encode(b'SYNTHETIC_RAW_GENERAL_KEY').decode() or self.headers.get('Content-Type')!='application/json' or self.headers.get('Accept')!='application/json':
                    fixture.errors.append('Unexpected write request '+str(self.path))
                try:payload=json.loads(raw)
                except Exception:payload=None;fixture.errors.append('Unparseable write body')
                if force_ssl_write and (not isinstance(payload,dict) or set(payload)!={'value'} or type(payload['value']) is not bool):fixture.errors.append('Unexpected Force SSL boolean write body')
                if directory_write and (not isinstance(payload,dict) or set(payload)!={'value'} or type(payload['value']) is not bool):fixture.errors.append('Unexpected directory listing boolean write body')
                fixture.posts.append({'path':self.path,'body':{'mailbox_local':payload.get('new',{}).get('mailbox',{}).get('local')} if mailbox_path and isinstance(payload,dict) else payload})
                if renewal_write:
                    valid=isinstance(payload,dict) and list(payload)==['name','years','renewPrivacy'] and isinstance(payload.get('name'),str) and re.fullmatch(r'[a-z0-9-]+(?:\.[a-z0-9-]+)+',payload['name']) and type(payload.get('years')) is int and 1<=payload['years']<=10 and type(payload.get('renewPrivacy')) is bool
                    if not valid:fixture.errors.append('Unexpected renewal scope body')
                    fixture.renewal_posts.append({'path':self.path,'body':payload})
                    status,body={'success':(200,b'true'),'failure':(200,b'false'),'null':(200,b'null'),'object':(200,b'{"result":true}'),'authentication':(401,b'private synthetic renewal response'),'uncertain':(202,b'true'),'echo':(200,b'"SYNTHETIC_RAW_GENERAL_KEY"')}[fixture.renewal_mode]
                    if not valid:status,body=400,b'{"error":"synthetic renewal scope refused"}'
                elif fixture.write_mode in ('success','uncertain_landed'):
                    status=200 if fixture.write_mode=='success' else 202;result={'id':100+len(fixture.packages)}
                    if self.path.endswith('/addWeb') and isinstance(payload,dict) and isinstance(payload.get('domain_name'),str):
                        name=payload['domain_name'];fixture.packages.append({'id':result['id'],'typeRef':int(payload.get('type',1)),'name':name,'names':[name]+(payload.get('extra_domain_names') or []),'enabled':True,'created':'2000-01-01T00:00:00Z'})
                    body=json.dumps({'result':result['id']} if fixture.write_mode=='success' and self.path.endswith('/addWeb') else result).encode()
                    if force_ssl_write or directory_write:
                        body=b'true'  # Synthetic unverified acknowledgement; never changes the GET fixture flag.
                    if mailbox_path:
                        mailbox=payload['new']['mailbox']
                        if set(payload)!= {'new'} or set(payload['new'])!={'mailbox'} or set(mailbox)!={'local','send','receive','password'} or mailbox['send']!='true' or mailbox['receive']!='true':fixture.errors.append('Unexpected mailbox body')
                        body=json.dumps({'result':{'result':[{'type':'mailbox','id':mailbox['local'],'generatedId':None,'password':mailbox['password']}],'name':mailbox_path.group(2)}}).encode()
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
