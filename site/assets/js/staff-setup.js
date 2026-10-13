'use strict';
// URL fragments stay out of HTTP access logs and referrers. Consume only by explicit POST.
const setupCode = new URLSearchParams(location.hash.slice(1)).get('token');
const setupInput = document.querySelector('[data-setup-token]');
if (setupInput && setupCode && /^[a-f0-9]{64}$/.test(setupCode)) {
    setupInput.value = setupCode;
    history.replaceState(null, '', location.pathname + location.search);
}
