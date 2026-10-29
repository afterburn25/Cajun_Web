#!/usr/bin/env python3
"""Execute the real suite-dispatch AST with recorded suite boundaries, no HTTP.

Browser/database assertions remain in their original suites. This regression
isolates orchestration so a duplicated suite is detected without a two-hour run.
"""
import ast
import builtins
from pathlib import Path
from types import SimpleNamespace
import unittest

ROOT = Path(__file__).resolve().parents[1]


def dispatch_statements(statements):
    """Keep suite imports/calls and their actual conditional control flow."""
    result = []
    for node in statements:
        if isinstance(node, ast.ImportFrom) and any(alias.name == 'run_browser' for alias in node.names):
            result.append(node)
        elif (isinstance(node, ast.Expr) and isinstance(node.value, ast.Call)
              and isinstance(node.value.func, ast.Name)
              and (node.value.func.id == 'run_browser' or node.value.func.id.endswith('_browser'))):
            result.append(node)
        elif isinstance(node, ast.If):
            body = dispatch_statements(node.body)
            otherwise = dispatch_statements(node.orelse)
            if body or otherwise:
                result.append(ast.If(test=node.test, body=body or [ast.Pass()], orelse=otherwise))
    return result


def execute_dispatch(software_only=False, financing_only=False, provider_only=False):
    tree = ast.parse((ROOT / 'tests/browser_acceptance.py').read_text(encoding='utf-8'))
    dispatches = [node for node in ast.walk(tree) if isinstance(node, ast.If)
                  and isinstance(node.test, ast.Name) and node.test.id == 'software_only']
    assert len(dispatches) == 1, 'Expected one full/focused browser dispatch boundary'
    provider = ast.parse((ROOT / 'tests/provider_schedule_cases.py').read_text(encoding='utf-8'))
    functions = [node for node in provider.body if isinstance(node, ast.FunctionDef) and node.name == 'run_browser']
    assert len(functions) == 1, 'Expected one provider-schedule browser entry'
    calls = []
    environment = {
        'software_only': software_only, 'financing_only': financing_only,
        'os': SimpleNamespace(environ={'CWP_SOFTWARE_ACCEPTANCE_ONLY': '1'} if software_only else {}),
        'browser': object(), 'release_fixture': object(), 'env': {},
    }

    def execute(statements):
        module = ast.fix_missing_locations(ast.Module(body=dispatch_statements(statements), type_ignores=[]))
        exec(compile(module, '<actual browser suite dispatch>', 'exec'), environment)

    def entry(name):
        def call(*_args):
            calls.append(name)
            if name == 'provider_schedule_cases':
                execute(functions[0].body)
        return call

    def import_suite(name, globals=None, locals=None, fromlist=(), level=0):
        if fromlist == ('run_browser',) and level == 0:
            return SimpleNamespace(run_browser=entry(name))
        raise AssertionError('Unexpected orchestration import: ' + name)

    environment['__builtins__'] = {**vars(builtins), '__import__': import_suite}
    environment['run_browser'] = entry('release_cases')
    if provider_only:
        entry('provider_schedule_cases')()
    else:
        execute(dispatches)
    return calls


class BrowserSuiteOrchestration(unittest.TestCase):
    def test_full_suite_invokes_every_feature_once(self):
        calls = execute_dispatch()
        self.assertGreater(len(calls), 40)
        self.assertEqual(len(calls), len(set(calls)), calls)
        self.assertEqual(calls.count('provider_schedule_cases'), 1)
        self.assertEqual(calls.count('software_commerce_cases'), 1)
        self.assertEqual(calls.count('license_financing_cases'), 1)

    def test_full_suite_retains_software_then_financing_order(self):
        self.assertEqual(execute_dispatch()[-3:], [
            'provider_schedule_cases', 'software_commerce_cases', 'license_financing_cases'])

    def test_provider_schedule_does_not_start_another_suite(self):
        self.assertEqual(execute_dispatch(provider_only=True), ['provider_schedule_cases'])

    def test_focused_software_invokes_software_once(self):
        self.assertEqual(execute_dispatch(software_only=True), ['software_commerce_cases'])

    def test_focused_financing_invokes_financing_once(self):
        self.assertEqual(execute_dispatch(financing_only=True), ['license_financing_cases'])


if __name__ == '__main__':
    unittest.main()
