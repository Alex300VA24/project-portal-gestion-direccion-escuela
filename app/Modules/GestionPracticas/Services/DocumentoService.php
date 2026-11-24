<?php

namespace App\Modules\GestionPracticas\Services;

use App\Modules\GestionPracticas\Models\DocumentoPractica;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Archivos de una práctica: se guardan en storage y se registran en comun.archivo. */
final class DocumentoService
{
    private const DISCO = 'local';

    /** Tipos que solo existen en una versión vigente: al subir uno nuevo el anterior queda «reemplazado». */
    private const UNICOS = ['carta_aceptacion', 'plan_practica', 'informe_final', 'constancia'];

    public function subir(Practica $practica, Actor $actor, UploadedFile $archivo, string $tipo): DocumentoPractica
    {
        $this->validarMomento($practica, $tipo);

        return DB::transaction(function () use ($practica, $actor, $archivo, $tipo): DocumentoPractica {
            $ruta = $archivo->store("gestion-practicas/practicas/{$practica->id_practica}", self::DISCO);

            $idArchivo = DB::table('comun.archivo')->insertGetId([
                'id_modulo' => $this->idModulo(),
                'nombre_original' => $archivo->getClientOriginalName(),
                'ruta_almacenamiento' => $ruta,
                'tipo_mime' => $archivo->getClientMimeType() ?: 'application/octet-stream',
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'subido_por' => $actor->requiereUsuario(),
            ], 'id_archivo');

            if (in_array($tipo, self::UNICOS, true)) {
                $practica->documentos()
                    ->where('tipo_documento', $tipo)
                    ->where('estado', '<>', DocumentoPractica::REEMPLAZADO)
                    ->update(['estado' => DocumentoPractica::REEMPLAZADO]);
            }

            return $practica->documentos()->create([
                'id_archivo' => $idArchivo,
                'tipo_documento' => $tipo,
                'estado' => DocumentoPractica::PENDIENTE,
            ])->refresh();
        });
    }

    /** Aprueba u observa un documento (administrador o supervisor asignado). */
    public function revisar(DocumentoPractica $documento, Actor $actor, string $estado): DocumentoPractica
    {
        if (! in_array($estado, [DocumentoPractica::APROBADO, DocumentoPractica::OBSERVADO], true)) {
            throw ValidationException::withMessages(['estado' => 'Estado de revisión no válido.']);
        }

        if ($documento->estado === DocumentoPractica::REEMPLAZADO) {
            throw ValidationException::withMessages(['estado' => 'El documento fue reemplazado por una versión más reciente.']);
        }

        $documento->update([
            'estado' => $estado,
            'revisado_por' => $actor->requiereUsuario(),
            'revisado_en' => now(),
        ]);

        return $documento;
    }

    /**
     * Ubicación del archivo de un documento; el controlador arma la respuesta HTTP.
     *
     * @return array{disco: string, ruta: string, nombre: string}|null null si el archivo ya no existe
     */
    public function ubicacion(DocumentoPractica $documento): ?array
    {
        $archivo = DB::table('comun.archivo')->where('id_archivo', $documento->id_archivo)->first();

        if ($archivo === null || ! Storage::disk(self::DISCO)->exists($archivo->ruta_almacenamiento)) {
            return null;
        }

        return ['disco' => self::DISCO, 'ruta' => $archivo->ruta_almacenamiento, 'nombre' => $archivo->nombre_original];
    }

    private function validarMomento(Practica $practica, string $tipo): void
    {
        $antesDeIniciar = [Practica::REGISTRADA, Practica::OBSERVADA];
        $informes = ['informe_parcial', 'informe_final'];

        $permitido = in_array($tipo, $informes, true)
            ? $practica->estado === Practica::EN_CURSO
            : in_array($practica->estado, [...$antesDeIniciar, Practica::EN_CURSO], true);

        if (! $permitido) {
            throw ValidationException::withMessages([
                'archivo' => in_array($tipo, $informes, true)
                    ? 'Los informes solo se suben mientras la práctica está en curso.'
                    : 'No se pueden adjuntar documentos en el estado actual de la práctica.',
            ]);
        }
    }

    private function idModulo(): int
    {
        $id = DB::table('seguridad.modulo_sistema')->where('codigo', 'gestion_practicas')->value('id_modulo');

        return (int) ($id ?? throw new \RuntimeException('El módulo gestion_practicas no está registrado en seguridad.modulo_sistema.'));
    }
}
