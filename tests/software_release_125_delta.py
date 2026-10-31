"""Compare the immutable published 1.25.0 COMPLETE package to a candidate build.

This is a package-delta preflight only. It does not pretend a same-version
candidate can be applied by the updater; the real populated 1.25 -> next-version
apply/recovery matrix remains a release gate after the version is assigned.
"""
from pathlib import Path, PurePosixPath
import argparse
import hashlib
import json
import zipfile

BASELINE_SHA256 = "bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39"


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def safe_members(path: Path) -> dict[str, bytes]:
    with zipfile.ZipFile(path) as z:
        if z.testzip() is not None:
            raise AssertionError(f"Corrupt ZIP: {path}")
        names = z.namelist()
        if len(names) != len(set(names)) or len(names) != len({n.casefold() for n in names}):
            raise AssertionError(f"Duplicate/case-colliding ZIP member: {path}")
        result = {}
        for info in z.infolist():
            name = info.filename
            p = PurePosixPath(name)
            if info.is_dir() or p.is_absolute() or str(p) != name:
                raise AssertionError(f"Unsafe ZIP member {name!r}")
            if any(part in ("", ".", "..") for part in name.split("/")):
                raise AssertionError(f"Unsafe ZIP path {name!r}")
            if any(ch in name for ch in ("\\", ":", "\x00")):
                raise AssertionError(f"Unsafe ZIP spelling {name!r}")
            if name.lower().endswith(".zip"):
                raise AssertionError(f"Nested ZIP {name!r}")
            result[name] = z.read(name)
        return result


def update_payload(update: dict[str, bytes]) -> tuple[str, dict[str, bytes]]:
    stages = sorted({name.split("/", 1)[0] for name in update if "/" in name and name.startswith("cwp-update-")})
    if len(stages) != 1:
        raise AssertionError(f"Expected one update stage, found {stages}")
    stage = stages[0]
    prefix = stage + "/site/"
    payload = {name[len(prefix):]: data for name, data in update.items() if name.startswith(prefix)}
    if not payload:
        raise AssertionError("Candidate UPDATE contains no site payload")
    return stage, payload


def update_eligible(path: str) -> bool:
    return path != "install.php" and not path.startswith(("install/", "storage/", "upload/"))


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--baseline", required=True, type=Path)
    ap.add_argument("--complete", required=True, type=Path)
    ap.add_argument("--update", required=True, type=Path)
    ap.add_argument("--report", type=Path)
    args = ap.parse_args()

    if digest(args.baseline.read_bytes()) != BASELINE_SHA256:
        raise AssertionError("Published v1.25.0 COMPLETE digest changed")

    baseline = safe_members(args.baseline)
    candidate = safe_members(args.complete)
    update = safe_members(args.update)
    stage, payload = update_payload(update)

    changed = sorted(
        path for path, data in candidate.items()
        if update_eligible(path) and (path not in baseline or baseline[path] != data)
    )
    removed = sorted(path for path in baseline if path not in candidate)

    missing = [path for path in changed if path not in payload]
    mismatched = [path for path in changed if path in payload and payload[path] != candidate[path]]
    if missing:
        raise AssertionError("Candidate UPDATE misses changed v1.25 files: " + ", ".join(missing[:20]))
    if mismatched:
        raise AssertionError("Candidate UPDATE bytes differ from COMPLETE: " + ", ".join(mismatched[:20]))

    forbidden = [
        path for path in payload
        if path in {"config.php", ".installed.php", "proxy-config.php", "payment-master.key",
                    "mail-master.key", "provider-master.key", ".installation-mutex.php"}
        or path.startswith(("storage/", "upload/", "install/"))
        or PurePosixPath(path).name.startswith("config-pending-")
    ]
    if forbidden:
        raise AssertionError("Candidate UPDATE contains protected/runtime files: " + ", ".join(forbidden[:20]))

    # The updater intentionally contains the complete current payload, not only
    # byte-different files. Require every payload byte to match COMPLETE.
    extra_or_wrong = [
        path for path, data in payload.items()
        if path not in candidate or candidate[path] != data
    ]
    if extra_or_wrong:
        raise AssertionError("UPDATE payload is not an exact COMPLETE subset: " + ", ".join(extra_or_wrong[:20]))

    report = {
        "baseline": "CajunWebPro-1.25.0-COMPLETE-UPLOAD.zip",
        "baseline_sha256": BASELINE_SHA256,
        "candidate_complete": args.complete.name,
        "candidate_update": args.update.name,
        "update_stage": stage,
        "baseline_files": len(baseline),
        "candidate_files": len(candidate),
        "candidate_update_payload_files": len(payload),
        "changed_or_added_from_1_25": len(changed),
        "removed_since_1_25": len(removed),
        "changed_or_added": changed,
        "removed": removed,
        "scope": "Package-delta preflight only; not a same-version updater apply test",
        "passed": True,
    }
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print("Published v1.25.0 -> candidate UPDATE delta: PASS")
    print(json.dumps({k: report[k] for k in [
        "baseline_files", "candidate_files", "candidate_update_payload_files",
        "changed_or_added_from_1_25", "removed_since_1_25"
    ]}))


if __name__ == "__main__":
    main()
