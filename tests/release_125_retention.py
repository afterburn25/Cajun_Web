"""Column-preserving comparison for published-package upgrade evidence; no DB writes."""
from copy import deepcopy
import hashlib
import json
import re
from pathlib import Path


_SCHEMA_MARKER_PATTERN = re.compile(r'[a-z_]+_schema_version')
_marker_row_keys = None


def _schema_marker_row_keys():
    """Snapshot row keys for every *_schema_version setting defined in the application."""
    global _marker_row_keys
    if _marker_row_keys is None:
        names = set()
        for path in (Path(__file__).resolve().parents[1] / 'site').rglob('*.php'):
            names.update(_SCHEMA_MARKER_PATTERN.findall(path.read_text(encoding='utf-8', errors='ignore')))
        _marker_row_keys = {hashlib.sha256(json.dumps({'name': name}, separators=(',', ':')).encode()).hexdigest() for name in names}
    return _marker_row_keys


def marker_transition_cells(before, after):
    """Journaled *_schema_version settings are versioned state, not business data:
    file recovery restores the marker values recorded alongside the original files
    and reapplication reasserts the forward set. Each era's functional checks prove
    the correct marker value separately; only cells whose value actually changed
    across the compared snapshots are named."""
    markers = _schema_marker_row_keys()
    before_rows = before['tables'].get('settings', {}).get('rows', {})
    after_rows = after['tables'].get('settings', {}).get('rows', {})
    return [('settings', key, 'value') for key, row in before_rows.items()
            if key in markers and key in after_rows and after_rows[key].get('value') != row.get('value')]


def validate(snapshot):
    assert set(snapshot) == {'version', 'tables', 'private_files'}
    assert isinstance(snapshot['version'], str) and isinstance(snapshot['tables'], dict) and isinstance(snapshot['private_files'], dict)
    digest = lambda value: isinstance(value, str) and re.fullmatch('[a-f0-9]{64}', value) is not None
    for name, table in snapshot['tables'].items():
        assert isinstance(name, str) and re.fullmatch('[a-z][a-z0-9_]*', name)
        assert set(table) == {'count', 'rows'} and type(table['count']) is int and table['count'] == len(table['rows'])
        for key, row in table['rows'].items():
            assert digest(key) and isinstance(row, dict) and row and all(isinstance(c, str) and digest(v) for c, v in row.items())
    for name, entry in snapshot['private_files'].items():
        assert isinstance(name, str) and not name.startswith('/') and '\\' not in name and '..' not in name.split('/')
        assert set(entry) == {'bytes', 'sha256'} and type(entry['bytes']) is int and entry['bytes'] >= 0 and digest(entry['sha256'])


def retained(before, after, *, changed_cells=()):
    """Old rows/columns and private files must survive; additions are separate evidence."""
    validate(before); validate(after)
    allowed = set(changed_cells)
    seen = set()
    for table, original in before['tables'].items():
        if table not in after['tables']:
            raise AssertionError('Retained table missing: ' + table)
        current = after['tables'][table]
        for key, columns in original['rows'].items():
            if key not in current['rows']:
                raise AssertionError('Retained row missing: ' + table)
            for column, digest in columns.items():
                cell = (table, key, column)
                if cell in allowed:
                    if column not in current['rows'][key]:
                        raise AssertionError('Allowed transition lost its column: ' + table)
                    seen.add(cell)
                elif current['rows'][key].get(column) != digest:
                    raise AssertionError('Retained column changed: ' + table + '.' + column)
    if seen != allowed:
        raise AssertionError('Declared transition does not name an original retained cell.')
    for name, value in before['private_files'].items():
        if after['private_files'].get(name) != value:
            raise AssertionError('Retained private file changed: ' + name)
    return True


def exact_business(before, after):
    """Retries may append a legitimate audit, but cannot alter existing audits or business rows."""
    retained(before, after)
    old, new = deepcopy(before), deepcopy(after)
    old.pop('version', None); new.pop('version', None)
    old['tables'].pop('audit', None); new['tables'].pop('audit', None)
    if old != new:
        raise AssertionError('Retry changed business rows, tables, or private files.')
    return True


def exact_business_recovered(before, after):
    """exact_business across a file-recovery boundary: journaled schema markers
    legitimately return to their recorded pre-update values and reassert on
    reapplication; every other original row, column and private byte must match."""
    cells = marker_transition_cells(before, after)
    retained(before, after, changed_cells=cells)
    old, new = deepcopy(before), deepcopy(after)
    old.pop('version', None); new.pop('version', None)
    for table, key, column in cells:
        old['tables'][table]['rows'][key][column] = new['tables'][table]['rows'][key][column]
    old['tables'].pop('audit', None); new['tables'].pop('audit', None)
    if old != new:
        raise AssertionError('Recovery changed business rows, tables, or private files.')
    return True
