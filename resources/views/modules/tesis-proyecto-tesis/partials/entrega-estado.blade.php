@php($tzMapa = ['Pendiente' => ['Pendiente de revisión', 'neutral'], 'Observada' => ['Observada', 'warning'], 'SinObservaciones' => ['Sin observaciones', 'success'], 'Culminada' => ['Culminada', 'success']])
@php([$tzTexto, $tzVar] = $tzMapa[$estado] ?? [$estado, 'neutral'])
<span class="tz-status is-{{ $tzVar }}">{{ $tzTexto }}@if (($n ?? 0) > 0) · {{ $n }}@endif</span>
