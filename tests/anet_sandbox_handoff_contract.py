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
print('Action-free sandbox handoff contract: PASS ('+str(len(checks))+' checks)')
