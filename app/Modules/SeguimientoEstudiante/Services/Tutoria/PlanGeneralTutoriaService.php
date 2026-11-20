<?php

namespace App\Modules\SeguimientoEstudiante\Services\Tutoria;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class PlanGeneralTutoriaService
{
    private const TABLE = 'seguimiento_estudiante.operacion';

    public function __construct(private readonly AsignacionTutoriaService $asignaciones) {}

    public function consultar(int $escuela, int $periodo): array
    {
        $datos = $this->registro($escuela, $periodo)->value('datos');
        $registro = $datos === null ? ['revision' => 0, 'documents' => []] : json_decode($datos, true, flags: JSON_THROW_ON_ERROR);
        $documentos = collect($registro['documents'])->sortByDesc('version')->values();
        $hoy = now('America/Lima')->toDateString();
        $iniciado = $documentos->first(fn (array $documento): bool => $documento['valid_from'] <= $hoy);
        $estado = match (true) {
            $documentos->isEmpty() => 'Sin publicación',
            $iniciado === null => $documentos->contains('status', 'PUBLICADO') ? 'Programado' : 'Retirado',
            $iniciado['status'] === 'RETIRADO' => 'Retirado',
            $iniciado['valid_until'] < $hoy => 'Vencido',
            $this->contenidoPdf($escuela, $periodo, $iniciado['sha256']) === null => 'PDF no disponible',
            default => 'Vigente',
        };

        return [
            'revision' => $registro['revision'], 'documentos' => $documentos,
            'estado' => $estado, 'vigente' => $estado === 'Vigente' ? $iniciado : null,
        ];
    }

    public function publicar(int $persona, int $cuenta, int $escuela, int $periodo, array $datos): void
    {
        $pdf = $datos['pdf']->getContent();
        if (! str_starts_with($pdf, '%PDF-')) {
            throw ValidationException::withMessages(['pdf' => 'Adjunta el PDF del plan general aprobado.']);
        }

        $huella = hash('sha256', $pdf);
        unset($datos['pdf']);
        $datos['version'] = (int) $datos['version'];
        $envio = $datos['request_id'];
        $firma = hash('sha256', json_encode([$cuenta, $datos, $huella], JSON_THROW_ON_ERROR));

        $this->modificar($persona, $escuela, $periodo, function (array &$registro) use ($cuenta, $escuela, $periodo, $datos, $pdf, $huella, $envio, $firma): void {
            $reintento = $registro['operations'][$envio] ?? null;
            if ($reintento !== null && $reintento !== $firma) {
                throw ValidationException::withMessages(['version' => 'Ese envío corresponde a otro documento. Recarga la página.']);
            }
            if ($reintento === null) {
                $this->verificarRevision($registro, $datos['version']);
            }

            $this->guardarPdf($escuela, $periodo, $huella, $pdf);
            if ($reintento !== null) {
                return;
            }

            $id = (string) Str::uuid();
            $registro['documents'][$id] = [
                'id' => $id,
                'version' => ++$registro['revision'],
                'status' => 'PUBLICADO',
                'title' => $datos['title'],
                'approval_reference' => $datos['approval_reference'],
                'approved_by' => $datos['approved_by'],
                'approved_on' => $datos['approved_on'],
                'valid_from' => $datos['valid_from'],
                'valid_until' => $datos['valid_until'],
                'registered_by' => $cuenta,
                'registered_at' => now()->toIso8601String(),
                'sha256' => $huella,
            ];
            $registro['operations'][$envio] = $firma;
        });
    }

    public function retirar(int $persona, int $cuenta, int $escuela, int $periodo, string $id, array $datos): void
    {
        $this->modificar($persona, $escuela, $periodo, function (array &$registro) use ($cuenta, $id, $datos): void {
            abort_unless(isset($registro['documents'][$id]), 404);
            $this->verificarRevision($registro, (int) $datos['version']);
            if ($registro['documents'][$id]['status'] === 'RETIRADO') {
                throw ValidationException::withMessages(['version' => 'Esta publicación ya fue retirada.']);
            }
            $registro['revision']++;
            $registro['documents'][$id]['status'] = 'RETIRADO';
            $registro['documents'][$id]['withdrawal'] = [
                'actor' => $cuenta, 'date' => now()->toIso8601String(), 'reason' => $datos['reason'],
            ];
        });
    }

    public function descargar(int $escuela, int $periodo, string $id): Response
    {
        $datos = $this->registro($escuela, $periodo)->value('datos');
        $documento = $datos === null ? null : (json_decode($datos, true, flags: JSON_THROW_ON_ERROR)['documents'][$id] ?? null);
        abort_if($documento === null, 404);
        $pdf = $this->contenidoPdf($escuela, $periodo, $documento['sha256']);
        abort_if($pdf === null, 503, 'El PDF registrado no está disponible.');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="F4-general-v'.$documento['version'].'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function modificar(int $persona, int $escuela, int $periodo, Closure $operacion): void
    {
        DB::transaction(function () use ($persona, $escuela, $periodo, $operacion): void {
            $ambito = $this->asignaciones->ambitos($persona)->where('ambito.id_escuela', $escuela)->where('ambito.id_periodo', $periodo)
                ->sharedLock()->first(['ambito.id_persona']);
            abort_if($ambito === null, 404);
            DB::table(self::TABLE)->insertOrIgnore([
                'id_escuela' => $escuela, 'clave' => "f4-general-tutoria|{$periodo}",
                'datos' => '{"revision":0,"documents":[],"operations":[]}',
            ]);
            $consulta = $this->registro($escuela, $periodo);
            $registro = json_decode((clone $consulta)->lockForUpdate()->value('datos'), true, flags: JSON_THROW_ON_ERROR);
            $revision = $registro['revision'];
            $operacion($registro);
            if ($registro['revision'] !== $revision) {
                $consulta->update(['datos' => json_encode($registro, JSON_THROW_ON_ERROR), 'actualizado_en' => now()]);
            }
        });
    }

    private function registro(int $escuela, int $periodo): Builder
    {
        return DB::table(self::TABLE)->where('id_escuela', $escuela)->where('clave', "f4-general-tutoria|{$periodo}");
    }

    private function verificarRevision(array $registro, int $observada): void
    {
        if ($registro['revision'] !== $observada) {
            throw ValidationException::withMessages(['version' => 'El registro cambió. Recarga la página antes de continuar.']);
        }
    }

    private function ruta(int $escuela, int $periodo, string $huella): string
    {
        return "seguimiento-estudiante/plan-general/{$escuela}/{$periodo}/{$huella}.pdf";
    }

    private function contenidoPdf(int $escuela, int $periodo, string $huella): ?string
    {
        $contenido = Storage::disk('local')->get($this->ruta($escuela, $periodo, $huella));

        return is_string($contenido) && hash_equals($huella, hash('sha256', $contenido)) ? $contenido : null;
    }

    private function guardarPdf(int $escuela, int $periodo, string $huella, string $pdf): void
    {
        if ($this->contenidoPdf($escuela, $periodo, $huella) !== null) {
            return;
        }
        $disco = Storage::disk('local');
        $ruta = $this->ruta($escuela, $periodo, $huella);
        $temporal = $ruta.'.'.Str::uuid().'.tmp';
        try {
            abort_unless($disco->put($temporal, $pdf, 'private') && $disco->move($temporal, $ruta), 503, 'No se pudo guardar el PDF.');
        } finally {
            $disco->delete($temporal);
        }
        abort_if($this->contenidoPdf($escuela, $periodo, $huella) === null, 503, 'El PDF registrado no está disponible.');
    }
}
