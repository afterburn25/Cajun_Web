'use strict';
(() => {
    const panel = document.getElementById('sandbox-trial');
    if (!panel) return;
    const form = document.getElementById('sandbox-token-form');
    const button = document.getElementById('sandbox-send');
    const feedback = document.getElementById('sandbox-feedback');
    const fields = ['card', 'month', 'year', 'code'].map(k => document.getElementById('sandbox-' + k));
    let sending = false, tokenReady = false;
    form.addEventListener('submit', event => { if (!tokenReady) event.preventDefault(); });
    button.addEventListener('click', () => {
        if (sending || !form.reportValidity()) return;
        if (!form.elements.confirm.checked) { feedback.textContent = 'Confirm the sandbox trial first.'; return; }
        if (!window.Accept) { feedback.textContent = 'Sandbox tokenization could not load. No transaction was submitted.'; return; }
        sending = true; button.disabled = true; feedback.textContent = 'Requesting a sandbox payment token…';
        const cardData = {cardNumber: fields[0].value.replace(/\s/g, ''), month: fields[1].value, year: fields[2].value, cardCode: fields[3].value};
        fields.forEach(field => { field.value = ''; });
        try {
            window.Accept.dispatchData({authData: {apiLoginID: panel.dataset.login, clientKey: panel.dataset.clientKey}, cardData}, response => {
                if (tokenReady) return; // A duplicate callback cannot submit twice.
                const opaque = response && response.opaqueData;
                if (!response || !response.messages || response.messages.resultCode !== 'Ok' || !opaque || opaque.dataDescriptor !== 'COMMON.ACCEPT.INAPP.PAYMENT' || typeof opaque.dataValue !== 'string') {
                    feedback.textContent = 'Tokenization failed. No transaction was submitted. Re-enter test card details to try again.';
                    sending = false; button.disabled = false; return;
                }
                form.elements.dataValue.value = opaque.dataValue;
                tokenReady = true; feedback.textContent = 'Submitting the sandbox trial. Do not reload or submit again.';
                form.requestSubmit();
            });
        } catch (_) {
            feedback.textContent = 'Tokenization stopped. No transaction was submitted. Reload before trying again.';
        }
    });
})();
