document.addEventListener('DOMContentLoaded', () => {

  // Get all "navbar-burger" elements
  const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

  // Add a click event on each of them
  $navbarBurgers.forEach( el => {
    el.addEventListener('click', () => {

      // Get the target from the "data-target" attribute
      const target = el.dataset.target;
      const $target = document.getElementById(target);

      // Toggle the "is-active" class on both the "navbar-burger" and the "navbar-menu"
      el.classList.toggle('is-active');
      $target.classList.toggle('is-active');

    });
  });

});

document.addEventListener('input', function(event) {
    // Memeriksa apakah elemen yang memicu event adalah input yang kita targetkan
    if (event.target.id === 'input-jumlah' || event.target.id === 'input-total') {
        // Logika Kalkulasi Otomatis Harga Satuan
        const inputJumlah = document.getElementById('input-jumlah');
        const inputTotal = document.getElementById('input-total');
        const inputSatuan = document.getElementById('input-satuan');

        if (!inputJumlah || !inputTotal || !inputSatuan) return;

        const jumlah = parseFloat(inputJumlah.value) || 0;
        const total = parseFloat(inputTotal.value) || 0;

        // Melakukan kalkulasi hanya jika pengguna memasukkan angka valid
        if (jumlah > 0 && total > 0) {
            const hargaPerSatuan = total / jumlah;
            // Menghilangkan desimal berlebih jika angkanya bulat
            inputSatuan.value = Number.isInteger(hargaPerSatuan) ? hargaPerSatuan : hargaPerSatuan.toFixed(2);
        }
    }
});
