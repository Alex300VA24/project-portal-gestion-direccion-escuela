<!doctype html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f5f5f0;font-family:Arial,Helvetica,sans-serif;color:#15233d;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dde1da;border-radius:8px;overflow:hidden;">
    <div style="background:#172554;color:#ffffff;padding:16px 24px;">
      <strong style="font-size:16px;">Seguimiento al Egresado</strong><br>
      <span style="font-size:12px;color:#c7d2e6;">Escuela de Ingeniería Informática · Universidad Nacional de Trujillo</span>
    </div>
    <div style="padding:24px;line-height:1.55;font-size:15px;">
      <p style="margin-top:0;">Hola, {{ $nombre }}:</p>

      @if ($proposito === 'CONTACTO_INICIAL')
        <p>La Escuela necesita tus datos de contacto para mantener el vínculo contigo. Completarlos toma un par de minutos.</p>
        <p style="background:#fff3d8;border-left:4px solid #a65d00;padding:12px 14px;margin:16px 0;font-size:14px;">
          <strong>Este paso es obligatorio.</strong> Si no completas tu contacto, no podrás egresar.@if ($venceEn) Tienes hasta el {{ $venceEn->format('d/m/Y') }} para hacerlo.@endif
        </p>
      @else
        <p>La Escuela te invita a responder la encuesta de seguimiento a egresados{{ $campania ? ' ('.$campania.')' : '' }}. Tus respuestas ayudan a mejorar la formación profesional.</p>
      @endif

      <p style="text-align:center;margin:28px 0;">
        <a href="{{ $url }}" style="background:#087f5b;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;display:inline-block;">
          {{ $proposito === 'CONTACTO_INICIAL' ? 'Completar mis datos' : 'Responder la encuesta' }}
        </a>
      </p>

      <p style="font-size:13px;color:#536078;">
        Si el botón no funciona, copia este enlace en tu navegador:<br>
        <span style="word-break:break-all;">{{ $url }}</span>
      </p>

      <p style="font-size:13px;color:#536078;">
        @if ($venceEn)Este enlace es personal y vence el {{ $venceEn->format('d/m/Y') }}. @else Este enlace es personal. @endif
        No lo compartas: quien lo tenga puede entrar a tu expediente.
      </p>
    </div>
  </div>
</body>
</html>
