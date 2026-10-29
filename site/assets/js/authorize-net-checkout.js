/* Accept.js on-site card entry: card fields have no form names and never go to PHP. */
(function () {
  'use strict';
  document.querySelectorAll('form[data-authorize-net]').forEach(function (form) {
    var pending = false;
    form.addEventListener('submit', function (event) {
      if (form.dataset.tokenized === '1') return;
      event.preventDefault();
      if (pending || !form.reportValidity()) return;
      var fail = form.querySelector('[data-payment-error]');
      var submit = form.querySelector('button[type="submit"]');
      fail.textContent = '';
      if (!window.Accept || typeof window.Accept.dispatchData !== 'function') {
        fail.textContent = 'Secure card processing is not available. Please try again later.';
        return;
      }
      pending = true;
      submit.disabled = true;
      var month = form.querySelector('[data-card-month]').value;
      var year = form.querySelector('[data-card-year]').value;
      var request = {
        authData: {
          apiLoginID: form.dataset.login,
          clientKey: form.dataset.clientKey
        },
        cardData: {
          cardNumber: form.querySelector('[data-card-number]').value.replace(/\s/g, ''),
          month: month,
          year: year,
          cardCode: form.querySelector('[data-card-code]').value
        }
      };
      window.Accept.dispatchData(request, function (response) {
        pending = false;
        if (response.messages && response.messages.resultCode === 'Error') {
          fail.textContent = (response.messages.message || []).map(function (m) {
            return m.text;
          }).join(' ') || 'Payment details were not accepted.';
          submit.disabled = false;
          return;
        }
        if (!response.opaqueData || !response.opaqueData.dataValue) {
          fail.textContent = 'Payment token was not returned.';
          submit.disabled = false;
          return;
        }
        form.querySelector('[name="descriptor"]').value = response.opaqueData.dataDescriptor;
        form.querySelector('[name="payment_token"]').value = response.opaqueData.dataValue;
        form.querySelectorAll('[data-card-number],[data-card-code]').forEach(function (input) {
          input.value = '';
          input.disabled = true;
        });
        form.dataset.tokenized = '1';
        form.submit();
      });
    });
  });
}());
