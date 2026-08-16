@php use App\Helpers\SessionKey; @endphp
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-H866L7TH8R"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-H866L7TH8R');
@if($method = session(SessionKey::sign_up_method->value))
  gtag('event', 'sign_up', {
    method: {{ Illuminate\Support\Js::from($method) }}
  });
@endif
</script>
