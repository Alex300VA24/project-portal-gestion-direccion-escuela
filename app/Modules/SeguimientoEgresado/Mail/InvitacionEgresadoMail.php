<?php

namespace App\Modules\SeguimientoEgresado\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo con el enlace personal para completar el contacto o responder la encuesta de seguimiento. */
class InvitacionEgresadoMail extends Mailable
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $url,
        public readonly string $proposito,
        public readonly ?string $campania,
        public readonly ?CarbonInterface $venceEn,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->proposito === 'CONTACTO_INICIAL'
            ? 'Completa tus datos de contacto · Escuela de Ingeniería Informática'
            : 'Encuesta de seguimiento a egresados · Escuela de Ingeniería Informática');
    }

    public function content(): Content
    {
        // Con versión de solo texto además de la HTML: los filtros de spam desconfían de los correos solo-HTML.
        return new Content(view: 'modules.seguimiento-egresado.emails.invitacion-egresado', text: 'modules.seguimiento-egresado.emails.invitacion-egresado-texto');
    }
}
