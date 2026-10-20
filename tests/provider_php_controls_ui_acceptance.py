#!/usr/bin/env python3
"""Full-lane export of the unchanged, isolated packaged PHP-controls runner.

Only the original public JSON and its sixteen PNGs cross into flat QA outputs.
Validated originals move unchanged to the fixed retained runtime directory;
unsafe output stays in place. Existing private runtime files are never moved.
"""
from pathlib import Path
import hashlib
import json
import os
import re
import stat
import struct
import subprocess
import sys
import zlib

ROOT = Path(__file__).resolve().parents[1]
REPORT = "php-controls-packaged-ui-results.json"
RETAINED = "test-runtime/php-controls-ui-acceptance-original"
TITLES = ("Owner", "Client", "Owner-Review", "Client-Review", "Owner-History",
          "Client-History", "Owner-Recovery", "Client-Unavailable")
CAPTURES = tuple("PHP-Controls-" + title + "-" + size + ".png"
                 for title in TITLES for size in ("Desktop", "Mobile"))
SCOPE = "Exact packaged HTTP, disposable SQL and loopback TLS; positive normalized options are synthetic; no live provider or release acceptance."
JOURNEY_SCOPE = "Real packaged HTTP forms with disposable SQL and loopback TLS; normalized choices explicitly synthetic. Screenshots require separate visual review."
REQUIRED_CHECKS = {
    "Every extracted member matches the recorded Git source",
    "Fresh seed starts without PHP control stores or provider writes",
    "Default HTTP choices remain unavailable without TLS request",
    "Latest failed choices suppress older synthetic success",
    "Exact bounded capture inventory",
    "Only three original single-send setter attempts occurred",
    "Each original request retains its distinct genuine ledger identity",
    "Local provider accepted only the exact intended TLS requests",
    "All expected browser captures exist with no extra public files",
    "Packaged PHP server emits no PHP diagnostics",
}
IDENTITY_KEYS = {"source_commit", "source_tree", "package_sha256", "package_members"}
OPTIONAL_KEYS = IDENTITY_KEYS | {"browser_version", "journey", "captures", "provider", "server_log",
                                 "failure", "cleanup_failures", "private_failure_log_unavailable"}


def require(condition, message):
    if not condition:
        raise ValueError(message)


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def read_file(path, maximum):
    path = Path(path)
    info = path.lstat()
    require(stat.S_ISREG(info.st_mode) and not path.is_symlink() and info.st_size <= maximum,
            "Expected a bounded regular public file")
    raw = path.read_bytes()
    require(len(raw) == info.st_size, "File changed during read")
    return raw


def read_json(raw):
    def pairs(items):
        result = {}
        for key, value in items:
            require(key not in result, "Duplicate JSON member")
            result[key] = value
        return result
    def constant(_):
        raise ValueError("Nonfinite JSON value")
    return json.loads(raw.decode("utf-8", errors="strict"), object_pairs_hook=pairs, parse_constant=constant)


def closed(value, keys):
    require(type(value) is dict and set(value) == set(keys), "Unexpected public record shape")


def integer(value, minimum=0):
    return type(value) is int and minimum <= value <= 2147483647


def digest(value):
    return type(value) is str and re.fullmatch(r"[a-f0-9]{64}", value) is not None


def png(raw, name):
    require(raw.startswith(b"\x89PNG\r\n\x1a\n"), "Invalid capture signature")
    offset, kinds = 8, []
    while offset < len(raw):
        require(offset + 12 <= len(raw), "Truncated PNG chunk")
        size = struct.unpack(">I", raw[offset:offset + 4])[0]
        end = offset + 12 + size
        require(end <= len(raw), "Truncated PNG data")
        kind, data = raw[offset + 4:offset + 8], raw[offset + 8:offset + 8 + size]
        require(zlib.crc32(kind + data) & 0xffffffff == struct.unpack(">I", raw[end - 4:end])[0], "PNG checksum mismatch")
        if not kinds:
            require(kind == b"IHDR" and size == 13, "Missing PNG dimensions")
            width, height = struct.unpack(">II", data[:8])
            require(width == (1440 if name.endswith("-Desktop.png") else 320) and 1 <= height <= 200000,
                    "Capture does not retain its declared viewport width")
        else:
            require(kind != b"IHDR", "Duplicate PNG header")
        kinds.append(kind)
        offset = end
        if kind == b"IEND":
            require(size == 0 and end == len(raw), "Trailing PNG data")
            break
    require(kinds[-1:] == [b"IEND"] and b"IDAT" in kinds, "Incomplete PNG")


def paths(root, fresh):
    root = Path(root)
    for parent in (root / "qa", root / "test-runtime"):
        require(not parent.is_symlink() and (not parent.exists() or parent.is_dir()), "Unsafe output parent")
    qa, nested = root / "qa", root / "qa/php-controls-ui"
    retained = root / RETAINED
    require(not retained.exists() and not retained.is_symlink(), "Preserve retained original output")
    for target in [qa / REPORT, *(qa / name for name in CAPTURES)]:
        require(not target.exists() and not target.is_symlink(), "Preserve existing flat output")
    if fresh:
        for target in (nested, root / "test-runtime/php-controls-ui"):
            require(not target.exists() and not target.is_symlink(), "Preserve existing packaged fixture")
    else:
        require(nested.is_dir() and not nested.is_symlink(), "Missing regular public output directory")
    return qa, nested


def validate_report(report, expected):
    require(type(report) is dict and {"passed", "scope", "checks", "count"} <= set(report)
            and set(report) <= {"passed", "scope", "checks", "count"} | OPTIONAL_KEYS, "Unexpected report shape")
    require(type(report["passed"]) is bool and report["scope"] == SCOPE, "Incorrect original scope/status")
    checks = report["checks"]
    require(type(checks) is list and len(checks) <= 2000 and integer(report["count"]) and report["count"] == len(checks), "Invalid check count")
    names = set()
    for item in checks:
        closed(item, {"name", "passed"})
        name = item["name"]
        require(type(name) is str and 1 <= len(name) <= 300 and name.isascii() and name.isprintable()
                and name not in names and type(item["passed"]) is bool, "Invalid named check")
        names.add(name)
    for key in IDENTITY_KEYS & set(report):
        require(type(report[key]) is type(expected[key]) and report[key] == expected[key], "Original identity mismatch")
    if "browser_version" in report:
        require(type(report["browser_version"]) is str and re.fullmatch(r"\d+(?:\.\d+){1,4}", report["browser_version"]), "Invalid browser version")
    if "journey" in report:
        journey = report["journey"]
        closed(journey, {"synthetic_options", "synthetic_provider_tls", "captures", "request_count", "post_count", "scope"})
        require(journey["synthetic_options"] is True and journey["synthetic_provider_tls"] is True
                and journey["scope"] == JOURNEY_SCOPE and type(journey["request_count"]) is int
                and journey["request_count"] == 3 and type(journey["post_count"]) is int and journey["post_count"] == 3
                and type(journey["captures"]) is list and len(journey["captures"]) == 16
                and all(type(n) is str for n in journey["captures"]) and set(journey["captures"]) == set(CAPTURES), "Invalid completed synthetic journey")
    if "captures" in report:
        require(type(report["captures"]) is list and len(report["captures"]) == 16, "Incomplete capture manifest")
        for item, name in zip(report["captures"], CAPTURES):
            closed(item, {"name", "sha256"})
            require(item["name"] == name and digest(item["sha256"]), "Invalid capture manifest")
    if "provider" in report:
        provider = report["provider"]
        closed(provider, {"calls", "posts", "gets"})
        require(all(integer(v) for v in provider.values()) and provider["posts"] == 3 and provider["gets"] > 0
                and provider["calls"] == provider["posts"] + provider["gets"], "Invalid synthetic transport counts")
    if "server_log" in report:
        log = report["server_log"]
        closed(log, {"sha256", "php_diagnostics", "raw_log_private"})
        require(digest(log["sha256"]) and integer(log["php_diagnostics"]) and log["raw_log_private"] is True, "Private log boundary missing")
    if "failure" in report:
        error = report["failure"]
        closed(error, {"class", "file", "line", "message"})
        require(type(error["class"]) is str and re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]{0,99}", error["class"])
                and type(error["file"]) is str and re.fullmatch(r"[A-Za-z0-9_.-]{0,150}", error["file"])
                and integer(error["line"]), "Unsafe failure diagnostic")
        require(type(error["message"]) is str and len(error["message"]) <= 400
                and error["message"].isascii()
                and all(ch.isprintable() or ch in "\n\r\t" for ch in error["message"])
                and not any(secret in error["message"] for secret in
                          ("SYNTHETIC_PHP_UI_KEY", "Synthetic PHP UI owner passphrase",
                           "Synthetic PHP UI client passphrase")), "Unsafe failure message")
    if "cleanup_failures" in report:
        require(type(report["cleanup_failures"]) is list and 1 <= len(report["cleanup_failures"]) <= 3, "Invalid cleanup failure list")
        steps = set()
        for item in report["cleanup_failures"]:
            closed(item, {"step", "class"})
            require(item["step"] in {"php", "log", "tls"} and item["step"] not in steps
                    and type(item["class"]) is str and re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]{0,99}", item["class"]), "Unsafe cleanup diagnostic")
            steps.add(item["step"])
    if "private_failure_log_unavailable" in report:
        require(report["private_failure_log_unavailable"] is True, "Invalid private failure flag")
    if report["passed"]:
        require(IDENTITY_KEYS | {"browser_version", "journey", "captures", "provider", "server_log"} <= set(report)
                and not ({"failure", "cleanup_failures", "private_failure_log_unavailable"} & set(report))
                and all(item["passed"] is True for item in checks) and REQUIRED_CHECKS <= names
                and checks[-1]["name"] == "Packaged PHP server emits no PHP diagnostics"
                and report["server_log"]["php_diagnostics"] == 0, "Incomplete successful runner")
    else:
        require("failure" in report or "cleanup_failures" in report or any(item["passed"] is False for item in checks), "Unexplained failed runner")


def export(root, expected, returncode):
    qa, nested = paths(root, fresh=False)
    entries = list(nested.iterdir())
    require({p.name for p in entries} <= {"results.json", *CAPTURES}, "Extra public output refused")
    require(all(not p.is_symlink() and p.is_file() for p in entries), "Nonregular public output refused")
    raw = read_file(nested / "results.json", 1024 * 1024)
    report = read_json(raw)
    validate_report(report, expected)
    require(type(returncode) is int and (not report["passed"] or returncode == 0), "Runner status contradicts report")
    outputs = []
    if report["passed"]:
        require({p.name for p in entries} == {"results.json", *CAPTURES}, "Incomplete public output set")
        for item in report["captures"]:
            data = read_file(nested / item["name"], 64 * 1024 * 1024)
            require(sha(data) == item["sha256"], "Capture digest mismatch")
            png(data, item["name"])
            outputs.append((item["name"], data))
    # The full evidence sealer permits only flat QA members. Retain the complete
    # closed public directory outside QA, without rewriting or deleting its files.
    # Invalid original output is left in place and can never pass the full step.
    retained = Path(root) / RETAINED
    retained.parent.mkdir(exist_ok=True)
    os.rename(nested, retained)
    require(not nested.exists() and retained.is_dir() and not retained.is_symlink(), "Original relocation failed")
    # Validate every input before exporting anything; the original report is last.
    outputs.append((REPORT, raw))
    for name, data in outputs:
        with (qa / name).open("xb") as handle:
            require(handle.write(data) == len(data), "Incomplete export write")
        require(read_file(qa / name, max(len(data), 1)) == data, "Export byte mismatch")
    require(read_file(retained / "results.json", 1024 * 1024) == raw, "Original result changed")
    for name, data in outputs[:-1]:
        require(read_file(retained / name, len(data)) == data, "Original capture changed")
    return 0 if report["passed"] else 1


def run_once(root, expected, invoke):
    paths(root, fresh=True)
    try:
        code = invoke()
    except (Exception, SystemExit):
        # An existing public failure may be retained; no replacement report is made.
        code = 1
    return export(root, expected, code)


def runtime(root, environ):
    import acceptance_environment as environment
    raw = read_file(Path(root) / "qa/native-environment-results.json", 1024 * 1024)
    value = read_json(raw)
    closed(value, {"passed", "selected", "lane", "scope", "checks", "cli", "minimal", "database_version"})
    choice = environment.selected(environ)
    require(value["passed"] is True and value["lane"] == "native" and value["selected"] == choice, "Native runtime prerequisite mismatch")
    checks = environment.validate(value["cli"], value["minimal"], value["database_version"], "native", choice)
    require(type(value["checks"]) is list and all(type(c) is dict and set(c) == {"name", "passed"}
            and c["passed"] is True for c in value["checks"])
            and value["checks"] == checks and all(item["passed"] is True for item in checks)
            and value["cli"]["binary"] == environ.get("PHP_BINARY"), "Native runtime proof mismatch")
    return sha(raw)


def identity(root):
    def git(*args):
        return subprocess.check_output(["git", *args], cwd=root).decode("utf-8").strip()
    require(not git("status", "--porcelain", "--untracked-files=all"), "Recorded checkout must be clean")
    head, tree = git("rev-parse", "HEAD"), git("rev-parse", "HEAD^{tree}")
    require(all(re.fullmatch(r"[a-f0-9]{40}", v) for v in (head, tree)), "Invalid recorded source")
    packages = list((Path(root) / "dist").glob("CajunWebPro-*-COMPLETE-UPLOAD.zip"))
    require(len(packages) == 1, "Require one built COMPLETE package")
    package = packages[0]
    require(not package.parent.is_symlink(), "Unsafe package parent")
    manifest = read_json(read_file(package.with_suffix(".manifest.json"), 16 * 1024 * 1024))
    package_hash = sha(read_file(package, 1024 * 1024 * 1024))
    require(type(manifest) is dict and manifest.get("source_commit") == head and manifest.get("artifact") == package.name
            and manifest.get("sha256") == package_hash and type(manifest.get("files")) is dict
            and len(manifest["files"]) > 0, "Package source binding mismatch")
    return {"source_commit": head, "source_tree": tree, "package_sha256": package_hash, "package_members": len(manifest["files"])}


def main():
    require(sys.platform == "linux" and os.environ.get("CWP_TEST_DATABASE") == "cwp_test"
            and os.environ.get("CWP_TEST_HOST", "127.0.0.1") == "127.0.0.1", "Linux disposable runtime required")
    expected, runtime_hash = identity(ROOT), runtime(ROOT, os.environ)
    import provider_php_controls_ui_browser as runner
    require(runner.ROOT == ROOT and Path(runner.__file__).resolve() == ROOT / "tests/provider_php_controls_ui_browser.py", "Unexpected original runner")
    def invoke():
        code = runner.main()
        require(identity(ROOT) == expected and runtime(ROOT, os.environ) == runtime_hash, "Source/runtime changed during fixture")
        return code
    return run_once(ROOT, expected, invoke)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(json.dumps({"passed": False, "error_class": type(error).__name__, "scope": "Packaged UI export refused; originals are preserved in their nested output or fixed retained runtime directory."}))
        raise SystemExit(1)
