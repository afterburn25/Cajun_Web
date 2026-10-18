"""Joined disposable SQL purchase with one explicitly simulated application-clock phase.

Only prepare PHP receives libfaketime. The Python parent, database service and due/
portal PHP children stay on real time. This is eventual, not immediate, purchase.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import stat
import subprocess
import sys
import time

PHASES = ("prepare", "due", "portal-own", "portal-other")
DISABLED_PHP_FUNCTIONS = "curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,shell_exec,system,passthru,popen,proc_open"
LIBRARY = Path("/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1")
OFFSET_SECONDS = 172800
SYNTHETIC_VALUES = (
    "Synthetic joined hosting owner", "Synthetic joined hosting client", "SYNTHETIC_JOINED_HOSTING_KEY",
    "synthetic-joined-hosting-" * 4, "local-ci-only",
)


def require(value: bool, message: str) -> None:
    if not value:
        raise RuntimeError(message)


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def clean_environment(environ: dict[str, str]) -> dict[str, str]:
    forbidden = [key for key in environ if key.startswith("FAKETIME") or key in
                 {"LD_PRELOAD", "LD_AUDIT", "DONT_FAKE_MONOTONIC", "CWP_PURCHASE_PARENT_EPOCH",
                  "CWP_TEST_HOSTING_PURCHASE", "CWP_TEST_HOSTING_PURCHASE_FULL_SUITE", "PHPRC", "PHP_INI_SCAN_DIR"}]
    require(not forbidden, "An inherited process clock/runtime override is present")
    env = dict(environ)
    env["TZ"] = "UTC"
    return env


def phase_environment(env: dict[str, str], phase: str, epoch: int) -> dict[str, str]:
    require(phase in PHASES, "Unknown closed purchase phase")
    child = dict(env)
    child.update(CWP_TEST_HOSTING_PURCHASE="1", CWP_PURCHASE_PARENT_EPOCH=str(epoch))
    if phase == "prepare":
        child.update(LD_PRELOAD=str(LIBRARY), FAKETIME="-2d",
                     FAKETIME_DONT_FAKE_MONOTONIC="1", FAKETIME_NO_CACHE="1")
    return child


def contained_fresh(root: Path, parent: str, leaf: str) -> Path:
    target = root / parent / leaf
    for part in (root, root / parent, target):
        require(not part.is_symlink(), "Purchase evidence/runtime path is a symlink")
        if part.exists():
            require(part.is_dir() and part.resolve() == part, "Purchase parent is not an exact directory")
    for part in root.parents:
        require(not part.is_symlink(), "Checkout ancestor is a symlink")
    require(not target.exists(), "Purchase evidence/runtime already exists")
    return target


def safe_tail(data: bytes, root: Path, db_password: str) -> str:
    text = data[-8192:].decode("utf-8", "replace")
    for value in (*SYNTHETIC_VALUES, db_password, str(root)):
        if value:
            text = text.replace(value, "[redacted]")
    # No arbitrary SQL parameters, session payload, configuration, or stdout dump.
    lines = [line for line in text.splitlines() if not line.lstrip().startswith(("#", "Stack trace:"))]
    return "\n".join(lines[-4:])[:1200]


def decode_report(data: bytes, phase: str) -> dict:
    def pairs(items):
        result = {}
        for key, value in items:
            require(key not in result, "Duplicate report key")
            result[key] = value
        return result
    report = json.loads(data.decode("utf-8"), object_pairs_hook=pairs,
                        parse_constant=lambda _: (_ for _ in ()).throw(ValueError("Nonfinite report")))
    require(report["phase"] == phase and report["passed"] is True, "Purchase phase failed")
    require("error" not in report, "Purchase report retains a failure")
    checks = report["checks"]
    require(isinstance(checks, list) and len(checks) > 3, "Missing purchase checks")
    names = [row["name"] for row in checks]
    require(all(isinstance(n, str) and n for n in names) and len(names) == len(set(names)),
            "Duplicate or invalid purchase check name")
    require(all(row["passed"] is True for row in checks), "A purchase check failed")
    require(report["simulated_application_clock"] is (phase == "prepare"), "Wrong phase clock label")
    clock = report["clock"]
    require(all(type(clock[key]) is int for key in ("php_epoch", "parent_epoch", "database_epoch", "offset_seconds")),
            "Clock evidence must be exact integer epochs")
    expected = OFFSET_SECONDS if phase == "prepare" else 0
    require(clock["offset_seconds"] == expected and
            abs(clock["php_epoch"] - (clock["parent_epoch"] - expected)) < 120 and
            abs(clock["database_epoch"] - clock["parent_epoch"]) < 120,
            "Application/database clock isolation differs")
    return report


def real_php_clock(php: str, env: dict[str, str]) -> dict:
    before = time.time()
    raw = subprocess.run([php, "-r", "echo json_encode(['epoch'=>time(),'micro'=>microtime(true),'php'=>PHP_VERSION],JSON_THROW_ON_ERROR);"],
                         env=env, check=True, capture_output=True, timeout=20)
    after = time.time()
    require(raw.stderr == b"", "Unshifted PHP clock probe emitted diagnostics")
    probe = json.loads(raw.stdout)
    require(before - 2 <= probe["epoch"] <= after + 2 and
            before - 1 <= probe["micro"] <= after + 1, "Unshifted PHP differs from parent real time")
    return probe


def arguments(argv=None):
    parser = argparse.ArgumentParser(description=__doc__, allow_abbrev=False)
    parser.add_argument('--full-suite', action='store_true')
    return parser.parse_args(argv)


def runtime_selection(env, full_suite):
    require(env.get('CWP_TEST_DATABASE') == 'cwp_test' and env.get('CWP_TEST_HOST') == '127.0.0.1',
            'Use the dedicated disposable loopback database')
    port = env.get('CWP_TEST_PORT', '')
    require(bool(re.fullmatch('[1-9][0-9]{0,4}', port)) and int(port) <= 65535,
            'Use the actual assigned disposable database port')
    php = env.get('PHP_BINARY', '')
    if full_suite:
        version = env.get('CWP_PHP_VERSION', '')
        require(version in ('8.2', '8.3', '8.4') and php == '/usr/bin/php' + version,
                'Use the full-suite selected supported PHP binary')
    else:
        require(php == '/usr/bin/php8.3' and port == '33306', 'Use the original focused runtime')
    return php, int(port)


def purchase_output_paths(root, full_suite):
    qa = root / ('qa' if full_suite else 'qa/hosting-purchase')
    names = {phase: phase + '-results.json' for phase in PHASES}
    names['joined'] = 'joined-purchase-results.json'
    return qa, {key: qa / (('hosting-purchase-' + key + '-results.json') if full_suite else name)
                for key, name in names.items()}


def prepare_purchase_outputs(root, full_suite):
    qa, outputs = purchase_output_paths(root, full_suite)
    if full_suite:
        for parent in (qa, *qa.parents):
            require(not parent.is_symlink() and (not parent.exists() or parent.is_dir()),
                    'Purchase output parent is not a regular directory')
        require(all(not p.exists() and not p.is_symlink() for p in outputs.values()),
                'Preserve previous joined purchase evidence')
    else:
        require(qa == contained_fresh(root, 'qa', 'hosting-purchase'), 'Unexpected focused output path')
    qa.mkdir(parents=True, exist_ok=full_suite, mode=0o700)
    return qa, outputs


def main(full_suite=False) -> int:
    root = Path(__file__).absolute().parent.parent
    env = clean_environment(dict(os.environ))
    require(platform.system() == "Linux" and platform.machine() == "x86_64", "Use the focused Ubuntu x86_64 job")
    php, port = runtime_selection(env, full_suite)
    require(Path(php).is_file(), "Selected PHP binary is unavailable")
    require(LIBRARY.is_file(), "Install the distro libfaketime package first")
    lib = LIBRARY.stat()
    require(lib.st_uid == 0 and not lib.st_mode & (stat.S_IWGRP | stat.S_IWOTH), "Clock library ownership differs")
    package = subprocess.run(["dpkg-query", "-W", "-f=${Package}\t${Version}\n", "libfaketime"],
                             check=True, capture_output=True, text=True, timeout=20)
    require(package.stdout.startswith("libfaketime\t") and package.stderr == "", "Clock package identity unavailable")
    runtime = contained_fresh(root, "test-runtime", "hosting-purchase")
    qa, outputs = prepare_purchase_outputs(root, full_suite)
    runtime.mkdir(parents=True, mode=0o700)
    for name in ("private", "sessions"):
        (runtime / name).mkdir(mode=0o700)
    (runtime / "private" / "media").mkdir(mode=0o700)
    proof = {"passed": False, "scope": "disposable SQL, simulated prepare time, real due time, synthetic provider transport",
             "immediate_purchase_proven": False, "live_provider_or_payment": False,
             "clock_dependency": {"package": package.stdout.strip(), "sha256": sha(LIBRARY.read_bytes())},
             "phases": [], "checks": []}
    start, monotonic = time.time(), time.monotonic()
    try:
        proof["source"] = subprocess.run(["git", "rev-parse", "HEAD"], cwd=root, check=True,
                                         capture_output=True, text=True, timeout=20).stdout.strip()
        require(bool(re.fullmatch("[0-9a-f]{40}", proof["source"])), "Missing actual checkout identity")
        proof["fixture_sha256"] = sha((root / "tests/hosting_catalog_purchase_native.php").read_bytes())
        proof["unshifted_before"] = real_php_clock(php, env)
        for phase in PHASES:
            epoch = int(time.time())
            child_env = phase_environment(env, phase, epoch)
            if full_suite: child_env["CWP_TEST_HOSTING_PURCHASE_FULL_SUITE"] = "1"
            stdout_path, stderr_path = runtime / (phase + ".stdout"), runtime / (phase + ".stderr")
            with stdout_path.open("xb") as stdout, stderr_path.open("xb") as stderr:
                child = subprocess.run([php, "-d", "display_errors=0", "-d", "log_errors=1",
                                        "-d", "allow_url_fopen=0", "-d", "disable_functions=" + DISABLED_PHP_FUNCTIONS,
                                        str(root / "tests/hosting_catalog_purchase_native.php"), phase],
                                       cwd=root, env=child_env, stdout=stdout, stderr=stderr, timeout=900)
            out, err = stdout_path.read_bytes(), stderr_path.read_bytes()
            record = {"phase": phase, "exit_code": child.returncode, "stdout_sha256": sha(out),
                      "stderr_sha256": sha(err), "stdout_bytes": len(out), "stderr_bytes": len(err)}
            proof["phases"].append(record)
            if err:
                record["diagnostic"] = safe_tail(err, root, env.get("CWP_TEST_PASSWORD", ""))
            report_path = outputs[phase]
            if report_path.is_file():
                data = report_path.read_bytes()
                record.update(report=report_path.name, report_sha256=sha(data))
            require(child.returncode == 0 and err == b"", "Purchase child failed or emitted PHP diagnostics")
            require(report_path.is_file(), "Current purchase phase report is missing")
            report = decode_report(data, phase)
            require(report["clock"]["parent_epoch"] == epoch, "Phase report belongs to a different invocation")
            record["named_checks"] = len(report["checks"])
            require(abs((time.time() - start) - (time.monotonic() - monotonic)) < 3,
                    "Parent wall clock changed relative to its monotonic clock")
            real_php_clock(php, env)
        proof["unshifted_after"] = real_php_clock(php, env)
        proof["checks"] = [
            {"name": "Only the prepare child receives the process-local clock environment", "passed": True},
            {"name": "Every phase observed an unchanged real database server clock", "passed": True},
            {"name": "Parent and independent unshifted PHP probes remained on real time", "passed": True},
            {"name": "All four real wrapper/render phases passed with no PHP diagnostics", "passed": True},
        ]
        proof["passed"] = True
    except Exception as error:
        proof["error"] = {"class": type(error).__name__, "message": safe_tail(str(error).encode(), root, env.get("CWP_TEST_PASSWORD", ""))}
    finally:
        proof["elapsed_seconds"] = round(time.monotonic() - monotonic, 3)
        with outputs["joined"].open("x", encoding="utf-8") as stream:
            json.dump(proof, stream, indent=2, allow_nan=False)
            stream.write("\n")
    print(json.dumps({"passed": proof["passed"], "phases": len(proof["phases"])}))
    return 0 if proof["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main(arguments().full_suite))
