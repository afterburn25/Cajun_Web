#!/usr/bin/env python3
"""Exact packaged PHP UI acceptance on Linux; disposable SQL and loopback TLS only."""
from pathlib import Path, PurePosixPath
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import base64
import datetime
import hashlib
import importlib.util
import json
import os
import re
import shutil
import socket
import ssl
import subprocess
import sys
import threading
import time
import traceback
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BASE = "http://127.0.0.1:8095"
SECRET = "SYNTHETIC_PHP_UI_KEY"


def sha(data):
    return hashlib.sha256(data).hexdigest()


def regular(path):
    path = Path(path)
    if path.is_symlink() or not path.is_file():
        raise RuntimeError("Expected a regular fixture file")
    return path


def private_write(path, data):
    with Path(path).open("xb") as handle:
        os.chmod(path, 0o600)
        handle.write(data)


def safe_name(name):
    p = PurePosixPath(name)
    if not name or p.is_absolute() or ".." in p.parts or "\\" in name or ":" in name or str(p) != name:
        raise RuntimeError("Unsafe packaged member")


def extract_package(package, manifest, expected, commit, destination):
    """Validate all members against the recorded Git bytes before creating any file."""
    regular(package)
    if (manifest.get("source_commit") != commit or manifest.get("artifact") != package.name
            or manifest.get("files") != expected or manifest.get("sha256") != sha(package.read_bytes())):
        raise RuntimeError("Package manifest differs from exact source")
    if destination.exists() or destination.is_symlink():
        raise RuntimeError("Package destination already exists")
    with zipfile.ZipFile(package) as archive:
        members = archive.infolist()
        if len(members) != len(expected) or len({m.filename.casefold() for m in members}) != len(members):
            raise RuntimeError("Package member count or identity differs")
        if sum(m.file_size for m in members) > 1024 * 1024 * 1024:
            raise RuntimeError("Package exceeds fixture bound")
        for member in members:
            safe_name(member.filename)
            if (member.filename not in expected or member.is_dir() or member.flag_bits & 1
                    or member.create_system != 3 or member.external_attr >> 16 != 0o100644
                    or member.compress_type != zipfile.ZIP_STORED
                    or sha(archive.read(member)) != expected[member.filename]):
                raise RuntimeError("Package member differs from exact source")
        destination.mkdir(mode=0o755)
        for member in members:
            target = destination / member.filename
            target.parent.mkdir(parents=True, exist_ok=True)
            with target.open("xb") as output:
                output.write(archive.read(member))
        if {p.relative_to(destination).as_posix(): sha(p.read_bytes()) for p in destination.rglob("*") if p.is_file()} != expected:
            raise RuntimeError("Extracted package differs from source")


class PhpProvider:
    """A POST acknowledgement deliberately leaves the independently reported value alone."""
    def __init__(self, runtime):
        from cryptography import x509
        from cryptography.x509.oid import NameOID, ExtendedKeyUsageOID
        from cryptography.hazmat.primitives import hashes, serialization
        from cryptography.hazmat.primitives.asymmetric import rsa
        tls = runtime / "tls"
        tls.mkdir(mode=0o700)
        now = datetime.datetime.now(datetime.timezone.utc)

        def certificate(key, name, issuer, signer, ca):
            subject = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, name)])
            builder = (x509.CertificateBuilder().subject_name(subject).issuer_name(issuer or subject)
                       .public_key(key.public_key()).serial_number(x509.random_serial_number())
                       .not_valid_before(now - datetime.timedelta(minutes=5))
                       .not_valid_after(now + datetime.timedelta(days=1))
                       .add_extension(x509.BasicConstraints(ca=ca, path_length=0 if ca else None), critical=True)
                       .add_extension(x509.SubjectKeyIdentifier.from_public_key(key.public_key()), critical=False)
                       .add_extension(x509.AuthorityKeyIdentifier.from_issuer_public_key(signer.public_key()), critical=False)
                       .add_extension(x509.KeyUsage(digital_signature=True, content_commitment=False,
                                      key_encipherment=not ca, data_encipherment=False, key_agreement=False,
                                      key_cert_sign=ca, crl_sign=ca, encipher_only=None, decipher_only=None), critical=True))
            if not ca:
                builder = (builder.add_extension(x509.SubjectAlternativeName([x509.DNSName("api.20i.com")]), critical=False)
                           .add_extension(x509.ExtendedKeyUsage([ExtendedKeyUsageOID.SERVER_AUTH]), critical=False))
            return builder.sign(signer, hashes.SHA256())

        ca_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
        ca = certificate(ca_key, "Synthetic PHP UI CA", None, ca_key, True)
        key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
        cert = certificate(key, "api.20i.com", ca.subject, ca_key, False)
        private_write(tls / "ca.pem", ca.public_bytes(serialization.Encoding.PEM))
        private_write(tls / "server.pem", cert.public_bytes(serialization.Encoding.PEM))
        private_write(tls / "server.key", key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()))
        self.calls = 0
        self.posts = []
        self.get_paths = []
        self.errors = []
        self.names = []
        self.php_value = "php82"
        fixture = self

        class Handler(BaseHTTPRequestHandler):
            def setup(self):
                super().setup()
                self.connection.settimeout(10)

            def answer(self, status, body):
                self.send_response(status)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(body)))
                self.send_header("Connection", "close")
                self.end_headers()
                self.wfile.write(body)

            def admitted(self):
                fixture.calls += 1
                valid = (self.path in ("/package/701/web/phpVersion", "/package/702/web/phpVersion")
                         and self.headers.get_all("Authorization") == ["Bearer " + base64.b64encode(SECRET.encode()).decode()]
                         and self.headers.get_all("Accept") == ["application/json"]
                         and self.headers.get("Host") == "api.20i.com:" + str(fixture.server.server_port)
                         and self.headers.get("Transfer-Encoding") is None)
                if not valid:
                    fixture.errors.append("fixed request admission failed")
                    self.answer(400, b"false")
                return valid

            def do_GET(self):
                if not self.admitted():
                    return
                if self.headers.get("Content-Length") not in (None, "0"):
                    fixture.errors.append("GET contained a body")
                    self.answer(400, b"false")
                    return
                fixture.get_paths.append(self.path)
                if fixture.php_value not in ("php82", "php84"):
                    fixture.errors.append("unexpected synthetic scalar")
                    self.answer(500, b"null")
                    return
                self.answer(200, json.dumps(fixture.php_value).encode())

            def do_POST(self):
                if not self.admitted():
                    return
                length = self.headers.get("Content-Length", "")
                if (not re.fullmatch(r"[1-9][0-9]{0,3}", length) or int(length) > 8192
                        or self.headers.get_all("Content-Type") != ["application/json"]):
                    fixture.errors.append("invalid setter headers")
                    self.answer(400, b"false")
                    return
                raw = self.rfile.read(int(length))
                try:
                    payload = json.loads(raw)
                except (ValueError, UnicodeError):
                    payload = None
                if (not isinstance(payload, dict) or list(payload) != ["value"] or payload["value"] not in ("php82", "php84")
                        or json.dumps(payload, separators=(",", ":")).encode() != raw):
                    fixture.errors.append("noncanonical synthetic setter")
                    self.answer(400, b"false")
                    return
                fixture.posts.append({"path": self.path, "body": raw.decode("ascii")})
                self.answer(200, b"true")

            def log_message(self, *args):
                pass

        self.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.minimum_version = ssl.TLSVersion.TLSv1_2
        context.load_cert_chain(tls / "server.pem", tls / "server.key")
        context.set_servername_callback(lambda sock, name, ctx: self.names.append(name))
        self.server.socket = context.wrap_socket(self.server.socket, server_side=True)
        private_write(runtime / "transport.json", json.dumps({"port": self.server.server_port, "ca": str(tls / "ca.pem")}).encode())
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()

    def close(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=5)
        if self.thread.is_alive():
            raise RuntimeError("Local provider did not close")


def main():
    if sys.platform != "linux" or os.environ.get("CWP_TEST_DATABASE") != "cwp_test" or os.environ.get("CWP_TEST_HOST", "127.0.0.1") != "127.0.0.1":
        raise SystemExit("This fixture requires isolated Linux and cwp_test on loopback.")
    php = os.environ.get("PHP_BINARY") or shutil.which("php")
    if not php or not re.fullmatch(r"[1-9][0-9]{0,4}", os.environ.get("CWP_TEST_PORT", "33306")):
        raise SystemExit("Missing isolated PHP/database runtime.")
    runtime = ROOT / "test-runtime" / "php-controls-ui"
    qa = ROOT / "qa" / "php-controls-ui"
    for parent in (ROOT / "test-runtime", ROOT / "qa"):
        if parent.is_symlink() or parent.exists() and not parent.is_dir():
            raise SystemExit("Unexpected fixture parent.")
        parent.mkdir(exist_ok=True)
    for fresh in (runtime, qa):
        if fresh.exists() or fresh.is_symlink():
            raise SystemExit("Preserve the previous fixture; this output already exists.")
        fresh.mkdir(mode=0o700)
    (runtime / "web").mkdir()
    checks, provider, process, server_log = [], None, None, None
    report = {"passed": False, "scope": "Exact packaged HTTP, disposable SQL and loopback TLS; positive normalized options are synthetic; no live provider or release acceptance.", "checks": checks}

    def check(name, condition):
        if not isinstance(name, str) or not name or any(c["name"] == name for c in checks) or type(condition) is not bool:
            raise AssertionError("Invalid or duplicate named fixture check")
        checks.append({"name": name, "passed": condition})
        if not condition:
            raise AssertionError(name)

    def fixture(phase, payload):
        if phase not in ("seed", "status", "cool", "options", "contact-add", "contact-clear", "fault-arm", "fault-clear"):
            raise AssertionError("Unknown private fixture phase")
        def retain(stdout, stderr):
            private_write(runtime / ("bridge-failure-" + str(time.time_ns()) + ".txt"), (stdout or b"")[-65536:] + b"\n" + (stderr or b"")[-65536:])
        try:
            result = subprocess.run([php, str(ROOT / "tests/provider_php_controls_ui_fixture.php"), phase],
                                    input=json.dumps(payload).encode(), capture_output=True, cwd=ROOT, timeout=50)
        except subprocess.TimeoutExpired as error:
            retain(error.stdout, error.stderr)
            raise RuntimeError("Private CLI phase timed out: " + phase) from None
        if result.returncode or result.stderr or len(result.stdout) > 65536:
            retain(result.stdout, result.stderr)
            raise RuntimeError("Private CLI phase failed: " + phase)
        try:
            value = json.loads(result.stdout)
        except (ValueError, UnicodeError):
            retain(result.stdout, result.stderr)
            raise RuntimeError("Private CLI phase returned invalid JSON: " + phase) from None
        if not isinstance(value, dict) or value.get("passed") is not True or value.get("phase") != phase:
            retain(result.stdout, result.stderr)
            raise RuntimeError("Private CLI phase returned an invalid receipt")
        return value

    try:
        head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
        if not re.fullmatch(r"[a-f0-9]{40}", head) or subprocess.check_output(["git", "status", "--porcelain", "--untracked-files=all"], cwd=ROOT).strip():
            raise RuntimeError("Packaged fixture requires a clean recorded source")
        spec = importlib.util.spec_from_file_location("php_ui_exact_build", ROOT / "tools/build.py")
        build = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(build)
        tracked = subprocess.check_output(["git", "ls-tree", "-r", "--name-only", head], cwd=ROOT, text=True).splitlines()
        with build.source_blobs(head, tracked) as read:
            expected = {name[5:]: sha(read(name)) for name in tracked if name.startswith("site/")}
        packages = list((ROOT / "dist").glob("CajunWebPro-*-COMPLETE-UPLOAD.zip"))
        if len(packages) != 1:
            raise RuntimeError("Build exactly one current COMPLETE package")
        package = packages[0]
        manifest_path = regular(package.with_suffix(".manifest.json"))
        manifest = json.loads(manifest_path.read_bytes())
        application = runtime / "web" / "php-controls-ui"
        extract_package(package, manifest, expected, head, application)
        check("Every extracted member matches the recorded Git source", True)
        report.update(source_commit=head, source_tree=subprocess.check_output(["git", "rev-parse", "HEAD^{tree}"], cwd=ROOT, text=True).strip(), package_sha256=sha(package.read_bytes()), package_members=len(expected))
        seed = fixture("seed", {})
        check("Fresh seed starts without PHP control stores or provider writes", seed["counts"]["request"] == 0 and seed["counts"]["ledger"] == 0 and seed["counts"]["delegation"] == 0)
        provider = PhpProvider(runtime)
        server_log = (runtime / "server.log").open("xb")
        os.chmod(runtime / "server.log", 0o600)
        process = subprocess.Popen([php, "-d", "auto_prepend_file=" + str(ROOT / "tests/provider_php_controls_ui_connector.php"),
                                    "-d", "session.save_handler=files", "-d", "session.save_path=" + str(runtime / "sessions"),
                                    "-d", "log_errors=1", "-d", "display_errors=0", "-S", "127.0.0.1:8095", "-t", str(runtime / "web")],
                                   cwd=ROOT, stdout=server_log, stderr=server_log)
        import requests
        with requests.Session() as probe:
            probe.trust_env = False
            for _ in range(80):
                if process.poll() is not None:
                    raise RuntimeError("Isolated PHP server stopped during startup")
                try:
                    response = probe.get(BASE + "/php-controls-ui/images/icons/favicon.png", timeout=1)
                    if response.status_code == 200 and sha(response.content) == expected["images/icons/favicon.png"]:
                        break
                except requests.RequestException:
                    pass
                time.sleep(.25)
            else:
                raise RuntimeError("Isolated PHP server did not become ready")
        from playwright.sync_api import sync_playwright
        from provider_php_controls_ui_cases import run_browser, CAPTURES
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch()
            try:
                report["browser_version"] = browser.version
                report["journey"] = run_browser({"BASE": BASE, "QA": qa, "check": check, "fixture": fixture, "provider": provider, "seed": seed}, browser)
            finally:
                browser.close()
        check("Local provider accepted only the exact intended TLS requests", not provider.errors and bool(provider.names) and set(provider.names) == {"api.20i.com"})
        check("All expected browser captures exist with no extra public files", {p.name for p in qa.iterdir()} == set(CAPTURES) and all(regular(qa / name).read_bytes().startswith(b"\x89PNG\r\n\x1a\n") for name in CAPTURES))
        report["captures"] = [{"name": name, "sha256": sha((qa / name).read_bytes())} for name in CAPTURES]
        report["provider"] = {"calls": provider.calls, "posts": len(provider.posts), "gets": len(provider.get_paths)}
        report["passed"] = True
    except BaseException as error:
        frames = traceback.extract_tb(error.__traceback__)
        report["failure"] = {"class": type(error).__name__, "file": Path(frames[-1].filename).name if frames else "", "line": frames[-1].lineno if frames else 0}
        report["passed"] = False
        try:
            private_write(runtime / "failure.txt", traceback.format_exc().encode())
        except OSError:
            report["private_failure_log_unavailable"] = True
    finally:
        def finish(label, work):
            try:
                work()
            except BaseException as error:
                report["passed"] = False
                report.setdefault("cleanup_failures", []).append({"step": label, "class": type(error).__name__})
                try:
                    private_write(runtime / ("cleanup-" + label + ".txt"), traceback.format_exc().encode())
                except OSError:
                    pass  # A failed private write cannot suppress the public failure result.

        def stop_php():
            if process is not None:
                process.terminate()
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=5)

        def inspect_log():
            if server_log is not None:
                server_log.close()
                raw_log = (runtime / "server.log").read_bytes()
                diagnostics = re.findall(rb"PHP (?:Warning|Notice|Deprecated|Fatal error|Parse error):", raw_log)
                report["server_log"] = {"sha256": sha(raw_log), "php_diagnostics": len(diagnostics), "raw_log_private": True}
                checks.append({"name": "Packaged PHP server emits no PHP diagnostics", "passed": not diagnostics})
                if diagnostics:
                    report["passed"] = False

        finish("php", stop_php)
        finish("log", inspect_log)
        if provider is not None:
            finish("tls", provider.close)
        report["count"] = len(checks)
        (qa / "results.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"passed": report["passed"], "count": report["count"], "report": "qa/php-controls-ui/results.json"}))
    return 0 if report["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
