#!/usr/bin/env python3
"""Read GitHub-pinned sandbox source and one-use nonces without executing Actions."""
from __future__ import annotations
import argparse
import base64
import hashlib
import io
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request
import zipfile

REPO = "afterburn25/Cajun_Web"
API = f"https://api.github.com/repos/{REPO}"
FILES = (
    ".github/workflows/software-authorize-sandbox.yml",
    ".github/workflows/license-financing-authorize-sandbox.yml",
    ".github/workflows/sandbox-workflow-security.yml",
    "tests/sandbox_workflow_contract.py",
    "tests/anet_sandbox_runtime.py",
    "tests/anet_sandbox_runtime_contract.py",
    "tests/anet_sandbox_tokenize.py",
    "tests/software_anet_sandbox_acceptance.py",
    "tests/finance_anet_sandbox_acceptance.py",
    "tests/anet_sandbox_handoff.py",
)
MAX_SOURCE = 200_000
MAX_ZIP = 131_072
MAX_TOKEN = 65_536


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def get_json(url: str, bearer: str) -> dict:
    if not url.startswith(API + "/"):
        raise RuntimeError("Unexpected GitHub API endpoint")
    req = urllib.request.Request(url, headers={
        "Accept": "application/vnd.github+json",
        "Authorization": "Bearer " + bearer,
        "X-GitHub-Api-Version": "2026-03-10",
        "User-Agent": "CajunWebPro-reviewed-sandbox-handoff/1",
    })
    # Do not forward the read-only API bearer across a source-API redirect.
    with urllib.request.build_opener(NoRedirect).open(req, timeout=20) as response:
        raw = response.read(MAX_SOURCE + 1)
        if response.geturl() != url or len(raw) > MAX_SOURCE:
            raise RuntimeError("GitHub API source exceeded bounds or redirected")
    result = json.loads(raw)
    if not isinstance(result, dict):
        raise RuntimeError("GitHub API returned invalid data")
    return result


def file_from_commit(path: str, sha: str, bearer: str) -> bytes:
    if path not in FILES:
        raise RuntimeError("Unreviewed source path")
    obj = get_json(API + "/contents/" + path + "?ref=" + sha, bearer)
    if obj.get("type") != "file" or obj.get("path") != path or obj.get("encoding") != "base64":
        raise RuntimeError("GitHub returned an unexpected source descriptor")
    raw = base64.b64decode("".join(obj.get("content", "").split()), validate=True)
    if not raw or len(raw) > MAX_SOURCE or obj.get("size") != len(raw):
        raise RuntimeError("GitHub source byte size differs")
    digest = hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()
    if obj.get("sha") != digest:
        raise RuntimeError("GitHub source object identity differs")
    return raw


def reviewed_files(sha: str, bearer: str, root: Path) -> None:
    for name in FILES:
        out = root / name
        data = file_from_commit(name, sha, bearer)
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_bytes(data)


def approved_artifact_redirect(url: str) -> bool:
    # The GitHub Actions documentation lists these artifact storage endpoints.
    # Accept only GitHub's named Azure storage accounts, not arbitrary blob
    # accounts, arbitrary GitHub subdomains, or attacker-controlled HTTPS hosts.
    try:
        parsed = urllib.parse.urlsplit(url)
        host = parsed.hostname or ""
        return (
            parsed.scheme == "https"
            and parsed.netloc == host
            and parsed.username is None
            and parsed.password is None
            and parsed.fragment == ""
            and bool(parsed.path) and bool(parsed.query)
            and len(url) <= 8192
            and (
                re.fullmatch(r"productionresultssa[0-9]{1,3}\.blob\.core\.windows\.net", host) is not None
                or host == "results-receiver.actions.githubusercontent.com"
            )
        )
    except ValueError:
        return False


def artifact_zip(archive_url: str, bearer: str) -> bytes:
    if not re.fullmatch(re.escape(API) + r"/actions/artifacts/[0-9]+/zip", archive_url):
        raise RuntimeError("Unexpected artifact archive URL")
    req = urllib.request.Request(archive_url, headers={
        "Authorization": "Bearer " + bearer,
        "Accept": "application/vnd.github+json",
    })
    try:
        with urllib.request.build_opener(NoRedirect).open(req, timeout=20):
            raise RuntimeError("Expected temporary signed artifact redirect")
    except urllib.error.HTTPError as exc:
        if exc.code != 302:
            raise RuntimeError("Cannot obtain reviewed handoff artifact") from None
        redirect = exc.headers.get("Location", "")
    if not approved_artifact_redirect(redirect):
        raise RuntimeError("GitHub artifact redirect is not an approved storage endpoint")
    # Never forward the GitHub bearer to the signed artifact URL. Do not
    # follow a *second* redirect, even between approved storage hosts.
    signed_request = urllib.request.Request(redirect)
    try:
        with urllib.request.build_opener(NoRedirect).open(signed_request, timeout=20) as response:
            if response.geturl() != redirect:
                raise RuntimeError("Artifact download unexpectedly redirected")
            data = response.read(MAX_ZIP + 1)
            if len(data) > MAX_ZIP:
                raise RuntimeError("Artifact archive exceeded the bounded size")
    except (urllib.error.HTTPError, urllib.error.URLError):
        # Signed URLs are bearer credentials for one-use nonces; never format
        # a transport exception that might include the token in CI logs.
        raise RuntimeError("GitHub artifact download failed or redirected") from None
    return data


def token_bytes(data: bytes, kind: str) -> bytes:
    if kind not in ("software", "finance"):
        raise RuntimeError("Unsupported sandbox handoff kind")
    expected = kind + "-tokens.json"
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        items = archive.infolist()
        if len(items) != 1:
            raise RuntimeError("Sandbox artifact must have one member")
        member = items[0]
        if member.filename not in (expected, "sandbox-handoff/" + expected) or member.is_dir():
            raise RuntimeError("Unexpected sandbox handoff member")
        if member.file_size > MAX_TOKEN or member.compress_size > MAX_ZIP:
            raise RuntimeError("Sandbox handoff member too large")
        mode = member.external_attr >> 16
        if mode and (mode & 0o170000) not in (0, 0o100000):
            raise RuntimeError("Nonregular sandbox handoff member")
        with archive.open(member) as file:
            content = file.read(MAX_TOKEN + 1)
        if len(content) > MAX_TOKEN or len(content) != member.file_size:
            raise RuntimeError("Sandbox handoff token bytes differ")
        return content


def download_handoff(kind: str, run_id: str, attempt: str, bearer: str) -> bytes:
    if not run_id.isdecimal() or not attempt.isdecimal():
        raise RuntimeError("Invalid run identity")
    expected = kind + "-anet-nonces-" + run_id + "-" + attempt
    obj = get_json(API + "/actions/runs/" + run_id + "/artifacts?per_page=100", bearer)
    rows = obj.get("artifacts")
    if not isinstance(rows, list) or len(rows) > 100:
        raise RuntimeError("Unexpected artifact index")
    candidates = [a for a in rows if isinstance(a, dict) and a.get("name") == expected]
    if len(candidates) != 1:
        raise RuntimeError("Missing or duplicate run-scoped token artifact")
    item = candidates[0]
    if item.get("expired") or item.get("size_in_bytes", MAX_ZIP + 1) > MAX_ZIP:
        raise RuntimeError("Expired or oversized token artifact")
    if str(item.get("workflow_run", {}).get("id")) != run_id:
        raise RuntimeError("Token artifact belongs to another run")
    url = API + "/actions/artifacts/" + str(item.get("id")) + "/zip"
    if item.get("archive_download_url") != url:
        raise RuntimeError("Token artifact download identity differs")
    return token_bytes(artifact_zip(url, bearer), kind)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--kind", choices=("software", "finance"), required=True)
    args = parser.parse_args()
    if os.environ.get("GITHUB_REPOSITORY") != REPO or os.environ.get("GITHUB_REF") != "refs/heads/main":
        raise RuntimeError("Merchant handoff requires the reviewed main branch")
    sha = os.environ.get("GITHUB_SHA", "")
    if not re.fullmatch(r"[a-f0-9]{40}", sha):
        raise RuntimeError("Invalid reviewed source SHA")
    bearer = os.environ.get("GH_READ_TOKEN", "")
    if not bearer:
        raise RuntimeError("Missing read-only GitHub API token")
    root = Path(__file__).resolve().parents[1]
    reviewed_files(sha, bearer, root)
    data = download_handoff(args.kind, os.environ.get("GITHUB_RUN_ID", ""),
                            os.environ.get("GITHUB_RUN_ATTEMPT", ""), bearer)
    output = root / "sandbox-handoff" / (args.kind + "-tokens.json")
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_bytes(data)
    os.chmod(output, 0o600)
    print("Reviewed same-commit sandbox source and token handoff staged: PASS")


if __name__ == "__main__":
    main()
