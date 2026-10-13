'use strict';
// The public page contains no identity, count or activity content. Fetch only with its own cookies.
document.querySelectorAll('[data-activity-bell]').forEach((host) => {
  let last = -Infinity, pending = false, link;
  const refresh = async () => {
    if (document.hidden || !document.hasFocus() || pending || Date.now() - last < 60000) return;
    last = Date.now(); pending = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
      const response = await fetch(host.dataset.activityEndpoint, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
      if ([204,401,403].includes(response.status)) {host.replaceChildren(); host.hidden = true; link = undefined; return;}
      if (!response.ok) throw new Error('Unavailable');
      const data = await response.json(), url = new URL(data.url, location.href);
      if (url.origin !== location.origin || !Number.isInteger(data.count) || data.count < 0 || data.count > 100) throw new Error('Invalid activity');
      if (!link) {link = document.createElement('a'); link.className = 'activity-bell'; host.replaceChildren(link);}
      link.href = url.href;
      const count = data.count >= 100 ? '99+' : String(data.count);
      link.textContent = '🔔 ' + count;
      link.setAttribute('aria-label', 'Workspace activity: ' + count + ' unread updates');
      link.title = 'Workspace activity · ' + count + ' unread'; host.hidden = false;
    } catch (_) {
      // An established bell can report staleness. A visitor gets no invented/private UI.
      if (link) {link.textContent = '🔔 ?'; link.setAttribute('aria-label', 'Workspace activity unavailable. Open activity to retry.'); link.title = 'Activity unavailable';}
    } finally {clearTimeout(timeout); pending = false;}
  };
  refresh(); setInterval(refresh, 60000);
  window.addEventListener('focus', refresh); document.addEventListener('visibilitychange', refresh);
});
