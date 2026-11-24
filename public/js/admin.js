(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = $('meta[name=csrf-token]')?.content;

  // Dropdown profil
  const pbtn = $('#profile-btn'), pmenu = $('#profile-menu');
  pbtn?.addEventListener('click', e => { e.stopPropagation(); pmenu.classList.toggle('open'); });
  document.addEventListener('click', () => pmenu?.classList.remove('open'));

  // Modal generik
  const openModal = el => el?.classList.add('open');
  const closeModal = el => el?.classList.remove('open');
  $$('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.overlay'))));
  $$('.overlay').forEach(o => o.addEventListener('mousedown', e => { if (e.target === o) closeModal(o); }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') $$('.overlay.open').forEach(closeModal); });

  // Submit otomatis saat filter berubah
  $$('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));

  // Modal check-out (data diambil dari server agar waktu akurat)
  const co = $('#checkout-modal');
  $$('.js-checkout').forEach(btn => btn.addEventListener('click', async () => {
    btn.disabled = true;
    try {
      const r = await fetch(btn.dataset.preview, { headers: { Accept: 'application/json' } });
      if (!r.ok) throw new Error(r.status);
      const d = await r.json();
      if (!d.can) { location.reload(); return; }
      $('#co-name').textContent = d.name; $('#co-host').textContent = d.host;
      $('#co-in').textContent = d.in; $('#co-out').textContent = d.out; $('#co-dur').textContent = d.duration;
      $('form', co).action = d.action;
      openModal(co);
    } catch { alert('Gagal memuat data check-out. Coba lagi.'); }
    btn.disabled = false;
  }));

  // Pilih semua + bar aksi massal
  const all = $('#select-all'), bulk = $('#bulk-bar');
  const rows = () => $$('.row-check');
  const sync = () => {
    const sel = rows().filter(c => c.checked);
    if (bulk) { bulk.classList.toggle('show', sel.length > 0); $('#bulk-count').textContent = sel.length; }
    if (all) all.checked = sel.length > 0 && sel.length === rows().length;
  };
  all?.addEventListener('change', () => { rows().forEach(c => c.checked = all.checked); sync(); });
  rows().forEach(c => c.addEventListener('change', sync));
  $$('[data-bulk]').forEach(b => b.addEventListener('click', () => {
    const f = $('#bulk-form'); $$('input[name="ids[]"]', f).forEach(i => i.remove());
    rows().filter(c => c.checked).forEach(c => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = c.value; f.appendChild(i); });
    $('input[name=action]', f).value = b.dataset.bulk; f.submit();
  }));

  // Preferensi notifikasi (simpan langsung)
  $$('[data-pref]').forEach(t => t.addEventListener('change', async () => {
    t.disabled = true;
    try {
      const r = await fetch(t.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ key: t.dataset.pref, value: t.checked }) });
      if (!r.ok) throw 0;
      if (t.dataset.pref === 'notify_sound' && t.checked) beep();
    } catch { t.checked = !t.checked; alert('Preferensi gagal disimpan.'); }
    t.disabled = false;
  }));

  // Suara & polling notifikasi
  let ctx;
  function beep() {
    try {
      ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
      g.gain.setValueAtTime(.15, ctx.currentTime); g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .5);
      o.start(); o.stop(ctx.currentTime + .5);
    } catch {}
  }
  const toast = $('#toast');
  function showToast(html) { if (!toast) return; toast.innerHTML = html; toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 7000); }
  const pollUrl = $('meta[name=poll-url]')?.content;
  let lastId = null;
  async function poll() {
    if (document.hidden) return; // tab tidak terlihat: jangan membebani server
    try {
      const r = await fetch(pollUrl, { headers: { Accept: 'application/json' } });
      if (!r.ok) return; // sesi habis / server error: lewati, jangan parse HTML sebagai JSON
      const d = await r.json();
      $('#bell')?.classList.toggle('has', d.unread > 0);
      if (lastId !== null && d.latest_id > lastId) {
        showToast('Tamu baru menunggu konfirmasi. <a href="' + $('#bell').href + '">Lihat</a>');
        if (d.sound) beep();
      }
      lastId = d.latest_id;
    } catch {}
  }
  if (pollUrl) {
    poll(); setInterval(poll, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  }
  const flash = $('#flash-toast'); if (flash) setTimeout(() => flash.remove(), 6000);

  // Halaman ekspor Excel
  const ef = $('#export-form');
  if (ef) {
    const fname = $('#filename'), cols = () => $$('input[name="cols[]"]', ef);
    $$('input[name=format]', ef).forEach(r => r.addEventListener('change', () => {
      const ext = r.value; fname.value = fname.value.replace(/\.(xlsx|csv)$/i, '') + '.' + ext;
      $$('.js-ext').forEach(e => e.textContent = ext === 'xlsx' ? 'Excel (.xlsx)' : 'CSV (.csv)');
      $('#tabs-box').style.display = ext === 'xlsx' ? '' : 'none';
    }));
    let timer, ctrl;
    const refresh = () => {
      const picked = cols().filter(c => c.checked);
      $('#col-count').textContent = picked.length + ' dari ' + cols().length + ' dipilih';
      $('#btn-export').disabled = picked.length === 0;
      clearTimeout(timer);
      timer = setTimeout(async () => {
        ctrl?.abort(); ctrl = new AbortController(); // batalkan permintaan lama agar hasil lama tidak menimpa yang baru
        const q = new URLSearchParams(ef.dataset.query); picked.forEach(c => q.append('cols[]', c.value));
        try {
          const r = await fetch(ef.dataset.preview + '?' + q, { signal: ctrl.signal });
          if (!r.ok) return;
          const html = await r.text();
          $('#preview-slot').innerHTML = html;
          const m = html.match(/data-cols="(\d+)"/); if (m) $$('.js-colsum').forEach(e => e.textContent = m[1] + ' kolom');
        } catch { /* dibatalkan atau jaringan putus: biarkan pratinjau terakhir */ }
      }, 150);
    };
    cols().forEach(c => c.addEventListener('change', refresh));
    ef.addEventListener('submit', e => { if (!cols().some(c => c.checked)) { e.preventDefault(); alert('Pilih minimal satu kolom.'); } });
    refresh();
  }
  // Unduhan otomatis setelah berhasil membuat berkas
  const dl = $('[data-autodownload]');
  if (dl) { const f = document.createElement('iframe'); f.style.display = 'none'; f.src = dl.dataset.autodownload; document.body.appendChild(f); }

  // Mode spreadsheet (baru / sudah ada)
  const mode = $$('input[name=mode]');
  if (mode.length) {
    const sync2 = () => { const ex = mode.find(m => m.checked).value === 'existing'; $('#new-box').style.display = ex ? 'none' : ''; $('#existing-box').style.display = ex ? '' : 'none'; };
    mode.forEach(m => m.addEventListener('change', sync2)); sync2();
  }
  $$('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
})();
