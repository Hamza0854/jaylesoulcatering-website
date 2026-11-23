/* CHEF JAY LeSOUL — account & review forms */
(function () {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* show / hide password */
  $$('.pw-toggle').forEach(btn => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.pw);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));

  /* character counter */
  $$('textarea[data-count]').forEach(t => {
    const out = t.parentElement.querySelector('.counter');
    const max = +t.dataset.count;
    const upd = () => { if (out) out.textContent = t.value.length + ' / ' + max; };
    t.addEventListener('input', upd); upd();
  });

  /* review form: check file sizes in the browser (no wasted uploads) + previews */
  const form = $('form[data-max-photos]');
  if (form) {
    const maxPhotos = +form.dataset.maxPhotos, photoMb = +form.dataset.photoMb, videoMb = +form.dataset.videoMb;
    const preview = $('.up-preview', form);
    const urls = [];
    const render = () => {
      urls.forEach(u => URL.revokeObjectURL(u)); urls.length = 0;
      preview.innerHTML = '';
      $$('input[type=file]', form).forEach(inp => [...inp.files].forEach(f => {
        const url = URL.createObjectURL(f); urls.push(url);
        const fig = document.createElement('figure');
        const el = document.createElement(f.type.startsWith('video') ? 'video' : 'img');
        el.src = url; if (el.tagName === 'VIDEO') { el.muted = true; el.playsInline = true; el.preload = 'metadata'; }
        const cap = document.createElement('figcaption');
        cap.textContent = (f.size / 1048576).toFixed(1) + ' MB';
        fig.append(el, cap); preview.appendChild(fig);
      }));
    };
    $$('input[type=file]', form).forEach(inp => inp.addEventListener('change', () => {
      const files = [...inp.files], isVideo = inp.dataset.kind === 'video';
      const limit = (isVideo ? videoMb : photoMb) * 1048576;
      let msg = '';
      if (!isVideo && files.length > maxPhotos) msg = 'Please choose up to ' + maxPhotos + ' photos.';
      else if (files.some(f => f.size > limit)) msg = (isVideo ? 'The video' : 'Each photo') + ' must be smaller than ' + (isVideo ? videoMb : photoMb) + ' MB. ' + (isVideo ? 'Try a shorter clip (30–60 seconds).' : '');
      inp.closest('.up-box').classList.toggle('error', !!msg);
      if (msg) { alert(msg); inp.value = ''; }
      render();
    }));
    form.addEventListener('submit', e => {
      const btn = $('.btn-send', form);
      if (btn.disabled) { e.preventDefault(); return; }
      btn.disabled = true; btn.textContent = 'Uploading… please wait';
    });
  }
})();
