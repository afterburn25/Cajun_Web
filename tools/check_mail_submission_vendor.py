"""Verify the unmodified pinned PHPMailer runtime; optionally reproduce upstream bytes."""
from pathlib import Path
import hashlib,json,sys,urllib.request
def check(reproduce=False):
    root=Path(__file__).resolve().parents[1]
    base=root/'foundation/mail-submission';vendor=base/'vendor/phpmailer'
    data=json.loads((base/'vendor-source.json').read_text(encoding='utf-8'))
    assert data['package']=='phpmailer/phpmailer' and data['version']=='7.1.1'
    assert data['commit']=='1bc1716a507a65e039d4ac9d9adebbbd0d346e15'
    expected={'src/PHPMailer.php','src/SMTP.php','src/Exception.php','LICENSE','composer.json','README.md'}
    assert set(data['files'])==expected
    assert {p.relative_to(vendor).as_posix() for p in vendor.rglob('*') if p.is_file()}==expected
    assert not any(p.is_symlink() for p in vendor.rglob('*'))
    for name,item in data['files'].items():
        contents=(vendor/name).read_bytes();assert hashlib.sha256(contents).hexdigest()==item['sha256'],name
        url=f"https://raw.githubusercontent.com/PHPMailer/PHPMailer/{data['commit']}/{name}"
        assert item['source']==url
        if reproduce:
            with urllib.request.urlopen(url,timeout=30) as response: remote=response.read()
            assert remote==contents,name
    print('PASS:6 pinned original PHPMailer7.1.1 source/license/metadata files'+(' reproduced from upstream commit' if reproduce else ' match recorded hashes'))

if __name__=='__main__':check('--reproduce' in sys.argv)
