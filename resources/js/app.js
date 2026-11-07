// Tema: disimpan di localStorage bila tersedia, kalau tidak mengikuti sistem.
document.addEventListener('click', (e) => {
    const themeBtn = e.target.closest('[data-theme-toggle]');
    if (themeBtn) {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (_) { /* abaikan */ }
        return;
    }

    const navBtn = e.target.closest('[data-nav-toggle]');
    if (navBtn) {
        const open = document.body.toggleAttribute('data-nav-open');
        navBtn.setAttribute('aria-expanded', String(open));
        return;
    }

    const fill = e.target.closest('[data-fill]');
    if (fill) {
        const form = document.querySelector('#login-form');
        form.querySelector('[name=username]').value = fill.dataset.fill;
        form.querySelector('[name=password]').value = fill.dataset.password || '';
        form.querySelector('[name=password]').focus();
    }
});

// Nama berkas yang dipilih pada area unggah.
document.querySelectorAll('[data-file-input]').forEach((input) => {
    const out = document.querySelector(input.dataset.fileInput);
    const zone = input.closest('[data-dropzone]');
    input.addEventListener('change', () => {
        if (out) out.textContent = input.files[0] ? input.files[0].name : 'Belum ada berkas dipilih';
    });
    if (zone) {
        ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.dataset.over = ''; }));
        ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, () => { delete zone.dataset.over; }));
    }
});

// Pilihan DPA hanya relevan untuk akun mahasiswa.
const roleSelect = document.querySelector('[data-role-select]');
const dpaField = document.querySelector('[data-dpa-field]');
if (roleSelect && dpaField) {
    const sync = () => { dpaField.hidden = roleSelect.value !== 'mahasiswa'; };
    roleSelect.addEventListener('change', sync);
    sync();
}

// Konfirmasi untuk tindakan yang tidak bisa dibatalkan.
document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
});

// Menu samping pada layar kecil menutup saat area lain disentuh.
document.addEventListener('click', (e) => {
    if (document.body.hasAttribute('data-nav-open') && !e.target.closest('#sidebar, [data-nav-toggle]')) {
        document.body.removeAttribute('data-nav-open');
    }
});
