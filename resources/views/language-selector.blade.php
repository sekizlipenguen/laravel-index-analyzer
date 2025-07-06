<div class="language-selector dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa fa-globe"></i> {{ __('index-analyzer.language') }}: {{ config('language.locales.' . App::getLocale()) }}
    </button>
    <ul class="dropdown-menu" aria-labelledby="languageDropdown">
        @foreach(config('language.locales', ['en' => 'English', 'tr' => 'Türkçe']) as $code => $name)
            <li>
                <a class="dropdown-item {{ App::getLocale() == $code ? 'active' : '' }}" href="#" data-language-code="{{ $code }}">
                    {{ $name }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const languageLinks = document.querySelectorAll('[data-language-code]');

    languageLinks.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const languageCode = this.getAttribute('data-language-code');

        // AJAX isteği ile dil değiştirme
        fetch('/index-analyzer/change-language', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            locale: languageCode,
          }),
        }).then(response => response.json()).then(data => {
          if (data.success) {
            // Sayfayı yenile
            window.location.reload();
          }
        }).catch(error => console.error('Error:', error));
      });
    });
  });
</script>
