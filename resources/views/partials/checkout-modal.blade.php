<div id="checkout-modal" class="overlay" role="dialog" aria-modal="true" aria-labelledby="co-title">
    <div class="modal">
        <button class="x" type="button" data-close aria-label="Tutup"><x-icon name="x" /></button>
        <div class="badge ic-blue"><x-icon name="arrow-up-right" :size="22" /></div>
        <h2 id="co-title">Selesaikan kunjungan?</h2>
        <p class="lead">Pastikan tamu sudah meninggalkan area kantor.<br>Waktu check-out akan dicatat otomatis.</p>
        <div class="box">
            <div class="sum-row"><span>Nama tamu</span><b id="co-name">–</b></div>
            <div class="sum-row"><span>Penanggung jawab</span><b id="co-host">–</b></div>
            <div class="sum-row"><span>Waktu masuk</span><b id="co-in">–</b></div>
            <div class="sum-row"><span>Waktu keluar</span><b id="co-out">–</b></div>
            <div class="sum-row"><span>Durasi kunjungan</span><b id="co-dur">–</b></div>
        </div>
        <p class="muted small">Status akan berubah dari Berkunjung menjadi Selesai.</p>
        <form method="POST" action="#" class="foot">@csrf
            <button class="btn" type="button" data-close>Batal</button>
            <button class="btn btn-primary" type="submit">Konfirmasi check-out</button>
        </form>
    </div>
</div>
