"""Portable blob-reader cases shared by the existing builder guard report.

The fixture uses only a small local Git object database. It never runs build.main,
contacts a remote, or launches one source process per tracked application file.
"""
from pathlib import Path
import hashlib,importlib.util,io,json,shutil,stat,subprocess,sys,tempfile,types
from unittest.mock import patch


def run(build,work,check):
    work=Path(work).resolve()
    fixture=Path(tempfile.mkdtemp(prefix='git-blob-batch-',dir=work)).resolve()
    assert fixture!=work and fixture.is_relative_to(work)
    original_root=build.ROOT
    real_output=subprocess.check_output;real_popen=subprocess.Popen
    def git(*arguments,data=None):
        return real_output(['git',*arguments],cwd=fixture,input=data,stderr=subprocess.PIPE)
    def blob(data):return git('hash-object','-w','--stdin',data=data).strip()
    def commit(entries):
        git('read-tree','--empty')
        lines=b''.join(mode.encode()+b' '+object_id+b'\t'+name.encode()+b'\n' for name,(mode,object_id) in entries.items())
        git('update-index','--index-info',data=lines)
        tree=git('write-tree').decode().strip()
        return git('-c','user.name=Build fixture','-c','user.email=build-fixture@example.invalid','-c','commit.gpgsign=false','commit-tree',tree,data=b'Synthetic builder fixture\n').decode().strip()
    def refuse(name,action,expected=(RuntimeError,)):
        try:action()
        except expected:check(name,True)
        else:check(name,False)
    def read_all(source,names):
        with build.source_blobs(source,names) as read:return {name:read(name) for name in names}
    try:
        git('init','--quiet')
        build.ROOT=fixture
        long_name='vendor/'+('/'.join(['dependency-component-0123456789']*10))+'/binary.php'
        payloads={'empty.bin':b'','binary.dat':bytes(range(256))+b'\0line\nend','duplicate.dat':bytes(range(256))+b'\0line\nend','folder with spaces/value.txt':b'spaces\n','executable.sh':b'#!/bin/sh\nexit 0\n',long_name:b'<?php /* exact long blob */\x80\xff\n'}
        entries={name:('100755' if name=='executable.sh' else '100644',blob(data)) for name,data in payloads.items()}
        source=commit(entries);names=sorted(payloads)
        calls=[];children=[]
        def output(command,**kwargs):calls.append(command);return real_output(command,**kwargs)
        def popen(command,**kwargs):
            calls.append(command);child=real_popen(command,**kwargs);children.append(child);return child
        proxy=types.SimpleNamespace(check_output=output,Popen=popen,PIPE=subprocess.PIPE)
        with patch.object(build,'subprocess',proxy):actual=read_all(source,names)
        check('Batch reader uses one immutable tree query and one child for all synthetic paths',len(calls)==2 and calls[0][1:4]==['ls-tree','-r','-z'] and calls[1]==['git','cat-file','--batch'])
        for name in names:
            check('Batch bytes equal legacy source_bytes for '+name,actual[name]==payloads[name]==build.source_bytes(source,name))
        check('Batch reader preserves duplicate blob content under distinct paths',actual['binary.dat']==actual['duplicate.dat'])
        check('Batch reader supports long object paths without creating long working files',len(long_name)>260 and not (fixture/'vendor').exists())
        check('Successful batch closes both pipes and reaps its real child',children[-1].stdin.closed and children[-1].stdout.closed and children[-1].poll()==0)
        changed=dict(entries);changed['binary.dat']=('100644',blob(b'newer synthetic bytes'));new_source=commit(changed)
        check('Batch snapshot remains bound to the requested older immutable commit',read_all(source,names)==payloads and read_all(new_source,names)['binary.dat']==b'newer synthetic bytes')
        refuse('Batch snapshot refuses omitted tracked names',lambda:read_all(source,names[:-1]))
        refuse('Batch snapshot refuses an unexpected tracked name',lambda:read_all(source,names+['missing.txt']))
        refuse('Batch snapshot refuses duplicate tracked names',lambda:read_all(source,names+[names[0]]))
        symlink_source=commit({'link':('120000',blob(b'binary.dat'))})
        refuse('Batch snapshot rejects a real Git symlink mode without OS symlink support',lambda:read_all(symlink_source,['link']))
        gitlink_source=commit({'module':('160000',source.encode())})
        refuse('Batch snapshot rejects a real Git submodule mode',lambda:read_all(gitlink_source,['module']))
        for mode in ['caller_failure','unknown_path','early_child_exit']:
            children=[]
            def child_factory(command,**kwargs):
                if mode=='early_child_exit':
                    child=real_popen([sys.executable,'-c','raise SystemExit(7)'],**kwargs);child.wait()
                else:child=real_popen(command,**kwargs)
                children.append(child);return child
            proxy=types.SimpleNamespace(check_output=real_output,Popen=child_factory,PIPE=subprocess.PIPE)
            observed=None
            with patch.object(build,'subprocess',proxy):
                try:
                    with build.source_blobs(source,names) as read:
                        read('unknown.txt' if mode=='unknown_path' else names[0])
                        if mode=='caller_failure':raise RuntimeError('Synthetic caller refusal')
                except (RuntimeError,KeyError,BrokenPipeError,OSError) as error:observed=error
            expected={'caller_failure':RuntimeError,'unknown_path':KeyError,'early_child_exit':OSError}[mode]
            check('Batch '+mode+' preserves a failing caller result',isinstance(observed,expected))
            child=children[-1]
            check('Batch '+mode+' closes both real pipes and reaps its child',child.stdin.closed and child.stdout.closed and child.poll() is not None)

        def object_id(data,algorithm='sha1'):
            digest=hashlib.new(algorithm);digest.update(b'blob '+str(len(data)).encode()+b'\0');digest.update(data);return digest.hexdigest().encode()
        body=b'abc';oid=object_id(body);packet=oid+b' blob 3\nabc\n';tree=b'100644 blob '+oid+b'\ta.bin\0'
        class FakeProcess:
            def __init__(self,raw,code):self.stdin=io.BytesIO();self.stdout=io.BytesIO(raw);self.code=code;self.done=False;self.errors=None
            def poll(self):return self.code if self.done else None
            def kill(self):self.done=True
            def wait(self):self.done=True;return self.code
        cases=[
            ('missing object',oid+b' missing\n',0),('non-blob object',oid+b' tree 3\nabc\n',0),
            ('wrong object identity',b'0'*40+b' blob 3\nabc\n',0),('truncated body',oid+b' blob 3\nab',0),
            ('missing delimiter',oid+b' blob 3\nabc',0),('wrong delimiter',oid+b' blob 3\nabc!',0),
            ('corrupt content',oid+b' blob 3\nabd\n',0),('noncanonical size',oid+b' blob 03\nabc\n',0),
            ('overlong header',b'a'*200+b'\n',0),('trailing output',packet+b'extra',0),('nonzero exit',packet,7)]
        for label,raw,code in cases:
            child=FakeProcess(raw,code)
            def fake_popen(*arguments,**kwargs):child.errors=kwargs['stderr'];return child
            proxy=types.SimpleNamespace(check_output=lambda *arguments,**kwargs:tree,Popen=fake_popen,PIPE=subprocess.PIPE)
            with patch.object(build,'subprocess',proxy):refuse('Batch protocol refuses '+label,lambda:read_all(source,['a.bin']))
            check('Batch protocol '+label+' closes pipes, error file and child',child.stdin.closed and child.stdout.closed and child.errors.closed and child.done)
        sha256=object_id(body,'sha256');sha256_tree=b'100644 blob '+sha256+b'\ta.bin\0';child=FakeProcess(sha256+b' blob 3\nabc\n',0)
        proxy=types.SimpleNamespace(check_output=lambda *arguments,**kwargs:sha256_tree,Popen=lambda *arguments,**kwargs:child,PIPE=subprocess.PIPE)
        with patch.object(build,'subprocess',proxy):check('Batch protocol validates SHA256-format Git object content',read_all(source,['a.bin'])=={'a.bin':body})
    finally:
        build.ROOT=original_root
        # This newly created, resolved child is the only recursive cleanup target.
        if fixture==work or not fixture.is_relative_to(work):raise RuntimeError('Unsafe synthetic Git cleanup path')
        def writable_remove(action,path,error):
            target=Path(path).resolve()
            if not target.is_relative_to(fixture):raise RuntimeError('Unsafe synthetic Git cleanup member')
            target.chmod(stat.S_IRUSR|stat.S_IWUSR|stat.S_IXUSR)
            action(path)
        shutil.rmtree(fixture,onerror=writable_remove)


if __name__=='__main__':
    root=Path(__file__).resolve().parents[1];work=root/'test-runtime';work.mkdir(exist_ok=True)
    spec=importlib.util.spec_from_file_location('cwp_build',root/'tools/build.py');build=importlib.util.module_from_spec(spec);spec.loader.exec_module(build)
    checks=[]
    def check(name,value):
        checks.append({'name':name,'passed':bool(value)})
        if not value:raise AssertionError(name)
    try:run(build,work,check)
    finally:
        qa=root/'qa';qa.mkdir(exist_ok=True)
        (qa/'build-git-blob-batch-results.json').write_text(json.dumps({'passed':sum(c['passed'] for c in checks),'checks':checks},indent=2)+'\n',encoding='utf-8')
    print('PASS:',len(checks),'portable Git blob batch checks')
