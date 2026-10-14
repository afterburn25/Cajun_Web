"""Independent Python standard-library parsing of actual PHPMailer-produced bytes."""
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
    check(name+' fixed UTC Date',int(email.utils.parsedate_to_datetime(str(message['Date'])).timestamp())==s['prepared_epoch'])
    check(name+' exact From',email.utils.getaddresses(message.get_all('From',[]))==[('',s['from'])])
    to=email.utils.getaddresses(message.get_all('To',[]));to=[address for _,address in to if address]
    check(name+' visible To unchanged',to==s['to'])
    check(name+' visible Cc unchanged',[address for _,address in email.utils.getaddresses(message.get_all('Cc',[]))]==s['cc'])
    check(name+' no hidden-recipient or extra authority headers',all(message.get(h) is None for h in ['Bcc','Resent-Bcc','Reply-To','Return-Path','Disposition-Notification-To','X-Mailer']))
    check(name+' single plaintext UTF8 part',not message.is_multipart() and message.get_content_type()=='text/plain' and message.get_content_charset()=='utf-8')
    check(name+' base64 body preserves every decoded byte',message.get_payload(decode=True)==s['body'].replace('\n','\r\n').encode('utf-8'))
    check(name+' wire lines fit SMTP bounds',max(map(len,raw.split(b'\r\n')))<=998 and len(raw)<=65536)
    check(name+' header fields have no parser defects',all(not getattr(value,'defects',()) for _,value in message.items()))
assert len(data['messages'])==12
result={'passed':True,'scope':'Independent standard-library MIME parse of synthetic maintained-library output only','checks':checks}
if len(sys.argv)>2:Path(sys.argv[2]).write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8',newline='\n')
print('PASS:',len(checks),'independent MIME decoding/header/privacy checks')
