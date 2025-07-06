document.addEventListener('DOMContentLoaded', function() {
  // Dil dropdown menüsündeki tüm dil seçeneklerini al
  const languageItems = document.querySelectorAll('[data-language-code]');

  // Her dil seçeneğine tıklama olayı ekle
  languageItems.forEach(item => {
    item.addEventListener('click', function(e) {
      e.preventDefault();

      // Seçilen dil kodunu al
      const languageCode = this.getAttribute('data-language-code');

      // CSRF token'ı al
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      // Dil değiştirme isteği gönder
      fetch(`/index-analyzer/set-locale/${languageCode}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      }).then(response => response.json()).then(data => {
        if (data.success) {
          // Başarılı olursa sayfayı yenile
          window.location.reload();
        } else {
          console.error('Dil değiştirme hatası:', data.message);
        }
      }).catch(error => {
        console.error('Dil değiştirme hatası:', error);
      });
    });
  });
});
