@php use App\Helpers\SessionKey; @endphp
@if($googleTagId = config('google.tag_id'))
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleTagId }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', {{ Illuminate\Support\Js::from($googleTagId) }});
@if($method = session(SessionKey::sign_up_method->value))
  gtag('event', 'sign_up', {
    method: {{ Illuminate\Support\Js::from($method) }}
  });
@endif
</script>
@endif
