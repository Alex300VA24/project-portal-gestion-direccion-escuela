<?php

namespace App\Modules\SeguimientoEgresado\Services\Invitaciones;

/** Resultado del envío del enlace de una invitación; la pantalla lo usa para decir la verdad sobre el correo. */
final class ResultadoEnvio
{
    public function __construct(
        public readonly EstadoEnvio $estado,
        public readonly ?string $destino = null,
        public readonly bool $redirigido = false,
    ) {}

    public function enviado(): bool
    {
        return $this->estado === EstadoEnvio::Enviado;
    }

    /** Texto para mostrar: "Enviado por correo a x@y" o "Enviado por correo a x@y (redirigido)". */
    public function texto(): string
    {
        $texto = $this->estado->etiqueta();
        if ($this->enviado() && $this->destino) {
            $texto .= ' a '.$this->destino.($this->redirigido ? ' (redirigido)' : '');
        }

        return $texto;
    }

    /** Arreglo simple para guardarlo en la sesión junto al enlace que se muestra una vez. */
    public function paraPantalla(): array
    {
        return ['correo' => $this->estado->value, 'correo_texto' => $this->texto(), 'correo_insignia' => $this->estado->insignia()];
    }
}
