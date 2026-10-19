"""Portable checks of the actual builder's closed development-source boundary."""
from pathlib import Path
import importlib.util
import json


def run(build, work, check):
    approved = {
        'ops/twentyi-catalog-schema/diagnostic.py',
        'ops/twentyi-catalog-schema/test_diagnostic.py',
        'ops/twentyi-cloud-root/diagnostic.py',
        'ops/twentyi-cloud-root/test_diagnostic.py',
    }
    check('Builder diagnostic exception is exactly four reviewed development scripts',
          build.FULL_PROJECT_DIAGNOSTICS == approved)
    for name in sorted(approved):
        build.full_project_area(name)
        check('Builder permits exact diagnostic source ' + name, True)
    for name in (
        'ops/other/diagnostic.py', 'ops/twentyi-catalog-schema/extra.py',
        'ops/twentyi-cloud-root/evidence.json', 'ops/twentyi-cloud-root/.env',
        'ops/twentyi-catalog-schema/diagnostic.py.bak',
        'ops/twentyi-catalog-schema/Diagnostic.py',
        'OPS/twentyi-catalog-schema/diagnostic.py',
        'ops/twentyi-catalog-schema/nested/diagnostic.py',
        'ops/twentyi-catalog-schema/../twentyi-cloud-root/diagnostic.py',
        'ops//twentyi-cloud-root/diagnostic.py',
        'ops\\twentyi-cloud-root\\diagnostic.py',
        '/ops/twentyi-cloud-root/diagnostic.py', 'unapproved/source.py',
    ):
        try:
            build.full_project_area(name)
        except RuntimeError:
            check('Builder rejects unapproved development source ' + name, True)
        else:
            check('Builder rejects unapproved development source ' + name, False)
    for name in ('README.md', 'tools/build.py', 'site/index.php', 'tests/build_guards.py'):
        build.full_project_area(name)
        check('Builder retains existing project source area ' + name, True)


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--report', type=Path, required=True)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    spec = importlib.util.spec_from_file_location('cwp_build', root/'tools/build.py')
    build = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(build)
    checks = []
    completed = False
    def check(name, passed):
        assert isinstance(passed, bool) and name not in {row['name'] for row in checks}
        checks.append({'name': name, 'passed': passed})
        if not passed:
            raise AssertionError(name)
    try:
        run(build, None, check)
        completed = True
    finally:
        with args.report.open('x', encoding='utf-8') as output:
            json.dump({'passed': completed and bool(checks) and all(row['passed'] for row in checks),
                       'checks': checks, 'scope': 'Actual builder path policy only; no diagnostic execution or provider access.'}, output, indent=2)
    print(json.dumps({'passed': True, 'checks': len(checks)}))
