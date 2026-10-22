"""Packaged PHP settings HTTP/forms and Chromium captures; synthetic local TLS only.

run_browser(env, browser) uses the fixed runner/fixture contract in INTEGRATION.txt.
No direct SQL, commercial row editing, production overrides or provider calls live here.
"""
import re
from http.cookies import SimpleCookie
from urllib.parse import parse_qs, urlencode, urljoin, urlparse

import requests
from bs4 import BeautifulSoup

OWNER_PASSWORD = "Synthetic PHP UI owner passphrase"
CLIENT_PASSWORD = "Synthetic PHP UI client passphrase"
CAPTURE_TITLES = (
    "PHP-Controls-Owner", "PHP-Controls-Client",
    "PHP-Controls-Owner-Review", "PHP-Controls-Client-Review",
    "PHP-Controls-Owner-History", "PHP-Controls-Client-History",
    "PHP-Controls-Owner-Recovery", "PHP-Controls-Client-Unavailable",
)
CAPTURES = tuple(f"{title}-{size}.png" for title in CAPTURE_TITLES for size in ("Desktop", "Mobile"))
ACTIONS = ("prepare", "delegate", "read", "review", "change", "verify", "recover")


def soup(response):
    return BeautifulSoup(response.text, "html.parser")


def fields(form):
    """Successful single-value controls only; unchecked boxes remain absent."""
    values = {}
    for node in form.select("input[name],select[name],textarea[name]"):
        if node.has_attr("disabled") or node.get("type") in ("button", "submit", "file"):
            continue
        if node.get("type") in ("checkbox", "radio") and not node.has_attr("checked"):
            continue
        name = node["name"]
        if name in values:
            raise AssertionError("Unexpected duplicate form field")
        if node.name == "select":
            selected = node.select_one("option[selected]") or node.select_one("option")
            values[name] = selected.get("value", selected.get_text()) if selected else ""
        elif node.name == "textarea":
            values[name] = node.get_text()
        else:
            values[name] = node.get("value", "")
    return values


def form_for(response, selector):
    forms = soup(response).select(selector)
    if len(forms) != 1 or forms[0].name != "form" or forms[0].get("method", "get").lower() != "post":
        raise AssertionError("Expected one real POST form: " + selector)
    return forms[0]


def form_action(response, form):
    target = urljoin(response.url, form.get("action") or response.url)
    if urlparse(target).scheme != "http" or urlparse(target).netloc != "127.0.0.1:8095":
        raise AssertionError("Form escaped the fixed loopback fixture")
    if not urlparse(target).path.startswith("/php-controls-ui/"):
        raise AssertionError("Form escaped the packaged application")
    return target


def csrf(response):
    token = soup(response).select_one('input[name="csrf"]')
    if token is None or not token.get("value"):
        raise AssertionError("Actual page has no CSRF token")
    return token["value"]


def session_id(session, actual_url):
    # Select cookies by the actual portal/admin URL, including its trailing path.
    header = session.prepare_request(requests.Request("GET", actual_url)).headers.get("Cookie", "")
    parsed = SimpleCookie(); parsed.load(header)
    candidates = [m.value for name, m in parsed.items() if re.fullmatch(r"cwp_[a-f0-9]{12}", name)]
    if len(candidates) != 1 or not re.fullmatch(r"[A-Za-z0-9,-]{24,128}", candidates[0]):
        raise AssertionError("Expected one current application session cookie")
    return candidates[0]  # stdin only; never put this in a check, report or exception.


def run_browser(env, browser):
    base, qa, check, fixture, provider, seed = (env[k] for k in ("BASE", "QA", "check", "fixture", "provider", "seed"))
    if base != "http://127.0.0.1:8095":
        raise AssertionError("Wrong isolated HTTP origin")
    for key in ("link_id", "other_link_id"):
        if not re.fullmatch(r"[a-f0-9]{32}", seed[key]):
            raise AssertionError("Invalid seed assignment identity")
    if seed["package_id"] != 701 or seed["other_package_id"] != 702:
        raise AssertionError("Unexpected fixture packages")
    admin = base + "/php-controls-ui/admin/index.php"
    portal = base + "/php-controls-ui/portal/index.php"
    link = seed["link_id"]
    owner_url = admin + "?" + urlencode({"view": "provider-php-controls", "service_link": link})
    client_url = portal + "?" + urlencode({"php_controls": 1, "service_link": link})
    request_ids, captures, sessions = [], [], []
    operations = {"read": 0, "options": 0, "review": 0}

    def operation(kind, detail):
        operations[kind] += 1
        return f"{kind.capitalize()} {operations[kind]} ({detail})"

    def state():
        result = fixture("status", {"request_ids": request_ids})
        if result.get("passed") is not True or not re.fullmatch(r"[a-f0-9]{64}", result["protected_sha256"]):
            raise AssertionError("Fixture status unavailable")
        return result

    def post_form(session, address, selector, changes=None, remove=()):
        response = session.get(address, timeout=30)
        form = form_for(response, selector)
        data = fields(form); data.update(changes or {})
        for name in remove:
            data.pop(name, None)
        return session.post(form_action(response, form), data=data, timeout=120), data

    def no_effect(before, calls, label):
        check(label, state()["protected_sha256"] == before["protected_sha256"] and provider.calls == calls)

    def private_safe(response, label):
        forbidden = (OWNER_PASSWORD, CLIENT_PASSWORD, "SYNTHETIC_PHP_UI_KEY", "key_fingerprint",
                     "credential_sha256", "owner_auth_version", "client_auth_version", "document_json")
        check(label, not any(value in response.text for value in forbidden)
              and all(not node.get("value") for node in soup(response).select('input[type="password"]')))

    def login(email, password, owner):
        session = requests.Session(); session.trust_env = False; sessions.append(session)
        address = admin + "?view=login" if owner else portal
        response = session.get(address, timeout=30)
        form = next((f for f in soup(response).select("form") if f.select_one('input[name="action"][value="login"]')), None)
        if form is None:
            raise AssertionError("Actual login form missing")
        data = fields(form); data.update({"email": email, "password": password})
        response = session.post(form_action(response, form), data=data, timeout=30)
        check("PHP UI actual login " + email, response.status_code == 200 and not soup(response).select_one('input[name="action"][value="login"]'))
        return session

    def cool():
        result = fixture("cool", {})
        if result.get("passed") is not True or not 0 <= result["waited_ms"] <= 32000:
            raise AssertionError("Genuine cooldown could not be established")

    def options(session, actor, mode="normal"):
        label = operation("options", actor + " " + mode)
        cool()
        result = fixture("options", {"session_id": session_id(session, portal), "actor": actor,
                                     "link_id": link, "mode": mode})
        check(label + " records exact options source",
              result.get("passed") is True and result.get("synthetic_options") is (mode != "unavailable")
              and result["kind"] == "available" and result["state"] == ("unavailable" if mode == "unavailable" else "complete"))
        return result

    def read(session, address, kind, owner):
        label = operation("read", ("owner" if owner else "client") + " " + kind)
        cool()
        response, _ = post_form(session, address, f'form[data-php-read="{kind}"]',
                                {"current_password": OWNER_PASSWORD} if owner else {})
        check(label + " actual form returns dedicated page",
              response.status_code == 200 and soup(response).select_one("[data-php-controls]") is not None)
        return response

    def review(session, address, desired):
        label = operation("review", ("owner" if address == owner_url else "client") + " " + desired)
        before, calls = state(), provider.calls
        response, selected = post_form(session, address, "form[data-php-select]", {"desired": desired})
        confirmation = fields(form_for(response, "form[data-php-confirm]"))
        check(label + " retains exact selected identity and observations",
              all(confirmation[k] == selected[k] for k in ("request_id", "current_read_id", "options_read_id", "desired"))
              and re.fullmatch(r"[a-f0-9]{64}", confirmation["review_sha256"]) is not None)
        no_effect(before, calls, label + " does not send or create a request")
        private_safe(response, label + " projection and password are private-safe")
        return confirmation

    def request_row(request_id):
        return state()["requests"][request_id]

    def capture(session, title, address, selector, desired=None):
        context = browser.new_context(viewport={"width": 1440, "height": 1050}, reduced_motion="reduce")
        context.add_cookies([{"name": c.name, "value": c.value, "domain": c.domain, "path": c.path} for c in session.cookies])
        page = context.new_page(); errors = []
        page.on("pageerror", lambda error: errors.append(type(error).__name__))
        try:
            for width, height, size in ((1440, 1050, "Desktop"), (320, 740, "Mobile")):
                page.set_viewport_size({"width": width, "height": height})
                response = page.goto(address, wait_until="networkidle")
                if response is None or response.status != 200:
                    raise AssertionError("Capture route was not successful")
                if desired is not None:
                    page.locator("form[data-php-select] select[name=desired]").select_option(desired)
                    page.locator("form[data-php-select] button[type=submit]").click()
                    page.locator("form[data-php-confirm]").wait_for(state="visible")
                page.evaluate("document.fonts.ready")
                page.locator(selector).first.wait_for(state="visible")
                page.evaluate("window.scrollTo({top:0,left:0,behavior:'instant'})")
                page.evaluate("new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))")
                check(title + " " + size + " fits viewport", page.evaluate("document.documentElement.scrollWidth <= innerWidth"))
                check(title + " " + size + " has visible target", page.locator(selector).first.is_visible())
                name = title + "-" + size + ".png"
                if (qa / name).exists():
                    raise AssertionError("Capture output already exists")
                page.screenshot(path=str(qa / name), full_page=True); captures.append(name)
            check(title + " has no page errors", not errors)
        finally:
            context.close()

    try:
        owner = login("php-ui-owner@example.invalid", OWNER_PASSWORD, True)
        staff = login("php-ui-staff@example.invalid", OWNER_PASSWORD, True)
        client = login("php-ui-client1@example.invalid", CLIENT_PASSWORD, False)
        other = login("php-ui-client2@example.invalid", CLIENT_PASSWORD, False)
        anonymous = requests.Session(); anonymous.trust_env = False; sessions.append(anonymous)
        before, calls = state(), provider.calls
        owner_entry = owner.get(admin + "?view=provider-settings", timeout=30)
        client_entry = client.get(portal + "?services=1", timeout=30)
        for label, page, expected in (("Owner", owner_entry, owner_url), ("Client", client_entry, client_url)):
            links = [urljoin(page.url, n["href"]) for n in soup(page).select("a[href]")]
            check(label + " hosting page links the exact assigned PHP settings", page.status_code == 200 and expected in links)
        start = owner.get(next(urljoin(owner_entry.url, n["href"]) for n in soup(owner_entry).select("a[href]") if urljoin(owner_entry.url, n["href"]) == owner_url), timeout=30)
        client_landing = client.get(next(urljoin(client_entry.url, n["href"]) for n in soup(client_entry).select("a[href]") if urljoin(client_entry.url, n["href"]) == client_url), timeout=30)
        check("Owner GET offers explicit setup", start.status_code == 200 and soup(start).select_one("form[data-php-prepare]") is not None)
        check("Saved scalar sharing alone grants no PHP change form", "php82" in client.get(portal + "?php_version=1&service_link=" + link, timeout=30).text
              and soup(client_landing).select_one("form[data-php-read]") is None)
        check("Ordinary staff cannot open owner settings", staff.get(owner_url, timeout=30).status_code == 403)
        check("Anonymous controls reveal no authorized screen", soup(anonymous.get(client_url, timeout=30)).select_one("[data-php-controls]") is None)
        check("Other client cannot obtain selected assignment controls", soup(other.get(client_url, timeout=30)).select_one("form[data-php-read]") is None)
        no_effect(before, calls, "All initial GETs preserve protected state and provider calls")
        for label, changes in (("CSRF", {"csrf": "invalid", "current_password": OWNER_PASSWORD}),
                               ("password", {"current_password": "wrong"})):
            before, calls = state(), provider.calls
            response, _ = post_form(owner, owner_url, "form[data-php-prepare]", changes)
            check("Setup rejects invalid " + label, "session expired" in response.text.lower() if label == "CSRF" else "current password" in response.text.lower())
            no_effect(before, calls, "Invalid setup " + label + " cannot prepare storage")
        response, _ = post_form(owner, owner_url, "form[data-php-prepare]", {"current_password": OWNER_PASSWORD})
        check("Explicit owner setup prepares both stores locally", state()["markers"] == {"delegation": "1", "lifecycle": "1"}
              and state()["counts"]["request"] == 0 and provider.calls == calls)
        for label, changes, remove in (("password", {"current_password": "wrong", "confirmed": "1"}, ()),
                                       ("confirmation", {"current_password": OWNER_PASSWORD}, ("confirmed",)),
                                       ("CSRF", {"csrf": "invalid", "current_password": OWNER_PASSWORD, "confirmed": "1"}, ())):
            before, calls = state(), provider.calls
            response, submitted = post_form(owner, owner_url, 'form[data-php-delegate="enable"]', changes, remove)
            no_effect(before, calls, "Invalid delegation " + label + " cannot grant permission")
            private_safe(response, "Invalid delegation " + label + " retains no private context")
            if label in ("password", "confirmation"):
                retained = fields(form_for(response, 'form[data-php-delegate="enable"]'))
                check("Delegation retry preserves exact " + label + " intent", all(retained[k] == submitted[k] for k in ("expected_permission_id", "event_id", "enabled", "review_sha256")))
        response, _ = post_form(owner, owner_url, 'form[data-php-delegate="enable"]', {"current_password": OWNER_PASSWORD, "confirmed": "1"})
        check("Owner explicitly delegates current primary client", state()["counts"]["delegation"] == 1
              and soup(client.get(client_url, timeout=30)).select_one('form[data-php-read="current"]') is not None)

        # The CLI changes only disposable session marker fields, never application authority.
        client_token = csrf(client.get(client_url, timeout=30))
        fixture("contact-add", {"session_id": session_id(client, portal)})
        try:
            before, calls = state(), provider.calls
            check("Retained contact context is rejected before GET normalization", client.get(client_url, timeout=30).status_code == 403)
            for action in ACTIONS:
                response = client.post(client_url, data={"csrf": client_token, "action": "php_controls_" + action, "link_id": link}, timeout=30)
                check("Contact context blocks action namespace " + action, response.status_code == 403)
            no_effect(before, calls, "Contact gate performs no lifecycle or provider work")
        finally:
            fixture("contact-clear", {"session_id": session_id(client, portal)})
        before, calls = state(), provider.calls
        for role, session, address in (("owner", owner, owner_url), ("client", client, client_url)):
            check("Support query blocks " + role + " settings", session.get(address + "&support=invalid", timeout=30).status_code == 403)
        no_effect(before, calls, "Support-query denials preserve protected state")
        support_url = admin + "?view=support"
        page = owner.get(support_url, timeout=30)
        policy = next(f for f in soup(page).select("form") if f.select_one('input[name=action][value=support_policy]') and f.select_one('input[name=client_id][value="1"]'))
        data = fields(policy); data.update({"allowed": "1", "owner_password": OWNER_PASSWORD}); owner.post(form_action(page, policy), data=data, timeout=30)
        page = owner.get(support_url, timeout=30)
        opening = next(f for f in soup(page).select("form") if f.select_one('input[name=action][value=support_start]') and f.select_one('input[name=client_id][value="1"]'))
        opened = owner.post(form_action(page, opening), data=fields(opening), timeout=30)
        support_id = parse_qs(urlparse(opened.url).query).get("support", [""])[0]
        check("Actual owner support form opens scoped support", opened.status_code == 200 and re.fullmatch(r"[a-f0-9]{32}", support_id) is not None)
        before, calls = state(), provider.calls
        check("Real support context cannot enter PHP controls", owner.get(client_url + "&support=" + support_id, timeout=30).status_code == 403)
        no_effect(before, calls, "Actual support refusal preserves provider state")
        end_form = next(f for f in soup(opened).select("form") if f.select_one('input[name=action][value=support_end]'))
        owner.post(form_action(opened, end_form), data=fields(end_form), timeout=30)

        provider.php_value = "php82"
        fixture("inventory", {})
        read(owner, owner_url, "current", True)
        check("Owner scalar read records true staff actor", state()["latest"]["current"]["actor_kind"] == "staff" and state()["latest"]["current"]["actor_id"] == 1)
        options(owner, "owner", "empty")
        check("Empty typed choices show no selection", soup(owner.get(owner_url, timeout=30)).select_one("form[data-php-select]") is None)
        options(owner, "owner")
        calls = provider.calls
        response = read(owner, owner_url, "available", True)
        check("Default HTTP choices remain unavailable without TLS request", state()["latest"]["available"]["state"] == "unavailable" and provider.calls == calls)
        check("Latest failed choices suppress older synthetic success", soup(response).select_one("form[data-php-select]") is None and "No version can be selected" in response.text)
        options(owner, "owner")
        page = owner.get(owner_url, timeout=30)
        check("Synthetic exact choices retain labels values and handlers", [n.get("value") for n in soup(page).select('select[name=desired] option')][1:] == ["php82", "php84"]
              and "Synthetic PHP 8.4" in page.text and "Synthetic handler" in page.text)
        capture(owner, "PHP-Controls-Owner", owner_url, "[data-php-controls]")
        confirmation = review(owner, owner_url, "php84")
        capture(owner, "PHP-Controls-Owner-Review", owner_url, "[data-php-review]", "php84")
        for label, changes, remove in (("password", {"current_password": "wrong", "confirmed": "1"}, ()),
                                       ("confirmation", {"current_password": OWNER_PASSWORD}, ("confirmed",)),
                                       ("CSRF", {"csrf": "invalid", "current_password": OWNER_PASSWORD, "confirmed": "1"}, ()),
                                       ("review hash", {"review_sha256": "f" * 64, "current_password": OWNER_PASSWORD, "confirmed": "1"}, ()),
                                       ("exact value", {"desired": "PHP84", "current_password": OWNER_PASSWORD, "confirmed": "1"}, ()),
                                       ("cross link", {"link_id": seed["other_link_id"], "current_password": OWNER_PASSWORD, "confirmed": "1"}, ())):
            before, calls = state(), provider.calls; data = dict(confirmation); data.update(changes)
            for key in remove: data.pop(key, None)
            response = owner.post(owner_url, data=data, timeout=120)
            no_effect(before, calls, "Invalid change " + label + " cannot create or send request")
            private_safe(response, "Invalid change " + label + " retains no password/private context")
            if label in ("password", "confirmation"):
                retained = fields(form_for(response, "form[data-php-confirm]"))
                check("Safe retry preserves original " + label + " review", all(retained[k] == confirmation[k] for k in ("request_id", "current_read_id", "options_read_id", "desired", "review_sha256")))
        read(owner, owner_url, "current", True)
        options(owner, "owner")
        confirmation = review(owner, owner_url, "php84")
        owner_request = confirmation["request_id"]; request_ids.append(owner_request)
        owner_change = dict(confirmation, current_password=OWNER_PASSWORD, confirmed="1")
        calls, posts = provider.calls, len(provider.posts)
        response = owner.post(owner_url, data=owner_change, timeout=120)
        row = request_row(owner_request)
        import json as _json
        (qa / "php-controls-owner-change-diagnostics.json").write_text(_json.dumps({
            "status": response.status_code, "calls_delta": provider.calls - calls,
            "posts_delta": len(provider.posts) - posts,
            "last_post": provider.posts[-1] if provider.posts else None,
            "row": row, "notice_excerpt": response.text[:1200]}, indent=2), encoding="utf-8")
        check("Owner form performs one exact setter and genuine ledger attempt", provider.calls == calls + 1 and len(provider.posts) == posts + 1
              and provider.posts[-1] == {"path": "/package/701/web/phpVersion", "body": '{"value":"php84"}'}
              and row["actor_kind"] == row["terminal_actor_kind"] == "staff" and row["actor_id"] == row["terminal_actor_id"] == 1
              and row["ledger_attempts"] == 1 and row["phase"] == "attempted")
        check("Acknowledgement does not establish reported PHP value", row["readback"] == "pending" and "Awaiting a check" in response.text and provider.php_value == "php82")
        before, calls = state(), provider.calls
        owner.post(owner_url, data=owner_change, timeout=120); owner.get(owner_url, timeout=30)
        no_effect(before, calls, "Captured owner replay and reload never resend")
        cool(); response, owner_verify = post_form(owner, owner_url, f'[data-php-request="{owner_request}"] form[data-php-verify]', {"current_password": OWNER_PASSWORD})
        check("Later different scalar remains fenced", request_row(owner_request)["readback"] == "different" and "Value differs" in response.text and soup(response).select_one("form[data-php-select]") is None)
        capture(owner, "PHP-Controls-Owner-History", owner_url, f'[data-php-request="{owner_request}"]')
        provider.php_value = "php84"; cool()
        post_form(owner, owner_url, f'[data-php-request="{owner_request}"] form[data-php-verify]', {"current_password": OWNER_PASSWORD})
        check("Independent matching scalar records observed disposition", request_row(owner_request)["readback"] == "observed")
        before, calls = state(), provider.calls; owner.post(owner_url, data=owner_verify, timeout=120)
        no_effect(before, calls, "Successful verification replay performs no new read")

        read(client, client_url, "current", False); options(client, "client")
        check("Client read has actual client actor", state()["latest"]["current"]["actor_kind"] == "client" and state()["latest"]["current"]["actor_id"] == 1)
        capture(client, "PHP-Controls-Client", client_url, "[data-php-controls]")
        client_confirmation = review(client, client_url, "php82")
        capture(client, "PHP-Controls-Client-Review", client_url, "[data-php-review]", "php82")
        client_request = client_confirmation["request_id"]; request_ids.append(client_request)
        client_change = dict(client_confirmation, current_password=CLIENT_PASSWORD, confirmed="1")
        posts = len(provider.posts); client.post(client_url, data=client_change, timeout=120)
        row = request_row(client_request)
        check("Delegated client setter retains actual client requester and recorder", len(provider.posts) == posts + 1 and row["actor_kind"] == row["terminal_actor_kind"] == "client"
              and row["actor_id"] == row["terminal_actor_id"] == 1 and row["ledger_attempts"] == 1 and row["readback"] == "pending"
              and provider.posts[-1] == {"path": "/package/701/web/phpVersion", "body": '{"value":"php82"}'})
        provider.php_value = "php82"; cool()
        _, client_verify = post_form(client, client_url, f'[data-php-request="{client_request}"] form[data-php-verify]', {"current_password": CLIENT_PASSWORD})
        check("Client later scalar is separately observed", request_row(client_request)["readback"] == "observed")
        capture(client, "PHP-Controls-Client-History", client_url, f'[data-php-request="{client_request}"]')

        fixture("inventory", {})
        read(client, client_url, "current", False); options(client, "client")
        fault_confirmation = review(client, client_url, "php84")
        fault_request = fault_confirmation["request_id"]; request_ids.append(fault_request)
        fault_change = dict(fault_confirmation, current_password=CLIENT_PASSWORD, confirmed="1")
        fixture("fault-arm", {}); posts = len(provider.posts); before_hits = state()["fault_hits"]
        try:
            response = client.post(client_url, data=fault_change, timeout=120)
            row = request_row(fault_request)
            check("Real post-send audit fault preserves original request and finished ledger", len(provider.posts) == posts + 1 and row["phase"] == "pending"
                  and row["ledger_status"] in ("failed", "uncertain") and row["ledger_attempts"] == 1 and row["terminal_actor_id"] is None and state()["fault_hits"] > before_hits
                  and provider.posts[-1] == {"path": "/package/701/web/phpVersion", "body": '{"value":"php84"}'})
            private_safe(response, "Terminal fault response discloses no private context or password")
        finally:
            fixture("fault-clear", {})
        before, calls = state(), provider.calls; client.post(client_url, data=fault_change, timeout=120)
        no_effect(before, calls, "Original unresolved client replay never resends")
        check("Client has no local recovery form", soup(client.get(client_url, timeout=30)).select_one("form[data-php-recover]") is None)
        capture(owner, "PHP-Controls-Owner-Recovery", owner_url, f'[data-php-request="{fault_request}"] form[data-php-recover]')
        before, calls = state(), provider.calls
        recovery_page = owner.get(owner_url, timeout=30); recovery = fields(form_for(recovery_page, f'[data-php-request="{fault_request}"] form[data-php-recover]'))
        forged = dict(recovery, csrf=csrf(client.get(client_url, timeout=30)), current_password=CLIENT_PASSWORD, confirmed="1")
        client.post(client_url, data=forged, timeout=120)
        no_effect(before, calls, "Client cannot forge owner recovery action")
        owner.post(owner_url, data=dict(recovery, ledger_sha256="f" * 64, current_password=OWNER_PASSWORD, confirmed="1"), timeout=120)
        no_effect(before, calls, "Wrong original ledger hash cannot recover")
        owner.post(owner_url, data=dict(recovery, current_password=OWNER_PASSWORD, confirmed="1"), timeout=120)
        row = request_row(fault_request)
        check("Owner recovery preserves client requester and actual staff recorder without send", provider.calls == calls and row["actor_kind"] == "client" and row["actor_id"] == 1
              and row["terminal_actor_kind"] == "staff" and row["terminal_actor_id"] == 1 and row["recovered_from_ledger"] and row["readback"] == "pending")
        provider.php_value = "php84"; cool()
        post_form(owner, owner_url, f'[data-php-request="{fault_request}"] form[data-php-verify]', {"current_password": OWNER_PASSWORD})
        check("Recovered terminal still requires actual separate scalar read", request_row(fault_request)["readback"] == "observed")

        read(client, client_url, "current", False); options(client, "client")
        stale_review = review(client, client_url, "php82")
        post_form(owner, owner_url, 'form[data-php-delegate="disable"]', {"current_password": OWNER_PASSWORD, "confirmed": "1"})
        before, calls = state(), provider.calls
        for label, data in (("reviewed approval", dict(stale_review, current_password=CLIENT_PASSWORD, confirmed="1")), ("captured replay", client_change), ("verification", client_verify)):
            response = client.post(client_url, data=data, timeout=120)
            no_effect(before, calls, "Revoked client cannot use " + label)
            check("Revoked " + label + " reveals no original history", not soup(response).select("[data-php-request]"))
        capture(client, "PHP-Controls-Client-Unavailable", client_url, "[data-php-controls]")
        private_safe(client.get(client_url, timeout=30), "Unavailable client screen remains private-safe")
        check("Exact bounded capture inventory", set(captures) == set(CAPTURES) and len(captures) == len(CAPTURES))
        check("Only three original single-send setter attempts occurred", len(provider.posts) == 3 and all(request_row(i)["ledger_attempts"] == 1 for i in request_ids))
        ledger_ids = [request_row(i)["ledger_id"] for i in request_ids]
        check("Each original request retains its distinct genuine ledger identity", len(set(ledger_ids)) == 3 and all(isinstance(i, str) and re.fullmatch(r"[a-f0-9]{32}", i) is not None for i in ledger_ids))
        return {"synthetic_options": True, "synthetic_provider_tls": True, "captures": captures,
                "request_count": len(request_ids), "post_count": len(provider.posts),
                "scope": "Real packaged HTTP forms with disposable SQL and loopback TLS; normalized choices explicitly synthetic. Screenshots require separate visual review."}
    finally:
        for session in sessions:
            session.close()
