"""Synthetic Chromium layout of actual PHP request views with current shipping CSS.

This does not execute application login, SQL, provider transport, or installed flows.
The PHP companion separately tests actual controller forwarding with business models.
"""
import argparse
import hashlib
import json
import mimetypes
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import subprocess
import tempfile
import threading
from urllib.parse import unquote, urlparse

from playwright.sync_api import sync_playwright


ROOT = Path(__file__).resolve().parents[1]
SCENES = ('Client-Pending', 'Owner-Review', 'Owner-Readback', 'Client-Observed')
CAPTURES = tuple('Modeled-Website-Folder-Requests-' + scene + '-' + size + '.png'
                 for scene in SCENES for size in ('Desktop', 'Mobile'))
SOURCES = ('tests/document_root_requests_browser.py', 'tests/document_root_requests_views.php',
           'site/app/providers/document_root_requests_views.php',
           'site/admin/controllers/document_root_requests.php',
           'site/portal/controllers/document_root_requests.php',
           'site/portal/views/document_root_requests.php',
           'site/assets/css/site.css', 'site/assets/css/admin.css',
           'site/assets/css/premium.css', 'site/assets/css/mail-notices.css',
           'site/assets/css/growth.css', 'VERSION')


def run(php, output, executable=None):
    if output.exists() or output.is_symlink():
        raise ValueError('A fresh external capture directory is required')
    output.mkdir(parents=True)
    checks, captures = [], []

    def check(name, passed):
        if any(row['name'] == name for row in checks):
            raise AssertionError('Duplicate modeled layout check')
        checks.append({'name': name, 'passed': bool(passed)})
        if not passed:
            raise AssertionError(name)

    with tempfile.TemporaryDirectory(prefix='folder-request-model-') as temp:
        pages = Path(temp) / 'pages'
        rendered = subprocess.run([php, str(ROOT / 'tests/document_root_requests_views.php'),
                                   '--render', str(pages)], cwd=ROOT, capture_output=True,
                                  text=True, encoding='utf-8', timeout=30)
        check('Actual PHP renderer exports exactly four modeled scenes', rendered.returncode == 0
              and [row['name'] for row in json.loads(rendered.stdout)['scenes']] == list(SCENES))

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *_args):
                pass

            def do_GET(self):
                path = unquote(urlparse(self.path).path)
                if path.startswith('/assets/'):
                    source = (ROOT / 'site' / path.lstrip('/')).resolve()
                    allowed = source.is_relative_to((ROOT / 'site/assets').resolve())
                else:
                    source = pages / path.lstrip('/')
                    allowed = path in tuple('/' + name + '.html' for name in SCENES)
                if not allowed or not source.is_file():
                    self.send_error(404)
                    return
                data = source.read_bytes()
                self.send_response(200)
                self.send_header('Content-Type', mimetypes.guess_type(source)[0] or 'application/octet-stream')
                self.send_header('Content-Length', str(len(data)))
                self.send_header('Cache-Control', 'no-store')
                self.end_headers()
                self.wfile.write(data)

        server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        base = 'http://127.0.0.1:' + str(server.server_port)
        try:
            with sync_playwright() as playwright:
                browser = playwright.chromium.launch(**({'executable_path': executable} if executable else {}))
                try:
                    context = browser.new_context()
                    context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(base + '/') else route.abort())
                    page = context.new_page()
                    for scene in SCENES:
                        for width, height, size in ((1440, 1050, 'Desktop'), (320, 740, 'Mobile')):
                            label = scene + ' ' + size
                            page.set_viewport_size({'width': width, 'height': height})
                            response = page.goto(base + '/' + scene + '.html')
                            page.evaluate('document.fonts.ready')
                            panel = page.locator('[data-folder-requests]')
                            check(label + ' renders one actual application panel', response.status == 200 and panel.count() == 1)
                            check(label + ' original viewport fits without horizontal scrolling',
                                  page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
                            check(label + ' labels and hints are readable', panel.locator('label,.hint,.btn').evaluate_all(
                                'nodes => nodes.every(el => parseFloat(getComputedStyle(el).fontSize) >= 14)'))
                            check(label + ' opaque paths wrap within card bounds', panel.locator('pre').evaluate_all(
                                'nodes => nodes.every(el => el.scrollWidth <= el.clientWidth && getComputedStyle(el).whiteSpace === "pre-wrap")'))
                            if scene == 'Client-Pending':
                                form = page.locator('[data-folder-request-create]')
                                check(label + ' confirmation starts blank', not form.locator('[name=confirmed]').is_checked())
                                form.locator('[name=name]').select_option('portfolio.example.test')
                                form.locator('[name=desired_path]').fill('public_html/new-portfolio')
                                check(label + ' actual form requires client consent', not form.evaluate('el => el.checkValidity()'))
                                form.locator('[name=confirmed]').check()
                                check(label + ' completed form is valid', form.evaluate('el => el.checkValidity()'))
                                form.locator('[name=confirmed]').uncheck()
                                page.locator('.folder-request-map summary').click()
                            elif scene == 'Owner-Review':
                                form = page.locator('[data-folder-request-approve]')
                                check(label + ' owner password and review start blank',
                                      form.locator('[name=current_password]').input_value() == '' and not form.locator('[name=reviewed]').is_checked())
                                check(label + ' owner form requires consent and password', not form.evaluate('el => el.checkValidity()'))
                            elif scene == 'Owner-Readback':
                                check(label + ' readback is separate from approval',
                                      page.locator('[data-folder-request-verify]').count() == 1 and page.locator('[data-folder-request-approve]').count() == 0)
                            else:
                                check(label + ' observed client result has no owner controls',
                                      page.locator('[data-folder-request-state=observed]').count() == 1
                                      and page.locator('input[type=password]').count() == 0)
                            required = int(panel.bounding_box()['height']) + 100
                            check(label + ' capture height stays bounded', required <= 6000)
                            page.set_viewport_size({'width': width, 'height': max(height, required)})
                            panel.evaluate('el => window.scrollTo({top:scrollY+el.getBoundingClientRect().top-20,behavior:"instant"})')
                            visible = panel.evaluate('''el => {
                                const r=el.getBoundingClientRect();return r.top>=0 && r.bottom<=innerHeight &&
                                [0.1,0.5,0.9].every(x=>[0.02,0.5,0.98].every(y=>{
                                  const n=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);
                                  return n && (n===el || el.contains(n));}));}''')
                            if not visible:
                                page.screenshot(path=str(output / 'layout-failure.png'), full_page=True)
                                (output / 'layout-failure.json').write_text(json.dumps(panel.evaluate('''el=>{
                                    const r=el.getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:r.height,
                                    viewport:innerHeight,hits:[0.1,0.5,0.9].flatMap(x=>[0.02,0.5,0.98].map(y=>{
                                      const n=document.elementFromPoint(r.left+r.width*x,r.top+r.height*y);
                                      return {x,y,tag:n?.tagName,cls:n?.className,inside:!!n&&(n===el||el.contains(n))};}))};}'''), indent=2), encoding='utf-8')
                            check(label + ' full capture panel is unobscured', visible)
                            name = 'Modeled-Website-Folder-Requests-' + scene + '-' + size + '.png'
                            destination = output / name
                            panel.screenshot(path=str(destination))
                            check(label + ' capture has actual PNG bytes', destination.read_bytes().startswith(b'\x89PNG\r\n\x1a\n') and destination.stat().st_size > 1000)
                            captures.append(name)
                finally:
                    browser.close()
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=5)
    check('Eight modeled captures are exact and distinct from installed captures', tuple(captures) == CAPTURES)
    return {'passed': True, 'scope': 'Actual PHP renderer and shipping CSS in synthetic Chromium pages; modeled shell/data, no SQL/login/provider/installed acceptance',
            'checks': checks, 'captures': captures,
            'source_sha256': {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest() for name in SOURCES}}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True)
    parser.add_argument('--report', required=True, type=Path)
    parser.add_argument('--output', type=Path, default=ROOT / 'test-runtime/document-root-requests-modeled')
    parser.add_argument('--browser', help='Optional local Chromium executable; CI uses installed Playwright Chromium')
    args = parser.parse_args()
    try:
        result = run(args.php, args.output, args.browser)
    except Exception as error:
        result = {'passed': False, 'scope': 'Modeled actual-renderer Chromium check; no installed acceptance',
                  'error_class': type(error).__name__, 'error': str(error)}
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'passed': result['passed'], 'report': str(args.report)}))
    raise SystemExit(0 if result['passed'] else 1)
