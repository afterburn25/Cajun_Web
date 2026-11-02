"""Offline checks for the one named import-count transition and source form identity."""
from copy import deepcopy
import hashlib
import json
from types import SimpleNamespace

from release_126_catalog_records import count_transition, png
from release_126_upgrade_cases import fields, BASELINE_SHA, BASELINE_SOURCE


def run(check):
    key = hashlib.sha256(json.dumps({'name': 'provider_catalog_import_count'}, separators=(',', ':')).encode()).hexdigest()
    before = {'version': '1.25.0', 'tables': {
        'settings': {'count': 1, 'rows': {key: {'name': 'a' * 64, 'value': hashlib.sha256(b'"1"').hexdigest()}}},
        'catalog_provider_imports': {'count': 1, 'rows': {'b' * 64: {'source_hash': 'c' * 64, 'media_id': 'd' * 64}}},
        'website_licenses': {'count': 1, 'rows': {'e' * 64: {'license_key': 'f' * 64}}}},
        'private_files': {'media/original.php': {'bytes': 100, 'sha256': '1' * 64}, 'provider-master.key': {'bytes': 32, 'sha256': '2' * 64}}}
    after = deepcopy(before); after['version'] = '1.26.0'
    after['tables']['settings']['rows'][key]['value'] = hashlib.sha256(b'"2"').hexdigest()
    after['tables']['catalog_provider_imports']['rows']['3' * 64] = {'source_hash': '4' * 64, 'media_id': '5' * 64}
    after['tables']['catalog_provider_imports']['count'] = 2
    after['tables']['provider_package_type_intents'] = {'count': 1, 'rows': {'6' * 64: {'proof': '7' * 64}}}
    check('Only the actual1-to2 import count transition permits the new catalog draft', count_transition(before, after))

    def deny(name, change):
        a, b = deepcopy(before), deepcopy(after); change(a, b)
        try: count_transition(a, b)
        except (AssertionError, KeyError, TypeError): check(name, True)
        else: check(name, False)
    deny('Wrong original import counter cannot be adopted', lambda a,b:a['tables']['settings']['rows'][key].update(value=hashlib.sha256(b'"0"').hexdigest()))
    deny('Unexpected third import is not covered by the count exception', lambda a,b:b['tables']['settings']['rows'][key].update(value=hashlib.sha256(b'"3"').hexdigest()))
    deny('Unchanged original counter cannot pretend to record a new import', lambda a,b:b['tables']['settings']['rows'][key].update(value=hashlib.sha256(b'"1"').hexdigest()))
    deny('Original import provenance remains immutable during activation', lambda a,b:b['tables']['catalog_provider_imports']['rows']['b'*64].update(source_hash='0'*64))
    deny('Original imported image reference remains immutable during activation', lambda a,b:b['tables']['catalog_provider_imports']['rows']['b'*64].update(media_id='0'*64))
    deny('Original license key remains immutable during activation', lambda a,b:b['tables']['website_licenses']['rows']['e'*64].update(license_key='0'*64))
    deny('Original uploaded image bytes remain immutable during activation', lambda a,b:b['private_files']['media/original.php'].update(sha256='0'*64))
    deny('Original vault key remains immutable during activation', lambda a,b:b['private_files']['provider-master.key'].update(sha256='0'*64))
    deny('Missing original count row cannot pass the named transition', lambda a,b:b['tables']['settings'].update(count=0,rows={}))
    forms=''.join('<form><input name="action" value="catalog_import_create"><input name="import_source" value="'+source+'"><input name="type_ref" value="901"><input name="preview_hash" value="'+str(i)*64+'"></form>' for i,source in enumerate(['packages.list','package.types'],1))
    response=SimpleNamespace(status_code=200,text=forms,url='http://127.0.0.1/subfolder/admin/index.php?view=catalog')
    _, selected=fields(response,'catalog_import_create',{'type_ref':901,'import_source':'package.types'})
    check('Catalog form selection binds source as well as the documented type identity',selected['import_source']=='package.types' and selected['preview_hash']=='2'*64)
    try: fields(response,'catalog_import_create',{'type_ref':901})
    except AssertionError: check('Ambiguous cross-source type form is refused',True)
    else: check('Ambiguous cross-source type form is refused',False)
    check('Published baseline constants identify accepted1.25 exactly',BASELINE_SHA=='bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39' and BASELINE_SOURCE=='b79c67110829126916e3a65e616191542448bccc')
    # A finite synthetic upload fixture, never claimed to be provider artwork.
    check('Original local image fixture contains a real bounded PNG',png().startswith(b'\x89PNG\r\n\x1a\n') and len(png())<1024)
