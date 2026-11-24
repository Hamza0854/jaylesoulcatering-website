/* CHEF JAY LeSOUL — admin panel */
(function () {
  'use strict';
  /* confirm dangerous buttons */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-confirm]');
    if (b && !confirm(b.dataset.confirm)) e.preventDefault();
  });
  /* 2FA setup QR code (drawn in the browser, the secret never leaves this page) */
  const qr = document.getElementById('qr');
  if (qr && window.QRCode) new QRCode(qr, { text: qr.dataset.uri, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
  /* eye button on every password field: show / hide the password */
  const eye = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeOff = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A11 11 0 0 1 12 5c7 0 11 7 11 7a18 18 0 0 1-3.2 3.9M6.6 6.6A18 18 0 0 0 1 12s4 7 11 7a10 10 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  document.querySelectorAll('input[type=password]').forEach(input => {
    const wrap = document.createElement('span');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'pw-eye'; btn.innerHTML = eye; btn.setAttribute('aria-label', 'Show password');
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? eyeOff : eye;
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    wrap.appendChild(btn);
  });

  /* prevent double submits */
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.dataset.sent) { e.preventDefault(); return; }
    f.dataset.sent = '1';
    setTimeout(() => { delete f.dataset.sent; }, 4000);
  });
})();
