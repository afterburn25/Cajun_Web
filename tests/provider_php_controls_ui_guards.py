"""Portable exact-package and real local Python TLS checks, not PHP/SQL acceptance."""
from pathlib import Path
import base64
import json
import socket
import ssl
import tempfile
import zipfile
from provider_php_controls_ui_browser import ROOT, PhpProvider, SECRET, extract_package, sha


def main():
    checks = []

    def check(name, ok):
        if type(ok) is not bool or any(row["name"] == name for row in checks):
            raise AssertionError("Invalid guard check")
        checks.append({"name": name, "passed": ok})
        if not ok:
            raise AssertionError(name)

    parent = ROOT / "test-runtime"
    if parent.is_symlink():
        raise RuntimeError("Unexpected test parent")
    parent.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="php-ui-guards-", dir=parent) as directory:
        temp = Path(directory).resolve()
        if temp.parent != parent.resolve():
            raise RuntimeError("Temporary guard directory escaped its parent")

        def package(name, entries):
            path = temp / (name + ".zip")
            with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_STORED) as out:
                for member, data, mode in entries:
                    info = zipfile.ZipInfo(member)
                    info.create_system = 3
                    info.external_attr = mode << 16
                    out.writestr(info, data)
            expected = {member: sha(data) for member, data, _ in entries}
            manifest = {"source_commit": "a" * 40, "artifact": path.name, "sha256": sha(path.read_bytes()), "files": expected}
            return path, manifest, expected

        p, m, expected = package("valid", [("index.php", b"safe", 0o100644), ("includes/example.php", b"nested", 0o100644)])
        destination = temp / "accepted"
        extract_package(p, m, expected, "a" * 40, destination)
        check("Exact package bytes extract into their own fresh directory", (destination / "index.php").read_bytes() == b"safe" and (destination / "includes/example.php").read_bytes() == b"nested")

        def refuse(name, fn):
            rejected = False
            try:
                fn()
            except RuntimeError:
                rejected = True
            check(name, rejected)

        refuse("Existing destination is never overwritten", lambda: extract_package(p, m, expected, "a" * 40, destination))
        check("Refusal preserves prior extracted contents", (destination / "index.php").read_bytes() == b"safe")
        refuse("Different recorded commit is rejected", lambda: extract_package(p, m, expected, "b" * 40, temp / "wrong-commit"))
        refuse("Archive digest mismatch is rejected", lambda: extract_package(p, dict(m, sha256="0" * 64), expected, "a" * 40, temp / "wrong-digest"))
        forged = dict(expected, **{"index.php": "0" * 64})
        refuse("Forged manifest cannot replace exact source bytes", lambda: extract_package(p, dict(m, files=forged), forged, "a" * 40, temp / "wrong-file"))
        for name, entries in [
            ("traversal", [("../escape.php", b"bad", 0o100644)]),
            ("backslash", [("sub\\file.php", b"bad", 0o100644)]),
            ("link", [("link.php", b"target.php", 0o120777)]),
            ("collision", [("a.php", b"one", 0o100644), ("A.php", b"two", 0o100644)]),
        ]:
            path, manifest, source = package(name, entries)
            target = temp / ("reject-" + name)
            refuse("Reject unsafe package " + name, lambda: extract_package(path, manifest, source, "a" * 40, target))
            check("Unsafe " + name + " creates no destination", not target.exists())

        runtime = temp / "tls-fixture"
        runtime.mkdir()
        provider = PhpProvider(runtime)
        try:
            context = ssl.create_default_context(cafile=str(runtime / "tls/ca.pem"))

            def request(method, path, body=b"", auth=True):
                headers = [method + " " + path + " HTTP/1.1", "Host: api.20i.com:" + str(provider.server.server_port), "Accept: application/json", "Connection: close"]
                if auth:
                    headers.append("Authorization: Bearer " + base64.b64encode(SECRET.encode()).decode())
                if method == "POST":
                    headers += ["Content-Type: application/json", "Content-Length: " + str(len(body))]
                with socket.create_connection(("127.0.0.1", provider.server.server_port), timeout=5) as raw:
                    with context.wrap_socket(raw, server_hostname="api.20i.com") as secured:
                        secured.sendall(("\r\n".join(headers) + "\r\n\r\n").encode() + body)
                        result = b""
                        while True:
                            chunk = secured.recv(8192)
                            if not chunk:
                                break
                            result += chunk
                            if len(result) > 16384:
                                raise RuntimeError("Fixture response exceeded bound")
                head, payload = result.split(b"\r\n\r\n", 1)
                return int(head.split(b" ", 2)[1]), payload

            path = "/package/701/web/phpVersion"
            check("TLS fixture presents the validated API hostname and current scalar", request("GET", path) == (200, b'"php82"') and provider.names == ["api.20i.com"])
            check("Exact canonical setter receives an acknowledgement", request("POST", path, b'{"value":"php84"}') == (200, b"true"))
            check("POST never changes the separate reported scalar", request("GET", path) == (200, b'"php82"') and provider.php_value == "php82")
            provider.php_value = "php84"
            check("Only explicit fixture state changes later GET", request("GET", path) == (200, b'"php84"'))
            check("Unexpected package is refused", request("GET", "/package/999/web/phpVersion")[0] == 400)
            check("Missing synthetic authorization is refused", request("GET", path, auth=False)[0] == 400)
            check("Noncanonical JSON setter is refused", request("POST", path, b'{"value": "php82"}')[0] == 400)
            check("Extra setter fields are refused", request("POST", path, b'{"value":"php82","extra":true}')[0] == 400)
            check("Only the original exact admitted setter is retained", provider.posts == [{"path": path, "body": '{"value":"php84"}'}] and len(provider.errors) == 4)
        finally:
            provider.close()
    result = {"passed": True, "count": len(checks), "checks": checks,
              "scope": "Synthetic archive guards and actual local Python TLS fixture; no PHP, SQL, authentication, provider API or packaged browser journey executed."}
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
