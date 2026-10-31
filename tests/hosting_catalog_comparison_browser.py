"""Synthetic local Chromium renderer QA; no application server, SQL or provider transport."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import struct
import subprocess
import sys
import zlib

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = "http://127.0.0.1:18329"  # Intercepted completely; no socket is opened.
STYLES = ("site.css", "premium.css", "growth.css", "hosting-comparison.css")
CAPTURES = (
    "Hosting-Comparison-Two-Desktop.png",
    "Hosting-Comparison-Four-Desktop.png",
    "Hosting-Comparison-Four-Mobile-First.png",
    "Hosting-Comparison-Four-Mobile-Last.png",
)


def png_fixture() -> bytes:
    """Fixed synthetic local artwork, with a real decodable PNG header and pixels."""
    def chunk(kind: bytes, data: bytes) -> bytes:
        return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", zlib.crc32(kind + data))
    pixels = b"".join(b"\0" + bytes(v for x in range(240) for v in (20 + x // 4, 55 + y // 3, 92 + x // 5)) for y in range(144))
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", 240, 144, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(pixels)) + chunk(b"IEND", b"")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", default="php")
    parser.add_argument("--browser-executable", type=Path, help="Optional installed Chromium executable; default uses Playwright Chromium")
    parser.add_argument("--report", type=Path, default=ROOT / "qa/hosting-catalog-comparison-browser-results.json")
    parser.add_argument("--output-dir", type=Path, default=ROOT / "qa/hosting-catalog-comparison")
    args = parser.parse_args()
    if args.report.exists() or args.report.is_symlink() or args.output_dir.exists() or args.output_dir.is_symlink():
        parser.error("Refusing to overwrite retained renderer evidence.")
    args.output_dir.mkdir(parents=True)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    checks: list[dict] = []
    captures: list[dict] = []
    errors: list[str] = []
    requests: list[str] = []
    css = {"/nested/assets/css/" + name: (ROOT / "site/assets/css" / name).read_bytes() for name in STYLES}
    artwork = png_fixture()
    source = ["tests/hosting_catalog_comparison.php", "tests/hosting_catalog_comparison_browser.py", "site/includes/layout/hosting_catalog.php", "site/includes/urls.php", "site/pages/services/hosting.php"] + ["site/assets/css/" + name for name in STYLES]

    def check(name: str, passed: bool) -> None:
        if type(passed) is not bool or any(row["name"] == name for row in checks):
            raise AssertionError("Non-unique or non-boolean renderer check")
        checks.append({"name": name, "passed": passed})
        if not passed:
            raise AssertionError(name)

    try:
        html = {}
        for count in (2, 3, 4):
            result = subprocess.run([args.php, str(ROOT / "tests/hosting_catalog_comparison.php"), "--render", str(count)], cwd=ROOT, capture_output=True, timeout=30)
            check(f"{count} plans render without PHP errors", result.returncode == 0 and result.stderr == b"" and b"<!doctype html>" in result.stdout)
            html[f"/plans/{count}"] = result.stdout
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(**({"executable_path": str(args.browser_executable)} if args.browser_executable else {}))
            context = browser.new_context(service_workers="block", reduced_motion="reduce")
            def route(request_route):
                request = request_route.request
                if not request.url.startswith(ORIGIN + "/"):
                    errors.append("Unexpected synthetic resource origin")
                    request_route.abort(); return
                path = request.url[len(ORIGIN):]
                requests.append(path)
                if path in html:
                    request_route.fulfill(status=200, content_type="text/html; charset=utf-8", body=html[path])
                elif path.removesuffix("?v=synthetic-render") in css:
                    request_route.fulfill(status=200, content_type="text/css; charset=utf-8", body=css[path.removesuffix("?v=synthetic-render")])
                elif path == "/nested/media/index.php?id=" + "a" * 32:
                    request_route.fulfill(status=200, content_type="image/png", body=artwork)
                elif path == "/favicon.ico":
                    request_route.fulfill(status=204, body="")
                else:
                    # Base CSS uses fonts/backgrounds. They are local presentation assets only.
                    base = ROOT / "site"
                    local = (base / path.removeprefix("/nested/")).resolve()
                    if path.startswith("/nested/") and local.is_relative_to(base.resolve()) and local.is_file() and local.suffix in (".woff2", ".woff", ".webp", ".png", ".jpg"):
                        request_route.fulfill(status=200, body=local.read_bytes())
                    else:
                        errors.append("Unexpected synthetic resource path")
                        request_route.abort()
            context.route("**/*", route)
            page = context.new_page()
            page.on("pageerror", lambda error: errors.append("Chromium page error"))
            for count in (2, 3, 4):
                for width, height, mode in ((1440, 1100, "desktop"), (320, 740, "mobile")):
                    label = f"{count} plans {mode}"
                    page.set_viewport_size({"width": width, "height": height})
                    page.goto(ORIGIN + f"/plans/{count}", wait_until="networkidle")
                    page.evaluate("document.fonts.ready")
                    region = page.locator(".hosting-comparison-scroll")
                    check(label + " has no document horizontal overflow", page.evaluate("document.documentElement.scrollWidth <= window.innerWidth") is True)
                    expected_links = ["/nested/portal/index.php?hosting=checkout&offer=" + f"{i:032x}" + "&offer_sha256=" + hashlib.sha256(f"offer-{i}".encode()).hexdigest() for i in range(1, count + 1)]
                    check(label + " retains every column and exact checkout identity", page.locator("thead th[scope=col]").count() == count + 1 and page.locator("a[href*='hosting=checkout']").evaluate_all("nodes => nodes.map(n => n.getAttribute('href'))") == expected_links)
                    check(label + " loads all local synthetic product images", page.locator(".hosting-comparison-image").evaluate_all("nodes => nodes.length > 0 && nodes.every(n => n.complete && n.naturalWidth === 240 && n.naturalHeight === 144)") is True)
                    check(label + " aligns every comparison row", page.locator("tbody tr").evaluate_all("rows => rows.every(r => { const b = [...r.cells].map(c => c.getBoundingClientRect()); return b.every(v => Math.abs(v.top-b[0].top)<1 && Math.abs(v.bottom-b[0].bottom)<1); })") is True)
                    region.focus()
                    check(label + " exposes keyboard focus on named region", region.evaluate("n => n === document.activeElement && !!n.getAttribute('aria-labelledby')") is True)
                    if mode == "desktop":
                        check(label + " fits all columns inside comparison", region.evaluate("n => n.scrollWidth <= n.clientWidth + 1") is True)
                    else:
                        check(label + " scrolls only inside its region", region.evaluate("n => n.scrollWidth > n.clientWidth") is True)
                        if count == 4:
                            path = args.output_dir / CAPTURES[2]
                            region.screenshot(path=str(path), animations="disabled")
                            captures.append({"name": path.name, "sha256": hashlib.sha256(path.read_bytes()).hexdigest(), "scope": "First mobile columns; exact synthetic fixture"})
                        for _ in range(60):
                            page.keyboard.press("ArrowRight", delay=10)
                        page.wait_for_function("() => { const n=document.querySelector('.hosting-comparison-scroll'); return n.scrollLeft >= n.scrollWidth-n.clientWidth-2; }", timeout=5000)
                        check(label + " reaches the final column by keyboard", region.evaluate("n => n.scrollLeft >= n.scrollWidth-n.clientWidth-2") is True)
                        check(label + " final action fits the visible region", page.locator("a[href*='hosting=checkout']").last.evaluate("n => {const a=n.getBoundingClientRect(),r=n.closest('.hosting-comparison-scroll').getBoundingClientRect(),l=n.closest('tr').cells[0].getBoundingClientRect();return a.left>=l.right-1 && a.right<=r.right+1;}") is True)
                    capture = CAPTURES[0] if count == 2 and mode == "desktop" else CAPTURES[1] if count == 4 and mode == "desktop" else CAPTURES[3] if count == 4 and mode == "mobile" else None
                    if capture:
                        path = args.output_dir / capture
                        region.screenshot(path=str(path), animations="disabled")
                        captures.append({"name": path.name, "sha256": hashlib.sha256(path.read_bytes()).hexdigest(), "scope": "Synthetic comparison region; final scroll position on mobile"})
            check("Every browser request stayed in the closed local fixture", errors == [])
            check("All four actual page stylesheets used the real versioned asset route", all(path + "?v=synthetic-render" in requests for path in css))
            check("Exactly four scoped captures were produced", sorted(c["name"] for c in captures) == sorted(CAPTURES))
            context.close(); browser.close()
        passed = True
    except Exception as error:
        passed = False
        errors.append(type(error).__name__ + ": " + str(error))
    report = {"passed": passed, "checks": checks, "count": len(checks), "captures": captures, "errors": errors,
              "source_sha256": {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in source},
              "synthetic_png_sha256": hashlib.sha256(artwork).hexdigest(),
              "scope": "Actual PHP comparison renderer and Chromium with original CSS, intercepted local synthetic images and identities. No application server, SQL, authentication, provider call or checkout execution. Pixel review is separate."}
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"passed": passed, "count": len(checks), "report": str(args.report)}))
    return 0 if passed else 1


if __name__ == "__main__":
    sys.exit(main())
