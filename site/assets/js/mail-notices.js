'use strict';
// Neutral public mounts contain no account, count or identity. Read local metadata with this session only.
document.querySelectorAll('[data-mail-notice-bell]').forEach((host) => {
  let pending = false, last = -Infinity, generation = 0, active;
  const clear = () => {
    host.replaceChildren(); host.hidden = true;
    host.closest('.admin-profile')?.classList.remove('has-mail-notices');
    host.closest('.nav-inner')?.classList.remove('has-mail-notices');
  };
  const refresh = async () => {
    if (new URL(location.href).searchParams.has('support')) { clear(); return; }
    if (document.hidden || !document.hasFocus() || pending || Date.now() - last < 60000) return;
    pending = true; last = Date.now();
    const controller = new AbortController(), current = generation, timer = setTimeout(() => controller.abort(), 8000);
    active = controller;
    try {
      const endpoint = new URL(host.dataset.mailNoticeEndpoint, location.href);
      if (endpoint.origin !== location.origin) throw new Error('Invalid endpoint');
      const response = await fetch(endpoint.href, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
      if ([204, 401, 403].includes(response.status)) { clear(); return; }
      if (!response.ok) throw new Error('Unavailable');
      const data = await response.json(), url = new URL(data.url, location.href);
      if (url.origin !== location.origin || url.username || url.password || !Number.isInteger(data.count) || data.count < 0 || data.count > 100) throw new Error('Invalid notices');
      if (current !== generation || document.hidden || !document.hasFocus() || new URL(location.href).searchParams.has('support')) { clear(); return; }
      const link = document.createElement('a'), count = data.count >= 100 ? '99+' : String(data.count);
      link.className = 'mail-notice-bell'; link.href = url.href; link.textContent = '🔔 Mail ' + count;
      link.setAttribute('aria-label', 'Email notices: ' + count + ' recent notices. Open your email notices.');
      link.title = 'Recent email notices · Check an inbox for new mail';
      host.replaceChildren(link); host.hidden = false;
      host.closest('.admin-profile')?.classList.add('has-mail-notices');
      host.closest('.nav-inner')?.classList.add('has-mail-notices');
    } catch (_) { clear(); }
    finally {
      clearTimeout(timer); pending = false; active = undefined;
      if (current !== generation && !document.hidden && document.hasFocus()) { last = -Infinity; refresh(); }
    }
  };
  const invalidate = () => { generation++; active?.abort(); clear(); };
  // Returning from the browser's back/forward cache must not restore an old private badge.
  window.addEventListener('pagehide', invalidate);
  window.addEventListener('blur', invalidate);
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) { invalidate(); last = -Infinity; }
    refresh();
  });
  window.addEventListener('focus', () => { invalidate(); last = -Infinity; refresh(); });
  document.addEventListener('visibilitychange', () => { invalidate(); if (!document.hidden) { last = -Infinity; refresh(); } });
  setInterval(refresh, 60000); refresh();
});
