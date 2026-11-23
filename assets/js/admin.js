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
  /* prevent double submits */
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.dataset.sent) { e.preventDefault(); return; }
    f.dataset.sent = '1';
    setTimeout(() => { delete f.dataset.sent; }, 4000);
  });
})();
