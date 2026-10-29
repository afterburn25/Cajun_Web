#!/usr/bin/env python3
"""Keep focused and full browser-suite ownership explicit and non-duplicative."""
from __future__ import annotations

import ast
import json
import sys
from pathlib import Path


ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) == 2 else Path(__file__).resolve().parents[1]


def software_calls(nodes: list[ast.stmt]) -> int:
    module = ast.Module(body=nodes, type_ignores=[])
    return sum(
        1
        for node in ast.walk(module)
        if isinstance(node, ast.Call)
        and isinstance(node.func, ast.Name)
        and node.func.id == "software_commerce_browser"
    )


def software_imports(nodes: list[ast.stmt]) -> int:
    module = ast.Module(body=nodes, type_ignores=[])
    return sum(
        1
        for node in ast.walk(module)
        if isinstance(node, ast.ImportFrom)
        and node.module == "software_commerce_cases"
        and any(
            alias.name == "run_browser" and alias.asname == "software_commerce_browser"
            for alias in node.names
        )
    )


def named_if(tree: ast.AST, name: str) -> ast.If:
    matches = [
        node
        for node in ast.walk(tree)
        if isinstance(node, ast.If)
        and isinstance(node.test, ast.Name)
        and node.test.id == name
    ]
    if len(matches) != 1:
        raise AssertionError(f"expected one {name} branch, found {len(matches)}")
    return matches[0]


browser = ast.parse((ROOT / "tests/browser_acceptance.py").read_text(), "browser_acceptance.py")
provider_source = (ROOT / "tests/provider_schedule_cases.py").read_text()
provider = ast.parse(provider_source, "provider_schedule_cases.py")
software_only = named_if(browser, "software_only")
financing_only = named_if(browser, "financing_only")

checks = {
    "provider schedule does not own software commerce": software_calls(provider.body) == 0
    and software_imports(provider.body) == 0
    and "software_commerce_cases" not in provider_source,
    "focused software branch imports once": software_imports(software_only.body) == 1,
    "focused software branch runs once": software_calls(software_only.body) == 1,
    "financing-only branch does not run software commerce": software_calls(financing_only.body) == 0,
    "full branch imports software commerce once": software_imports(financing_only.orelse) == 1,
    "full branch runs software commerce once": software_calls(financing_only.orelse) == 1,
}
report = {"passed": all(checks.values()), "count": len(checks), "checks": checks}
print(json.dumps(report, indent=2))
raise SystemExit(0 if report["passed"] else 1)
