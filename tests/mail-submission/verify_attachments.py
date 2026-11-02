"""Independent Python standard-library parsing of attachment-bearing MIME bytes."""
from pathlib import Path
import base64,email,email.policy,email.utils,json,sys
source=Path(sys.argv[1]);data=json.loads(source.read_text(encoding='utf-8'));assert data['passed'] is True
checks=[]
def check(name,condition):
    checks.append({'name':name,'passed':bool(condition)})
    assert condition,name
for fixture in data['messages']:
    name=fixture['name'];s=fixture['snapshot'];raw=base64.b64decode(fixture['mime_base64'],validate=True)
    message=email.message_from_bytes(raw,policy=email.policy.default)
    check(name+' independent parser has no MIME defects',not message.defects)
    check(name+' exact literal decoded subject',str(message['Subject'])==s['subject'])
    check(name+' fixed Message-ID',str(message['Message-ID'])==s['message_id'])
    check(name+' exact From',email.utils.getaddresses(message.get_all('From',[]))==[('',s['from'])])
    to=email.utils.getaddresses(message.get_all('To',[]));to=[address for _,address in to if address]
    check(name+' visible To unchanged',to==s['to'])
    check(name+' no hidden-recipient or extra authority headers',all(message.get(h) is None for h in ['Bcc','Resent-Bcc','Reply-To','Return-Path','Disposition-Notification-To','X-Mailer']))
    check(name+' message is multipart for attachments',message.is_multipart())
    found=[]
    body=None
    for part in message.walk():
        if part.is_multipart():continue
        if part.get_content_disposition()=='attachment':found.append(part)
        elif part.get_content_type()=='text/plain' and body is None:body=part
    expected=s['attachments']
    check(name+' declared descriptors match wire attachment count',len(found)==len(expected))
    for part,want in zip(found,expected):
        check(name+' wire filename matches sanitized name',part.get_filename()==want['name'])
        check(name+' wire content type matches declared type',part.get_content_type()==want['mime'])
        data_bytes=part.get_payload(decode=True)
        import hashlib
        check(name+' decoded bytes match stored checksum',hashlib.sha256(data_bytes).hexdigest()==want['sha256'] and len(data_bytes)==want['bytes'])
    check(name+' plaintext body survives alongside attachments',body is not None and body.get_payload(decode=True)==s['body'].replace('\n','\r\n').encode('utf-8'))
    check(name+' wire lines fit SMTP bounds',max(map(len,raw.split(b'\r\n')))<=998 and len(raw)<=786432)
    check(name+' header fields have no parser defects',all(not getattr(value,'defects',()) for _,value in message.items()))
    if s['format']==4:
        check(name+' copied message retains threading headers',message['In-Reply-To'] is not None and message['References'] is not None)
assert len(data['messages'])==3
result={'passed':True,'scope':'Independent standard-library MIME parse of synthetic attachment-bearing output only','checks':checks}
if len(sys.argv)>2:Path(sys.argv[2]).write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8',newline='\n')
print('PASS:',len(checks),'independent attachment MIME decoding checks')
