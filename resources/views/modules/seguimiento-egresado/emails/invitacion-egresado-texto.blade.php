Hola, {{ $nombre }}:

@if ($proposito === 'CONTACTO_INICIAL')
La Escuela necesita tus datos de contacto para mantener el vínculo contigo. Completarlos toma un par de minutos.
@else
La Escuela te invita a responder la encuesta de seguimiento a egresados{{ $campania ? ' ('.$campania.')' : '' }}. Tus respuestas ayudan a mejorar la formación profesional.
@endif

{{ $proposito === 'CONTACTO_INICIAL' ? 'Completa tus datos aquí' : 'Responde la encuesta aquí' }}:
{{ $url }}

@if ($venceEn)Este enlace es personal y vence el {{ $venceEn->format('d/m/Y') }}.@else Este enlace es personal.@endif No lo compartas: quien lo tenga puede entrar a tu expediente.

Seguimiento al Egresado · Escuela de Ingeniería Informática · Universidad Nacional de Trujillo
