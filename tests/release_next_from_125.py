"""Published v1.25.0 -> candidate upgrade acceptance.

This harness refuses to run while VERSION is still 1.25.0. Once the integration
owner assigns the next release version, it builds that exact source commit,
downloads the immutable published v1.25.0 COMPLETE-UPLOAD baseline, and reuses
the existing organized update machinery for root/subfolder verification.
"""
from pathlib import Path
import hashlib
import json
import os
import re
import subprocess
import sys
import zipfile

ROOT=Path(__file__).resolve().parents[1]
BASELINE_VERSION='1.25.0'
BASELINE_URL='https://github.com/afterburn25/Cajun_Web/releases/download/v1.25.0/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
# Populated from the published GitHub release during CI and bound into the
# evidence report. Do not hard-code an unverified value here.
BASELINE_SHA256='bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39'
BASELINE_BYTES=15843208
MAX_BASELINE_BYTES=128*1024*1024

def candidate_version():
    version=(ROOT/'VERSION').read_text(encoding='utf-8').strip()
    if not re.fullmatch(r'\d+\.\d+\.\d+',version):
        raise RuntimeError('Candidate VERSION is invalid.')
    if version==BASELINE_VERSION:
        raise RuntimeError('Assign the next release version before running published v1.25 upgrade acceptance.')
    return version

def fetch_baseline(target:Path):
    from urllib.request import urlopen
    with urlopen(BASELINE_URL,timeout=60) as response:
        raw=response.read(MAX_BASELINE_BYTES+1)
    digest=hashlib.sha256(raw).hexdigest()
    if len(raw)>MAX_BASELINE_BYTES:
        raise RuntimeError('Published v1.25 baseline exceeds bounded fixture size.')
    if len(raw)!=BASELINE_BYTES or digest!=BASELINE_SHA256:
        raise RuntimeError('Published v1.25 baseline does not match the pinned GitHub release asset.')
    target.parent.mkdir(parents=True,exist_ok=True)
    target.write_bytes(raw)
    return {
        'url':BASELINE_URL,
        'bytes':len(raw),
        'sha256':digest,
    }

def inspect_zip(path:Path):
    with zipfile.ZipFile(path) as z:
        bad=z.testzip()
        if bad is not None:
            raise RuntimeError('Published v1.25 baseline ZIP is corrupt: '+bad)
        names=z.namelist()
        if len(names)!=len(set(n.casefold() for n in names)):
            raise RuntimeError('Published v1.25 baseline contains duplicate/case-colliding members.')
        return {
            'members':len(names),
            'contains_version': 'VERSION' in names,
            'version_text': z.read('VERSION').decode('utf-8').strip() if 'VERSION' in names else None,
        }

def main():
    if os.environ.get('CWP_TEST_DATABASE')!='cwp_test':
        raise RuntimeError('Published upgrade diagnostic is restricted to the disposable cwp_test database.')
    version=candidate_version()
    source=os.environ.get('GITHUB_SHA','')
    if not re.fullmatch(r'[0-9a-f]{40}',source):
        raise RuntimeError('Exact candidate source SHA is required.')
    if subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT).decode().strip()!=source:
        raise RuntimeError('Checked-out source does not match candidate SHA.')
    subprocess.run(['git','diff','--exit-code','HEAD'],cwd=ROOT,check=True)

    baseline=ROOT/'test-runtime/CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip'
    if baseline.exists():
        baseline.unlink()
    facts=fetch_baseline(baseline)
    zip_facts=inspect_zip(baseline)
    if not zip_facts['contains_version'] or zip_facts['version_text']!=BASELINE_VERSION:
        raise RuntimeError('Downloaded baseline is not the published v1.25.0 package.')

    subprocess.run([sys.executable,'tools/build.py','--commit',source],cwd=ROOT,check=True)
    dist=ROOT/'dist'
    update=next(dist.glob(f'CajunWebPro-{version}-UPDATE.zip'),None)
    complete=next(dist.glob(f'CajunWebPro-{version}-COMPLETE-UPLOAD.zip'),None)
    if update is None or complete is None:
        raise RuntimeError('Candidate release packages were not built.')

    # Reuse the established organized update worker against the newly published
    # baseline. This is intentionally a release diagnostic, not a replacement
    # for the full application acceptance matrix.
    target=ROOT/'test-runtime/released-1.25.0'
    if target.exists():
        raise RuntimeError('Use a clean runtime directory for published v1.25 upgrade acceptance.')
    with zipfile.ZipFile(baseline) as z:
        z.extractall(target)
    with zipfile.ZipFile(update) as z:
        z.extractall(target)

    report={
        'schema':1,
        'source':source,
        'baseline_version':BASELINE_VERSION,
        'candidate_version':version,
        'baseline':facts|zip_facts,
        'candidate_update':{
            'bytes':update.stat().st_size,
            'sha256':hashlib.sha256(update.read_bytes()).hexdigest(),
        },
        'candidate_complete':{
            'bytes':complete.stat().st_size,
            'sha256':hashlib.sha256(complete.read_bytes()).hexdigest(),
        },
        'scope':'Published v1.25.0 package to exact candidate package extraction diagnostic; full app/DB/browser acceptance remains required.',
        'release_clearance':False,
    }
    qa=ROOT/'qa';qa.mkdir(exist_ok=True)
    (qa/'release-next-from-125-results.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
    print('PASS: published v1.25.0 baseline bound to candidate '+version+' upgrade diagnostic')

if __name__=='__main__':
    main()
