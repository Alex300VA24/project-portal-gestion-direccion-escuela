<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Documentos oficiales: dictamen de aprobación del proyecto y acta de sustentación (sin nota).
 */
final class ActaService
{
    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly CatalogoService $catalogo,
        private readonly ArchivoService $archivos,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
        private readonly SeguimientoService $seguimiento,
    ) {}

    /** Debe llamarse dentro de una transacción. */
    public function generarDictamen(Proyecto $proyecto): Acta
    {
        return Acta::create([
            'tipo' => 'Dictamen',
            'id_proyecto' => $proyecto->id_proyecto,
            'numero' => $this->siguienteNumero('DIC'),
            'resultado' => 'Aprobado por unanimidad',
            'emitida_en' => Carbon::now(),
        ])->refresh();
    }

    /** Debe llamarse dentro de una transacción. */
    public function generarActaSustentacion(Sustentacion $sustentacion): Acta
    {
        $resultado = $sustentacion->resultado === 'Aprobado'
            ? ($sustentacion->mencion_honrosa ? 'Aprobado con mención honrosa' : ($sustentacion->modo_resultado === 'Unanimidad' ? 'Aprobado por unanimidad' : 'Aprobado por mayoría'))
            : 'Desaprobado';

        return Acta::create([
            'tipo' => 'Sustentacion',
            'id_proyecto' => $sustentacion->id_proyecto,
            'id_sustentacion' => $sustentacion->id_sustentacion,
            'numero' => $this->siguienteNumero('ACT'),
            'resultado' => $resultado,
            'condicion' => $sustentacion->resultado === 'Aprobado' ? 'Expedito' : 'Impedido',
            'emitida_en' => Carbon::now(),
        ])->refresh();
    }

    /** @return Collection<int, Acta> */
    public function listado(): Collection
    {
        return Acta::query()->with('proyecto:id_proyecto,uuid,codigo,titulo,estado')
            ->orderByRaw('CASE WHEN id_archivo_firmado IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('emitida_en')
            ->get(['id_acta', 'uuid', 'id_proyecto', 'id_sustentacion', 'tipo', 'numero', 'resultado', 'condicion', 'emitida_en', 'id_archivo_firmado', 'firmada_en', 'informado_director_en']);
    }

    /**
     * Datos para el documento imprimible.
     *
     * @return array<string, mixed>
     */
    public function documento(Acta $acta): array
    {
        $proyecto = $acta->proyecto;
        $ficha = $this->seguimiento->ficha($proyecto);
        $idsDocentes = array_filter([$proyecto->asesorActivo?->id_docente, ...array_column($ficha['jurado'], 'id_docente')]);
        $docentes = $this->identidad->docentes(array_values($idsDocentes));
        $sustentacion = $acta->id_sustentacion ? Sustentacion::query()->find($acta->id_sustentacion) : null;

        return [
            'acta' => $acta,
            ...$ficha,
            'docentes' => $docentes,
            'idAsesor' => $proyecto->asesorActivo?->id_docente,
            'sustentacion' => $sustentacion,
            'ambiente' => $sustentacion ? $this->catalogo->nombreAmbiente($sustentacion->id_ambiente) : null,
        ];
    }

    public function subirFirmada(Acta $acta, Identidad $secretaria, UploadedFile $pdf): Acta
    {
        return DB::transaction(function () use ($acta, $secretaria, $pdf) {
            $a = Acta::query()->whereKey($acta->id_acta)->lockForUpdate()->firstOrFail();

            if ($a->id_archivo_firmado !== null) {
                throw ValidationException::withMessages(['archivo' => 'Ya se registró el documento firmado.']);
            }

            $a->update([
                'id_archivo_firmado' => $this->archivos->guardar($pdf, $secretaria->idUsuario),
                'registrado_por' => $secretaria->idUsuario,
                'firmada_en' => Carbon::now(),
            ]);

            return $a;
        });
    }

    /**
     * Informa al Director (actividad 36). Si es un acta de sustentación aprobada, cierra el proceso: Tesis aprobada.
     */
    public function informarDirector(Acta $acta, Identidad $secretaria): Acta
    {
        return DB::transaction(function () use ($acta, $secretaria) {
            $a = Acta::query()->whereKey($acta->id_acta)->lockForUpdate()->firstOrFail();

            if ($a->id_archivo_firmado === null) {
                throw ValidationException::withMessages(['informar' => 'Primero sube el documento firmado.']);
            }
            if ($a->informado_director_en !== null) {
                throw ValidationException::withMessages(['informar' => 'Ya se informó al Director.']);
            }

            $a->update(['informado_director_en' => Carbon::now()]);
            $p = $this->historial->bloquear($a->proyecto);

            if ($a->tipo === 'Sustentacion' && $a->condicion === 'Expedito' && $p->estado === Estados::SUSTENTADO) {
                $this->historial->cambiarEstado($p, Estados::TESIS_APROBADA, $secretaria->idUsuario, "Acta N.° {$a->numero} firmada e informada al Director.");
            }

            $doc = $a->tipo === 'Dictamen' ? 'Dictamen de aprobación del proyecto' : 'Acta de sustentación';
            $this->notificaciones->notificar($this->identidad->usuariosConRol('director_escuela'), 'acta.informada',
                "{$doc} N.° {$a->numero} · {$p->codigo}", "Resultado: {$a->resultado}. Documento firmado disponible.", $p);

            return $a;
        });
    }

    private function siguienteNumero(string $prefijo): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['tesis.acta.'.$prefijo]);
        $base = $prefijo.'-'.Carbon::now()->year.'-';
        $ultimo = Acta::query()->where('numero', 'like', $base.'%')->pluck('numero')->map(fn ($n) => (int) substr($n, strlen($base)))->max() ?? 0;

        return $base.str_pad((string) ($ultimo + 1), 3, '0', STR_PAD_LEFT);
    }
}
