"""Column-preserving comparison for published-package upgrade evidence; no DB writes."""
from copy import deepcopy
import hashlib
import json
import re


# The update journal restores these recorded schema markers on file recovery and
# re-applies the marker state current at that recovery. Compare them honestly.
SCHEMA_MARKER_CELLS = [('settings', hashlib.sha256(json.dumps({'name': name}, separators=(',', ':')).encode()).hexdigest(), 'value')
                       for name in ('studio_schema_version', 'payments_schema_version')]


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


def exact_business(before, after, *, changed_cells=()):
    """Retries may append a legitimate audit, but cannot alter existing audits or business rows."""
    retained(before, after, changed_cells=changed_cells)
    old, new = deepcopy(before), deepcopy(after)
    for table, key, column in changed_cells:
        old['tables'][table]['rows'].get(key, {}).pop(column, None)
        new['tables'][table]['rows'].get(key, {}).pop(column, None)
    old.pop('version', None); new.pop('version', None)
    old['tables'].pop('audit', None); new['tables'].pop('audit', None)
    if old != new:
        raise AssertionError('Retry changed business rows, tables, or private files.')
    return True
