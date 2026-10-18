"""Actual fixture SQL against in-memory SQLite; no application DB or network."""
import ast
import json
from pathlib import Path
import re
import sqlite3
import sys

source=Path(__file__).with_name('cloud_assignment_fixture.py').read_text()
node=next(n for n in ast.parse(source).body if isinstance(n,ast.FunctionDef) and n.name=='age_unprotected_refreshes')
conn=sqlite3.connect(':memory:');conn.row_factory=sqlite3.Row
conn.execute('CREATE TABLE ci_provider_refreshes(id TEXT,profile_id TEXT,operation TEXT,started_epoch INTEGER,seq INTEGER)')
checks=[];sleeps=[]
class Clock:
    @staticmethod
    def time():return 1000
    @staticmethod
    def sleep(value):sleeps.append(value)
def db(sql,args=()):return [dict(r) for r in conn.execute(sql,args).fetchall()]
scope={'db':db,'time':Clock};exec(compile(ast.Module(body=[node],type_ignores=[]),'<actual-fixture-helper>','exec'),scope)
age=scope['age_unprotected_refreshes']
def check(name,ok):
    checks.append({'name':name,'passed':ok})
    if not ok:raise AssertionError(name)
def seed():
    conn.execute('DELETE FROM ci_provider_refreshes');sleeps.clear()
    conn.executemany('INSERT INTO ci_provider_refreshes VALUES (?,?,?,?,?)',[('cloud-a','a','cloudservers.list',990,1),('package-a','a','packages.list',999,2),('legacy-a','a',None,998,3),('cloud-b','b','cloudservers.list',995,4),('package-b','b','packages.list',997,5)])
try:
    seed();before=db('SELECT * FROM ci_provider_refreshes WHERE operation=? ORDER BY seq',['cloudservers.list']);age(120)
    check('Actual aging SQL preserves all cloud source bytes',db('SELECT * FROM ci_provider_refreshes WHERE operation=? ORDER BY seq',['cloudservers.list'])==before)
    check('Actual aging SQL still ages only unrelated and legacy reads',db('SELECT started_epoch FROM ci_provider_refreshes WHERE id IN (?,?) ORDER BY id',['package-a','legacy-a'])==[{'started_epoch':878},{'started_epoch':879}])
    check('Preserved newest cloud read imposes actual remaining cooldown',sleeps==[26])
    seed();other=db('SELECT * FROM ci_provider_refreshes WHERE profile_id=? ORDER BY seq',['b']);age(60,'a')
    check('Profile-scoped aging never changes another profile',db('SELECT * FROM ci_provider_refreshes WHERE profile_id=? ORDER BY seq',['b'])==other)
    check('Profile-scoped cooldown is derived only from selected profile',sleeps==[21])
    seed();before=db('SELECT * FROM ci_provider_refreshes ORDER BY seq');refused=False
    try:age(1)
    except AssertionError as e:refused=str(e)=='Unexpected fixture interval'
    check('Unexpected interval refuses before any mutation or wait',refused and db('SELECT * FROM ci_provider_refreshes ORDER BY seq')==before and sleeps==[])
    seed();conn.execute('UPDATE ci_provider_refreshes SET started_epoch=900');age(7200)
    check('Already expired cooldown never adds an arbitrary delay',sleeps==[0])
    seed();original_db=scope['db'];changed=False
    def corrupt(sql,args=()):
        global changed
        result=original_db(sql,args)
        if sql.startswith('UPDATE'):
            conn.execute('UPDATE ci_provider_refreshes SET started_epoch=989 WHERE id=?',['cloud-a']);changed=True
        return result
    scope['db']=corrupt;refused=False
    try:age(120)
    except AssertionError as e:refused=str(e)=='Fixture changed retained cloud intent bytes'
    check('Actual before-after guard detects unintended protected mutation',changed and refused and sleeps==[])
    # Execute the exact literal INSERTs with the documented field sequence. This
    # checks SQL binding arity before expensive native jobs; SQLite does not prove
    # the real MySQL schema, transaction, authorization or HMAC behavior.
    root=Path(sys.argv[2]) if len(sys.argv)>2 else Path(__file__).resolve().parents[1]
    columns={
        'provider_cloud_links':'id,profile_id,server_id,client_id,revision,linked_by,linked_at,key_fingerprint,profile_revision,refresh_id',
        'provider_cloud_link_events':'link_id,profile_id,server_id,client_id,revision,event,actor_id,recorded_at,key_fingerprint,profile_revision,refresh_id',
        'provider_cloud_assignment_evidence':'id,kind,source_id,profile_id,actor_id,document_json,document_sha256,previous_sha256,proof',
    }
    for name,fields in columns.items():conn.execute('CREATE TABLE '+name+' ('+','.join(c+' TEXT' for c in fields.split(','))+')')
    statements={}
    for label,path,table in [
        ('new assignment','site/app/providers/cloud_assignment.php','provider_cloud_links'),
        ('assignment event','site/app/providers/cloud_assignment.php','provider_cloud_link_events'),
        ('protected evidence','site/app/providers/cloud_assignment_storage.php','provider_cloud_assignment_evidence'),
        ('native legacy fixture','tests/provider_cloud_assignment_native.php','provider_cloud_links'),
    ]:
        content=(root/path).read_text()
        matches=re.findall(r"'INSERT INTO '\.table\('"+re.escape(table)+r"'\)\.' (\([^']+\) VALUES \([^']+\))'",content)
        if len(matches)!=1:raise AssertionError('Expected one exact literal INSERT for '+label)
        sql='INSERT INTO '+table+' '+matches[0];fields=columns[table].split(',')
        params=[label+' field '+str(i) for i in range(len(fields))]
        conn.execute('DELETE FROM '+table);conn.execute(sql,params)
        actual=dict(conn.execute('SELECT * FROM '+table).fetchone())
        check('Actual '+label+' INSERT binds each expected field once',actual==dict(zip(fields,params)))
        statements[label]=(sql,params)
    for label in ['new assignment','protected evidence']:
        sql,params=statements[label];bad=sql[:-1]+',?)';refused=False
        try:conn.execute(bad,params)
        except sqlite3.OperationalError as e:refused='values for' in str(e)
        check('Original extra-placeholder '+label+' INSERT is rejected',refused)
    result={'scope':'Extracted real fixture SQL, literal production/legacy INSERTs against synthetic in-memory SQLite and captured clock; not native application/MySQL/browser.','checks':checks}
    if len(sys.argv)>1:Path(sys.argv[1]).write_text(json.dumps(result,indent=2)+'\n')
    print(str(len(checks))+' cloud fixture preservation checks passed')
except Exception as e:
    if len(sys.argv)>1:Path(sys.argv[1]).write_text(json.dumps({'checks':checks,'failure':str(e)},indent=2)+'\n')
    raise
