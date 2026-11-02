"""Offline negative tests for the action-free sandbox payment runner handoff."""
import base64
import hashlib
import io
import json
from pathlib import Path
import sys
from unittest.mock import patch
import zipfile

sys.path.insert(0, str(Path(__file__).parent))
import anet_sandbox_handoff as h

checks = []
def check(name, good):
    if not good: raise AssertionError(name)
    checks.append(name)
def denied(name, fn):
    try: fn()
    except (RuntimeError,ValueError,zipfile.BadZipFile): checks.append(name)
    else: raise AssertionError(name)

def archived(name, payload=b'{"tokens":[]}'):
    stream=io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as z: z.writestr(name,payload)
    return stream.getvalue()

for kind in ('software','finance'):
    good=archived(kind+'-tokens.json')
    check(kind+' single artifact accepted',h.token_bytes(good,kind)==b'{"tokens":[]}')
    for variant in ('../other.json', 'wrong-tokens.json',kind+'-tokens.json/','nested/unsafe.json'):
        denied(kind+' rejects '+variant,lambda v=variant:h.token_bytes(archived(v),kind))
    denied(kind+' rejects non-zip',lambda:h.token_bytes(b'notzip',kind))
    stream=io.BytesIO()
    with zipfile.ZipFile(stream,'w') as z:
        z.writestr(kind+'-tokens.json','valid')
        z.writestr('extra','bad')
    denied(kind+' rejects extra members',lambda:h.token_bytes(stream.getvalue(),kind))
    denied(kind+' rejects decompressed oversized member',lambda:h.token_bytes(archived(kind+'-tokens.json',b'x'*65537),kind))
    identity={'id':333,'name':kind+'-anet-nonces-321-1','expired':False,'size_in_bytes':len(good),'workflow_run':{'id':321},'archive_download_url':h.API+'/actions/artifacts/333/zip'}
    with patch.object(h,'get_json',return_value={'artifacts':[identity]}),patch.object(h,'artifact_zip',return_value=good):
        check(kind+' binds artifact to run and attempt',h.download_handoff(kind,'321','1','demo')==b'{"tokens":[]}')
    for key,value in [('expired',True),('size_in_bytes',h.MAX_ZIP+1),('workflow_run',{'id':999}),('archive_download_url','https://example.invalid/zip')]:
        change={**identity,key:value}
        with patch.object(h,'get_json',return_value={'artifacts':[change]}):
            denied(kind+' rejects conflicting '+key,lambda:h.download_handoff(kind,'321','1','demo'))
    with patch.object(h,'get_json',return_value={'artifacts':[identity,identity]}):
        denied(kind+' rejects duplicate artifact',lambda:h.download_handoff(kind,'321','1','demo'))

path='tests/anet_sandbox_runtime.py'
data=b'proof of immutable reviewed data'
object_sha=hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
obj={'path':path,'type':'file','encoding':'base64','content':base64.b64encode(data).decode(),'size':len(data),'sha':object_sha}
with patch.object(h,'get_json',return_value=obj):
    check('matching Git blob accepted',h.file_from_commit(path,'a'*40,'demo')==data)
for field,value in [('path','tests/unauthorized.py'),('size',len(data)+1),('sha','b'*40),('encoding','utf8')]:
    with patch.object(h,'get_json',return_value={**obj,field:value}):
        denied('reject source mismatch: '+field,lambda:h.file_from_commit(path,'a'*40,'demo'))
denied('reject nonallowlisted source',lambda:h.file_from_commit('tests/unknown.py','a'*40,'demo'))
check('no redirects for GitHub artifact API',h.NoRedirect().redirect_request(None,None,302,'','', 'https://example.invalid') is None)

# Valid signed GitHub Actions artifact URLs use GitHub's dedicated Azure blob
# storage account names or its documented results receiver. Neither any HTTPS
# host nor any Azure blob account is sufficient.
good_signed = "https://productionresultssa12.blob.core.windows.net/actions-results/proof.zip?sig=opaque"
for url in (
    good_signed,
    "https://productionresultssa100.blob.core.windows.net/actions-results/proof.zip?sig=opaque",
    "https://results-receiver.actions.githubusercontent.com/action-results/proof?token=opaque",
):
    check("approved artifact storage " + url.split("/")[2],h.approved_artifact_redirect(url))
for url in (
    "https://attacker.example/receive?sig=opaque",
    "https://productionresultssa12.blob.core.windows.net.attacker.example/file?sig=opaque",
    "https://evilaccount.blob.core.windows.net/actions-results/file?sig=opaque",
    "https://productionresultssa12.blob.core.windows.net:444/actions-results/proof?sig=opaque",
    "http://productionresultssa12.blob.core.windows.net/actions-results/proof?sig=opaque",
    "https://user:secret@productionresultssa12.blob.core.windows.net/actions-results/file?sig=opaque",
    "https://productionresultssa12.blob.core.windows.net/actions-results/proof.zip",
    "https://productionresultssa12.blob.core.windows.net/actions-results/file?sig=opaque#fragment",
    "https://productionresultssa12.blob.core.windows.net.attacker.example:443/artifact?sig=opaque",
    "https://productionresultssa9999.blob.core.windows.net/actions-results/file?sig=opaque",
):
    denied("reject untrusted artifact redirect " + url.split("/")[2],
           lambda value=url: (None if h.approved_artifact_redirect(value) else (_ for _ in ()).throw(RuntimeError("rejected"))))

class StubSignedResponse:
    def __init__(self, url, data):
        self.url, self.data = url, data
    def __enter__(self): return self
    def __exit__(self, *_): return False
    def geturl(self): return self.url
    def read(self, maxbytes): return self.data[:maxbytes]

class StubOpener:
    def __init__(self, location, signed_status=200):
        self.location=location
        self.calls=[]
        self.signed_status=signed_status
    def open(self,request,timeout=20):
        self.calls.append(request)
        if len(self.calls)==1:
            raise h.urllib.error.HTTPError(request.full_url,302,"Moved",{"Location":self.location},None)
        if self.signed_status==302:
            raise h.urllib.error.HTTPError(request.full_url,302,"Moved",{"Location":"https://attacker.example/"},None)
        return StubSignedResponse(self.location,b"artifact-bytes")

artifact_url=h.API+"/actions/artifacts/333/zip"
opener=StubOpener(good_signed)
with patch.object(h.urllib.request,"build_opener",return_value=opener):
    check("artifact ZIP read from approved storage",
          h.artifact_zip(artifact_url,"secret-read-token")==b"artifact-bytes")
check("GitHub bearer stays only on original API request",
      opener.calls[0].get_header("Authorization")=="Bearer secret-read-token"
      and opener.calls[1].get_header("Authorization") is None
      and opener.calls[1].full_url==good_signed)

unapproved="https://attacker.example/steal?sig=opaque"
opener=StubOpener(unapproved)
with patch.object(h.urllib.request,"build_opener",return_value=opener):
    denied("reject bad signed redirect before requesting attacker host",
           lambda:h.artifact_zip(artifact_url,"secret-read-token"))
check("unapproved signed host never requested",len(opener.calls)==1)

opener=StubOpener(good_signed,signed_status=302)
with patch.object(h.urllib.request,"build_opener",return_value=opener):
    denied("reject chained redirect even from approved storage",
           lambda:h.artifact_zip(artifact_url,"secret-read-token"))
check("no third-party request after storage redirect",len(opener.calls)==2)
print('Action-free sandbox handoff contract: PASS ('+str(len(checks))+' checks)')
