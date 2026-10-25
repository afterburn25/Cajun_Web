#!/usr/bin/env python3
"""Portable temporary-file regressions for the packaged UI public export only."""
import argparse
import ast
import copy
import json
import os
from pathlib import Path
import struct
import tempfile
import zlib
from unittest.mock import patch

import provider_php_controls_ui_acceptance as bridge


EXPECTED = {"source_commit": "a" * 40, "source_tree": "b" * 40,
            "package_sha256": "c" * 64, "package_members": 3000}


def image(name):
    width = 1440 if name.endswith("-Desktop.png") else 320
    def chunk(kind, raw):
        return struct.pack(">I", len(raw)) + kind + raw + struct.pack(">I", zlib.crc32(kind + raw) & 0xffffffff)
    return (b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, 1, 8, 2, 0, 0, 0))
            + chunk(b"IDAT", zlib.compress(b"\0" + b"\0" * width * 3)) + chunk(b"IEND", b""))


def successful():
    names = sorted(bridge.REQUIRED_CHECKS - {"Packaged PHP server emits no PHP diagnostics"}) + ["Packaged PHP server emits no PHP diagnostics"]
    return {"passed": True, "scope": bridge.SCOPE, **EXPECTED,
            "checks": [{"name": n, "passed": True} for n in names], "count": len(names),
            "browser_version": "140.0.7339.0",
            "journey": {"synthetic_options": True, "synthetic_provider_tls": True,
                        "captures": list(reversed(bridge.CAPTURES)), "request_count": 3, "post_count": 3,
                        "scope": bridge.JOURNEY_SCOPE},
            "captures": [{"name": n, "sha256": bridge.sha(image(n))} for n in bridge.CAPTURES],
            "provider": {"calls": 15, "posts": 3, "gets": 12},
            "server_log": {"sha256": "d" * 64, "php_diagnostics": 0, "raw_log_private": True}}


def failed():
    return {"passed": False, "scope": bridge.SCOPE, "checks": [], "count": 0,
            "failure": {"class": "RuntimeError", "file": "provider_php_controls_ui_browser.py", "line": 200,
                        "message": "Synthetic bounded failure for export validation"}}


def emit(root, value, images=True):
    nested = root / "qa/php-controls-ui"
    nested.mkdir(parents=True)
    raw = (json.dumps(value, indent=3) + "\n\n").encode()
    (nested / "results.json").write_bytes(raw)
    if images:
        for name in bridge.CAPTURES:
            (nested / name).write_bytes(image(name))
    return raw


def snapshot(path):
    return {p.relative_to(path).as_posix(): p.read_bytes() for p in path.rglob("*") if p.is_file() and not p.is_symlink()}


def run(checks):
    def check(name, value):
        if type(value) is not bool or any(c["name"] == name for c in checks):
            raise AssertionError("Invalid portable check")
        checks.append({"name": name, "passed": value})
        if not value:
            raise AssertionError(name)

    def refuses(name, work):
        try:
            work()
        except (ValueError, OSError, UnicodeError, json.JSONDecodeError):
            check(name, True)
        else:
            check(name, False)

    with tempfile.TemporaryDirectory(prefix="php-ui-bridge-") as directory:
        base = Path(directory)
        number = 0
        def fresh():
            nonlocal number
            number += 1
            root = base / str(number)
            root.mkdir()
            return root

        root = fresh()
        private = root / "test-runtime/php-controls-ui"
        private.mkdir(parents=True)
        (private / "config.php").write_bytes(b"PRIVATE-CONFIG-SENTINEL")
        (private / "server.log").write_bytes(b"PRIVATE-TOKEN-SENTINEL")
        raw = emit(root, successful())
        originals = snapshot(root)
        check("Complete success exports zero exit status", bridge.export(root, EXPECTED, 0) == 0)
        check("Success report is byte-for-byte original including whitespace", (root / "qa" / bridge.REPORT).read_bytes() == raw)
        check("Only exact flat report and sixteen existing PNG names exported", {p.name for p in (root / "qa").iterdir()} == {bridge.REPORT, *bridge.CAPTURES})
        check("All sixteen exports match retained original PNG bytes", all((root / "qa" / n).read_bytes() == (root / bridge.RETAINED / n).read_bytes() for n in bridge.CAPTURES))
        check("Original report images and private runtime bytes unchanged", all((root / (n.replace("qa/php-controls-ui/", bridge.RETAINED + "/", 1))).read_bytes() == data for n, data in originals.items()))
        check("Original public directory relocated out of full QA boundary", not (root / "qa/php-controls-ui").exists() and (root / bridge.RETAINED).is_dir())
        check("No private sentinel exported", all(b"PRIVATE-" not in p.read_bytes() for p in (root / "qa").iterdir() if p.is_file()))
        refuses("Repeated export refuses prior outputs", lambda: bridge.export(root, EXPECTED, 0))

        root = fresh(); calls = []
        def original():
            calls.append(1)
            emit(root, successful())
            return 0
        check("Runner callable executes exactly once", bridge.run_once(root, EXPECTED, original) == 0 and calls == [1])

        root = fresh(); raw = emit(root, successful())
        open_original = Path.open
        def interrupted_open(path, *args, **kwargs):
            if path == root / "qa" / bridge.CAPTURES[1] and args and args[0] == "xb":
                raise OSError("Synthetic disk-write interruption")
            return open_original(path, *args, **kwargs)
        with patch.object(Path, "open", interrupted_open):
            refuses("Interrupted flat export fails", lambda: bridge.export(root, EXPECTED, 0))
        check("Interrupted export retains every original and cannot publish success", (root / bridge.RETAINED / "results.json").read_bytes() == raw
              and all((root / bridge.RETAINED / n).read_bytes() == image(n) for n in bridge.CAPTURES)
              and not (root / "qa" / bridge.REPORT).exists())

        root = fresh()
        def collision():
            emit(root, successful())
            (root / "qa" / bridge.CAPTURES[0]).write_bytes(b"prior-flat-output")
            return 0
        refuses("Late flat collision refuses without overwrite", lambda: bridge.run_once(root, EXPECTED, collision))
        check("Late collision retains original directory and existing bytes", (root / "qa/php-controls-ui/results.json").is_file()
              and (root / "qa" / bridge.CAPTURES[0]).read_bytes() == b"prior-flat-output"
              and not (root / bridge.RETAINED).exists())

        for label, code, raises in (("nonzero", 1, False), ("false report with zero exit", 0, False), ("raised original failure", 1, True)):
            root = fresh(); originals = []
            def fail_original():
                originals.append(emit(root, failed(), images=False))
                # A partial capture is retained locally but never exported.
                (root / "qa/php-controls-ui" / bridge.CAPTURES[0]).write_bytes(b"incomplete PNG")
                if raises:
                    raise RuntimeError("Private synthetic failure")
                return code
            check(label + " remains failed", bridge.run_once(root, EXPECTED, fail_original) == 1)
            check(label + " preserves exact failure report and partial original", (root / "qa" / bridge.REPORT).read_bytes() == originals[0]
                  and (root / bridge.RETAINED / "results.json").read_bytes() == originals[0]
                  and (root / bridge.RETAINED / bridge.CAPTURES[0]).read_bytes() == b"incomplete PNG")
            check(label + " exports no partial image", not any((root / "qa" / n).exists() for n in bridge.CAPTURES))

        root = fresh(); calls = []
        def no_report():
            calls.append(1)
            raise SystemExit("Refused before bootstrap")
        refuses("Early refusal cannot fabricate a result", lambda: bridge.run_once(root, EXPECTED, no_report))
        check("Early refusal has one invocation and no flat report", calls == [1] and not (root / "qa" / bridge.REPORT).exists())

        for label, relative in (("flat report", "qa/" + bridge.REPORT), ("flat image", "qa/" + bridge.CAPTURES[0]),
                                ("nested original", "qa/php-controls-ui"), ("private runtime", "test-runtime/php-controls-ui"),
                                ("retained original", bridge.RETAINED)):
            root = fresh(); target = root / relative; target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(b"retained")
            calls = []
            refuses("Preexisting " + label + " refuses before invocation", lambda: bridge.run_once(root, EXPECTED, lambda: calls.append(1)))
            check("Preexisting " + label + " remains exact", calls == [] and target.read_bytes() == b"retained")

        mutations = {
            "nonboolean passed": lambda r: r.update(passed=1),
            "wrong source": lambda r: r.update(source_commit="e" * 40),
            "wrong tree": lambda r: r.update(source_tree="e" * 40),
            "wrong package": lambda r: r.update(package_sha256="e" * 64),
            "wrong member count": lambda r: r.update(package_members=2999),
            "boolean member count": lambda r: r.update(package_members=True),
            "absent source": lambda r: r.pop("source_commit"),
            "private extra top-level field": lambda r: r.update(config="SECRET"),
            "altered scope": lambda r: r.update(scope="Live provider success"),
            "wrong count": lambda r: r.update(count=r["count"] + 1),
            "duplicate check": lambda r: (r["checks"].append(copy.deepcopy(r["checks"][0])), r.update(count=r["count"] + 1)),
            "false check": lambda r: r["checks"][0].update(passed=False),
            "numeric check flag": lambda r: r["checks"][0].update(passed=1),
            "missing required check": lambda r: (r["checks"].pop(0), r.update(count=r["count"] - 1)),
            "wrong last check": lambda r: r["checks"].reverse(),
            "unknown check field": lambda r: r["checks"][0].update(token="SECRET"),
            "invented real choices": lambda r: r["journey"].update(synthetic_options=False),
            "invented live provider": lambda r: r["journey"].update(synthetic_provider_tls=False),
            "missing request": lambda r: r["journey"].update(request_count=2),
            "repeated POST": lambda r: r["provider"].update(posts=4),
            "inconsistent calls": lambda r: r["provider"].update(calls=99),
            "duplicate journey capture": lambda r: r["journey"]["captures"].__setitem__(0, r["journey"]["captures"][1]),
            "capture manifest path": lambda r: r["captures"][0].update(name="../private.png"),
            "capture digest mismatch": lambda r: r["captures"][0].update(sha256="0" * 64),
            "public raw log": lambda r: r["server_log"].update(raw_log="SECRET"),
            "nonprivate log": lambda r: r["server_log"].update(raw_log_private=False),
            "PHP diagnostics": lambda r: r["server_log"].update(php_diagnostics=1),
            "success carrying failure": lambda r: r.update(failure=failed()["failure"]),
            "missing browser identity": lambda r: r.pop("browser_version"),
        }
        for label, mutate in mutations.items():
            root = fresh(); value = successful(); mutate(value); emit(root, value)
            before = snapshot(root)
            refuses(label + " refused", lambda: bridge.export(root, EXPECTED, 0))
            check(label + " leaves all original bytes and no exports", snapshot(root) == before)

        for label, mutate in {
            "extra public file": lambda p: (p / "config.php").write_bytes(b"SECRET"),
            "extra public directory": lambda p: (p / "sessions").mkdir(),
            "missing capture": lambda p: (p / bridge.CAPTURES[-1]).unlink(),
            "truncated capture": lambda p: (p / bridge.CAPTURES[-1]).write_bytes(b"\x89PNG\r\n\x1a\n"),
            "duplicate JSON key": lambda p: (p / "results.json").write_text('{"passed":false,"passed":true}'),
            "nonfinite JSON": lambda p: (p / "results.json").write_text('{"passed":NaN}'),
            "invalid UTF8": lambda p: (p / "results.json").write_bytes(b"\xff"),
        }.items():
            root = fresh(); emit(root, successful()); mutate(root / "qa/php-controls-ui")
            before = snapshot(root)
            refuses(label + " refused without export", lambda: bridge.export(root, EXPECTED, 0))
            check(label + " source retained", snapshot(root) == before)

        for label, code in (("nonzero exit with success", 1), ("boolean exit with success", False)):
            root = fresh(); emit(root, successful())
            refuses(label + " refused", lambda: bridge.export(root, EXPECTED, code))
            check(label + " no flat success", not (root / "qa" / bridge.REPORT).exists())

        root = fresh(); bad = failed(); bad["failure"]["file"] = "../private/config.php"; emit(root, bad, False)
        refuses("Unsafe failure location remains nested only", lambda: bridge.export(root, EXPECTED, 1))
        root = fresh(); bad = failed(); bad.pop("failure"); emit(root, bad, False)
        refuses("Unexplained false result refused", lambda: bridge.export(root, EXPECTED, 1))

        for label, transform in (("wrong width", lambda raw: image("PHP-Controls-Owner-Mobile.png")),
                                 ("PNG trailing data", lambda raw: raw + b"PRIVATE"),
                                 ("PNG bad CRC", lambda raw: raw[:-1] + bytes([raw[-1] ^ 1]))):
            root = fresh(); value = successful(); data = transform(image(bridge.CAPTURES[0])); value["captures"][0]["sha256"] = bridge.sha(data)
            emit(root, value); (root / "qa/php-controls-ui" / bridge.CAPTURES[0]).write_bytes(data)
            refuses(label + " refused even with matching digest", lambda: bridge.export(root, EXPECTED, 0))

        # Real symlink probes; inability to create one is an actual test failure.
        for label, relative, directory_link in (("QA parent", "qa", True), ("runtime parent", "test-runtime", True),
                                               ("flat result", "qa/" + bridge.REPORT, False),
                                               ("nested directory", "qa/php-controls-ui", True),
                                               ("private runtime", "test-runtime/php-controls-ui", True),
                                               ("retained original", bridge.RETAINED, True)):
            root = fresh(); outside = fresh(); target = root / relative; target.parent.mkdir(parents=True, exist_ok=True)
            destination = outside if directory_link else outside / "absent"
            target.symlink_to(destination, target_is_directory=directory_link)
            calls = []
            refuses("Symlink " + label + " denied before invocation", lambda: bridge.run_once(root, EXPECTED, lambda: calls.append(1)))
            check("Symlink " + label + " not followed", calls == [] and list(outside.iterdir()) == [])
        root = fresh(); emit(root, successful()); path = root / "qa/php-controls-ui" / bridge.CAPTURES[0]
        path.unlink(); path.symlink_to(base / "absent.png")
        refuses("Symlink public image refused", lambda: bridge.export(root, EXPECTED, 0))

        import acceptance_environment as environment
        choice = {"php": "8.3", "database": "mariadb-10.11"}
        fixture_binary = str(base / "synthetic-php8.3")
        cli = {"version": "8.3.20", "sapi": "cli", "binary": fixture_binary, "extensions": sorted(environment.EXTENSIONS)}
        minimal = {"version": "8.3.20", "pdo": True, "tokenizer": True, "drivers": []}
        runtime = {"passed": True, "selected": choice, "lane": "native", "scope": "Actual isolated CI runtime, not live provider or deployment evidence.",
                   "cli": cli, "minimal": minimal, "database_version": "10.11.18-MariaDB", "checks": environment.validate(cli, minimal, "10.11.18-MariaDB", "native", choice)}
        env = {"CWP_PHP_VERSION": "8.3", "CWP_DATABASE": "mariadb-10.11", "PHP_BINARY": fixture_binary}
        root = fresh(); (root / "qa").mkdir(); target = root / "qa/native-environment-results.json"
        target.write_text(json.dumps(runtime))
        check("Existing native runtime predicates revalidate without PHP or SQL", bridge.runtime(root, env) == bridge.sha(target.read_bytes()))
        for label, mutate in {
            "failed runtime": lambda r: r.update(passed=False),
            "wrong lane": lambda r: r.update(lane="apache"),
            "wrong PHP": lambda r: r["cli"].update(version="8.2.20"),
            "wrong DB": lambda r: r.update(database_version="8.4.1"),
            "wrong selected": lambda r: r["selected"].update(php="8.4"),
            "different PHP executable": lambda r: r["cli"].update(binary="/usr/bin/php8.4"),
            "stale runtime check": lambda r: r["checks"][0].update(passed=False),
            "numeric runtime flag": lambda r: r["checks"][0].update(passed=1),
            "missing runtime extension": lambda r: r["cli"].update(extensions=[]),
        }.items():
            value = copy.deepcopy(runtime); mutate(value); target.write_text(json.dumps(value))
            refuses(label + " prerequisite refused", lambda: bridge.runtime(root, env))

        tests = Path(__file__).resolve().parent
        runner = ast.parse((tests / "provider_php_controls_ui_browser.py").read_text(encoding="utf-8"))
        cases = ast.parse((tests / "provider_php_controls_ui_cases.py").read_text(encoding="utf-8"))
        titles = next(ast.literal_eval(n.value) for n in cases.body if isinstance(n, ast.Assign) and any(isinstance(t, ast.Name) and t.id == "CAPTURE_TITLES" for t in n.targets))
        check("Sixteen names exactly match unchanged cases constant", bridge.CAPTURES == tuple(t + "-" + size + ".png" for t in titles for size in ("Desktop", "Mobile")))
        strings = {n.value for tree in (runner, cases) for n in ast.walk(tree) if isinstance(n, ast.Constant) and type(n.value) is str}
        check("Required successful outcomes all exist in original producer", bridge.REQUIRED_CHECKS <= strings)
        check("Scope disclosures are exact original producer values", bridge.SCOPE in strings and bridge.JOURNEY_SCOPE in strings)
        source = ast.parse((tests / "provider_php_controls_ui_acceptance.py").read_text(encoding="utf-8"))
        calls = [n for n in ast.walk(source) if isinstance(n, ast.Call) and isinstance(n.func, ast.Attribute) and isinstance(n.func.value, ast.Name) and n.func.value.id == "runner" and n.func.attr == "main"]
        check("Production bridge has one unchanged runner entry call", len(calls) == 1 and not calls[0].args and not calls[0].keywords)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--report", type=Path)
    args = parser.parse_args()
    if args.report:
        if args.report.exists() or args.report.is_symlink():
            raise ValueError("Preserve existing guard report")
        for parent in args.report.parents:
            if parent.is_symlink():
                raise ValueError("Unsafe guard report parent")
        args.report.parent.mkdir(parents=True, exist_ok=True)
    checks = []
    result = {"passed": False, "scope": "Portable temporary-file export, refusal and retention checks; no PHP, SQL, provider or browser execution.", "checks": checks}
    try:
        run(checks)
        result["passed"] = True
    except Exception as error:
        result["failure_class"] = type(error).__name__
    result["count"] = len(checks)
    raw = json.dumps(result, indent=2) + "\n"
    if args.report:
        with args.report.open("x", encoding="utf-8", newline="\n") as handle:
            handle.write(raw)
    print(raw, end="")
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
