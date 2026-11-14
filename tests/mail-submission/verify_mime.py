"""Independent Python standard-library parsing of actual PHPMailer-produced bytes."""
from pathlib import Path
import base64,email,email.policy,email.utils,hashlib,json,sys

source=Path(sys.argv[1]);data=json.loads(source.read_text(encoding='utf-8'));assert data['passed'] is True
checks=[]
def check(name,condition):
    checks.append({'name':name,'passed':bool(condition)})
    assert condition,name

expected={*(f'format-{number}' for number in range(1,9)),'fallback-empty','fallback-zero','maximum-thread','maximum-shape',*(f'subject-{number}' for number in range(10)),'long-recipients','bcc-only','cc-only','literal-dots','long-thread'}
check('complete fixture family',len(data['messages'])==27 and {fixture['name'] for fixture in data['messages']}==expected)
for fixture in data['messages']:
    name=fixture['name'];s=fixture['snapshot'];raw=base64.b64decode(fixture['mime_base64'],validate=True)
    message=email.message_from_bytes(raw,policy=email.policy.default)
    check(name+' independent parser has no MIME defects',not message.defects)
    check(name+' exact literal decoded subject',str(message['Subject'])==s['subject'])
    check(name+' fixed Message-ID',str(message['Message-ID'])==s['message_id'])
    check(name+' fixed UTC Date',int(email.utils.parsedate_to_datetime(str(message['Date'])).timestamp())==s['prepared_epoch'])
    check(name+' exact From',email.utils.getaddresses(message.get_all('From',[]))==[('',s['from'])])
    to=[address for _,address in email.utils.getaddresses(message.get_all('To',[])) if address]
    check(name+' visible To unchanged',to==s['to'])
    check(name+' visible Cc unchanged',[address for _,address in email.utils.getaddresses(message.get_all('Cc',[]))]==s['cc'])
    check(name+' no hidden-recipient or extra authority headers',all(message.get(h) is None for h in ['Bcc','Resent-Bcc','Reply-To','Return-Path','Disposition-Notification-To','X-Mailer']))
    header=raw.split(b'\r\n\r\n',1)[0]
    check(name+' hidden envelope addresses absent from headers',all(address.encode('ascii') not in header for address in s['bcc']))
    thread=s.get('thread')
    if thread:
        check(name+' exact literal In-Reply-To',str(message['In-Reply-To'])==thread['in_reply_to'])
        check(name+' exact unfolded References',str(message['References'])==' '.join(thread['references']))
        check(name+' thread fields are not encoded words',b'In-Reply-To: =?' not in header and b'References: =?' not in header)
    else:
        check(name+' no unsolicited thread headers',message.get('In-Reply-To') is None and message.get('References') is None)

    leaves=[part for part in message.walk() if not part.is_multipart()]
    attachments=[part for part in leaves if part.get_content_disposition()=='attachment']
    body_parts=[part for part in leaves if part.get_content_disposition()!='attachment']
    expected_attachments=s.get('attachments',[])
    actual_attachments=[{'name':part.get_filename(),'mime':part.get_content_type(),'sha256':hashlib.sha256(part.get_payload(decode=True)).hexdigest(),'bytes':len(part.get_payload(decode=True))} for part in attachments]
    check(name+' attachment bytes, metadata, and order',actual_attachments==expected_attachments)
    if expected_attachments:
        check(name+' attachments use mixed topology',message.get_content_type()=='multipart/mixed')
    expected_plain=s['body'].replace('\n','\r\n').encode('utf-8')
    plain=[part for part in body_parts if part.get_content_type()=='text/plain']
    html=[part for part in body_parts if part.get_content_type()=='text/html']
    check(name+' exact UTF8 plaintext fallback',len(plain)==1 and plain[0].get_content_charset()=='utf-8' and plain[0].get_payload(decode=True)==expected_plain)
    if 'body_html' in s:
        expected_html=s['body_html'].replace('\n','\r\n').encode('utf-8')
        check(name+' exact UTF8 HTML body',len(html)==1 and html[0].get_content_charset()=='utf-8' and html[0].get_payload(decode=True)==expected_html)
        check(name+' formatted body has alternative topology',any(part.get_content_type()=='multipart/alternative' for part in message.walk()))
    else:
        check(name+' no unsolicited HTML body',html==[])
    check(name+' wire is canonical CRLF with bounded lines',b'\r\n\r\n' in raw and raw.replace(b'\r\n',b'').find(b'\n')==-1 and raw.replace(b'\r\n',b'').find(b'\r')==-1 and max(map(len,raw.split(b'\r\n')))<=998 and len(raw)<=1048576)
    check(name+' header fields have no parser defects',all(not getattr(value,'defects',()) for _,value in message.items()))
assert len(data['messages'])==27
result={'passed':True,'scope':'Independent standard-library MIME parse of all synthetic plain, HTML, thread, attachment, and maximum-shape maintained-library output','checks':checks}
if len(sys.argv)>2:Path(sys.argv[2]).write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8',newline='\n')
print('PASS:',len(checks),'independent MIME decoding/header/privacy checks')
