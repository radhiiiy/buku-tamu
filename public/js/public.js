(() => {
  const form = document.getElementById('visit-form');
  const bar = document.getElementById('progress-bar');
  if (!form) return;
  const required = [...form.querySelectorAll('[data-req]')];
  const update = () => {
    const done = required.filter(el => el.type === 'checkbox' ? el.checked : el.value.trim() !== '').length;
    bar.style.width = Math.max(8, Math.round(done / required.length * 100)) + '%';
  };
  form.addEventListener('input', update); form.addEventListener('change', update); update();
  // Tombol kembali (bfcache) tidak boleh meninggalkan tombol kirim dalam keadaan nonaktif.
  window.addEventListener('pageshow', e => { if (e.persisted) { const b = form.querySelector('button[type=submit]'); b.disabled = false; b.style.opacity = ''; } });
  form.addEventListener('submit', () => { const b = form.querySelector('button[type=submit]'); b.disabled = true; b.style.opacity = .7; });
})();
