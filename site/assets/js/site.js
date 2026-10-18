/* Progressive enhancement only. PHP remains responsible for pages, content and forms. */
'use strict';
(() => {
    document.documentElement.classList.remove('no-js');
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let userPaused = false;
    try { userPaused = localStorage.getItem('cwp-motion-paused') === '1'; } catch (_) { /* Storage is optional. */ }
    const paused = () => motionQuery.matches || userPaused;
    const motionButton = document.querySelector('.motion-toggle');
    function applyMotion() {
        document.body.classList.toggle('motion-off', paused());
        if (motionButton) {
            motionButton.hidden = false;
            motionButton.textContent = paused() ? 'Motion off' : 'Motion on';
            motionButton.setAttribute('aria-pressed', String(paused()));
            motionButton.setAttribute('aria-label', motionQuery.matches
                ? 'Reduced motion follows your device preference'
                : (paused() ? 'Enable decorative motion' : 'Pause decorative motion'));
            motionButton.disabled = motionQuery.matches;
        }
    }
    motionButton?.addEventListener('click', () => {
        userPaused = !userPaused;
        try { localStorage.setItem('cwp-motion-paused', userPaused ? '1' : '0'); } catch (_) { /* No persistent storage required. */ }
        applyMotion();
    });
    motionQuery.addEventListener?.('change', applyMotion);
    applyMotion();

    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('#site-nav');
    if (toggle && nav) {
        const close = () => {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation');
        };
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && nav.classList.contains('open')) { close(); toggle.focus(); }
        });
        nav.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
        document.addEventListener('click', e => {
            if (!nav.contains(e.target) && !toggle.contains(e.target)) close();
        });
        matchMedia('(min-width: 1201px)').addEventListener?.('change', e => { if (e.matches) close(); });
    }

    const reading = document.querySelector('.reading-progress i');
    const topButton = document.querySelector('.back-top');
    let scrollPending = false;
    function paintScroll() {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (reading) reading.style.width = (max > 0 ? window.scrollY / max * 100 : 0) + '%';
        topButton?.classList.toggle('visible', window.scrollY > 650);
        document.querySelector('.site-header')?.classList.toggle('is-scrolled', window.scrollY > 35);
        scrollPending = false;
    }
    window.addEventListener('scroll', () => {
        if (!scrollPending) { scrollPending = true; requestAnimationFrame(paintScroll); }
    }, {passive:true});
    paintScroll();
    topButton?.addEventListener('click', () => window.scrollTo({top:0, behavior:paused() ? 'auto' : 'smooth'}));

    if (!paused() && 'IntersectionObserver' in window) {
        const items = document.querySelectorAll('.service-card,.project-card,.promise-card,.timeline-item,.content-card');
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { entry.target.classList.add('shown'); observer.unobserve(entry.target); }
            });
        }, {threshold:0.02, rootMargin:'0px 0px 55px 0px'});
        items.forEach(el => { el.classList.add('reveal'); observer.observe(el); });
        document.documentElement.classList.add('js-motion');
    }
    if (matchMedia('(hover: hover) and (pointer: fine)').matches) {
        document.querySelectorAll('.glow-card').forEach(card => {
            let requested = false, x = 0, y = 0;
            card.addEventListener('pointermove', e => {
                if (paused() || document.hidden) return;
                const rect = card.getBoundingClientRect(); x = e.clientX - rect.left; y = e.clientY - rect.top;
                if (requested) return;
                requested = true;
                requestAnimationFrame(() => {
                    card.style.setProperty('--mx', x + 'px'); card.style.setProperty('--my', y + 'px'); requested = false;
                });
            });
        });
        const hero = document.querySelector('.hero');
        const scene = document.querySelector('[data-parallax]');
        if (hero && scene) {
            hero.addEventListener('pointermove', e => {
                if (paused() || document.hidden) return;
                const rect = hero.getBoundingClientRect();
                scene.style.setProperty('--scene-x', ((e.clientX - rect.left) / rect.width - .5) * 8 + 'px');
                scene.style.setProperty('--scene-y', ((e.clientY - rect.top) / rect.height - .5) * 6 + 'px');
            });
            hero.addEventListener('pointerleave', () => { scene.style.setProperty('--scene-x', '0px'); scene.style.setProperty('--scene-y', '0px'); });
        }
    }

    const portfolioLinks = document.querySelectorAll('.filters a[data-filter]');
    const portfolioCards = document.querySelectorAll('.portfolio-grid .project-card');
    const applyPortfolioFilter = category => {
        portfolioLinks.forEach(link => {
            const selected = link.dataset.filter === category;
            link.classList.toggle('active', selected);
            if (selected) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
        });
        let matched = 0;
        portfolioCards.forEach(card => {
            card.hidden = category !== '' && card.dataset.category !== category;
            if (!card.hidden) { card.classList.add('shown'); matched++; }
        });
        const empty = document.querySelector('[data-portfolio-empty]');
        if (empty) empty.hidden = matched > 0;
        const status = document.querySelector('[data-portfolio-status]');
        if (status) status.textContent = `Showing ${matched} of ${portfolioCards.length} projects.`;
    };
    portfolioLinks.forEach(link => link.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
        event.preventDefault();
        history.pushState(null, '', link.getAttribute('href'));
        applyPortfolioFilter(link.dataset.filter);
    }));
    window.addEventListener('popstate', () => applyPortfolioFilter((new URLSearchParams(location.search).get('cat') || '').trim()));
    const serviceSlug = new URLSearchParams(location.search).get('service');
    if (serviceSlug && /^[a-z0-9-]+$/.test(serviceSlug)) {
        document.getElementById(serviceSlug)?.scrollIntoView({behavior:paused() ? 'auto' : 'smooth', block:'start'});
    }

    document.querySelectorAll('[data-inquiry]').forEach(form => {
        let busy = false;
        form.addEventListener('submit', async e => {
            if (!window.fetch) return; // Native PHP POST remains available without JavaScript.
            e.preventDefault();
            if (busy || !form.reportValidity()) return;
            busy = true;
            const button = form.querySelector('button[type="submit"]');
            const result = form.querySelector('.form-result');
            const original = button.innerHTML;
            button.disabled = true; button.textContent = 'Sending your inquiry…'; result.hidden = true;
            try {
                const response = await fetch(form.getAttribute('action') || location.href, {method:'POST', body:new FormData(form), credentials:'same-origin', headers:{'Accept':'application/json'}});
                let body;
                try { body = await response.json(); }
                catch (_) { throw new Error('The server returned an unexpected response. Your details remain in this form. Please try again.'); }
                if (!response.ok || !body.ok) throw new Error(body.message || 'Your inquiry could not be saved. Please try again.');
                result.classList.remove('error'); result.textContent = body.message; result.hidden = false; result.focus();
                button.textContent = 'Inquiry received ✓'; button.disabled = true;
                form.querySelectorAll('input,textarea,select').forEach(input => { input.disabled = true; });
            } catch (error) {
                result.classList.add('error'); result.textContent = error instanceof Error ? error.message : 'Please try again.';
                result.hidden = false; result.focus(); button.disabled = false; button.innerHTML = original; busy = false;
            }
        });
    });
    document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', e => {
        if (!confirm(form.dataset.confirm)) e.preventDefault();
    }));
})();
