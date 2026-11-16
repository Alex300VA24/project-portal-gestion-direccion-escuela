<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\ActividadCronograma;
use App\Modules\TesisProyectoTesis\Models\Colaborador;
use App\Modules\TesisProyectoTesis\Models\PartidaPresupuesto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ProyectoEstudiante;
use App\Modules\TesisProyectoTesis\Models\Recurso;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registro del proyecto de tesis (F1): actividades 1 y 2 del flujo.
 */
final class ProyectoService
{
    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly CatalogoService $catalogo,
        private readonly ArchivoService $archivos,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * Proyecto vigente de un estudiante: el activo si existe; si no, el último en el que participó.
     */
    public function proyectoDeEstudiante(int $idEstudiante): ?Proyecto
    {
        $ids = ProyectoEstudiante::query()
            ->where('id_estudiante', $idEstudiante)
            ->where('estado', 'Activo')
            ->pluck('id_proyecto');

        return Proyecto::query()
            ->whereIn('id_proyecto', $ids)
            ->orderByRaw('CASE WHEN estado IN (?) THEN 1 ELSE 0 END', [Estados::TESIS_APROBADA])
            ->orderByDesc('registrado_en')
            ->first();
    }

    public function tieneProyectoActivo(int $idEstudiante): bool
    {
        return ProyectoEstudiante::query()
            ->join('tesis.proyecto_tesis as p', 'p.id_proyecto', '=', 'tesis.proyecto_estudiante.id_proyecto')
            ->where('tesis.proyecto_estudiante.id_estudiante', $idEstudiante)
            ->where('tesis.proyecto_estudiante.estado', 'Activo')
            ->whereNotIn('p.estado', Estados::FINALES)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $datos  validados por RegistrarProyectoRequest
     */
    public function registrar(Identidad $autor, array $datos, UploadedFile $documento): Proyecto
    {
        if ($autor->idEstudiante === null) {
            throw ValidationException::withMessages(['coautor_codigo' => 'Tu cuenta no está registrada como estudiante.']);
        }

        $integrantes = [$autor->idEstudiante];
        $codigoCoautor = trim((string) ($datos['coautor_codigo'] ?? ''));

        if ($codigoCoautor !== '') {
            $coautor = $this->identidad->estudiantePorCodigo($codigoCoautor);

            if ($coautor === null) {
                throw ValidationException::withMessages(['coautor_codigo' => 'No existe un estudiante activo con ese código.']);
            }
            if ($coautor['id'] === $autor->idEstudiante) {
                throw ValidationException::withMessages(['coautor_codigo' => 'No puedes agregarte a ti mismo como coautor.']);
            }
            $integrantes[] = $coautor['id'];
        }

        if (count($integrantes) < 1 || count($integrantes) > Parametros::INTEGRANTES_MAXIMOS) {
            throw ValidationException::withMessages(['coautor_codigo' => 'El proyecto debe tener 1 o 2 integrantes.']);
        }

        $idLinea = $this->catalogo->idLineaPorCodigo((string) $datos['linea']);
        if ($idLinea === null) {
            throw ValidationException::withMessages(['linea' => 'La línea de investigación no está vigente.']);
        }

        $periodo = $this->catalogo->periodoActual();
        if ($periodo === null) {
            throw ValidationException::withMessages(['titulo' => 'No hay un periodo académico registrado. Comunícate con la Secretaría.']);
        }

        return DB::transaction(function () use ($autor, $datos, $documento, $integrantes, $idLinea, $periodo) {
            // Serializa registros concurrentes de los mismos estudiantes (doble envío) y la numeración.
            foreach ($integrantes as $id) {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['tesis.estudiante.'.$id]);
            }
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['tesis.codigo_proyecto']);

            foreach ($integrantes as $i => $id) {
                if ($this->tieneProyectoActivo($id)) {
                    throw ValidationException::withMessages([
                        $i === 0 ? 'titulo' : 'coautor_codigo' => $i === 0
                            ? 'Ya tienes un proyecto de tesis activo.'
                            : 'El coautor ya tiene un proyecto de tesis activo.',
                    ]);
                }
            }

            $idArchivo = $this->archivos->guardar($documento, $autor->idUsuario);

            $proyecto = Proyecto::create([
                'codigo' => $this->siguienteCodigo(),
                'titulo' => trim((string) $datos['titulo']),
                'resumen' => trim((string) $datos['resumen']),
                'id_linea' => $idLinea,
                'id_periodo_registro' => $periodo->id_periodo,
                'etapa' => Estados::ETAPA_PROYECTO,
                'estado' => Estados::REGISTRADO,
                'enfoque' => $datos['enfoque'],
                'tipo_investigacion' => $datos['tipo_investigacion'],
                'diseno' => $datos['diseno'],
                'lugar_ejecucion' => $datos['lugar_ejecucion'],
                'institucion' => $datos['institucion'],
                'duracion_meses' => (int) $datos['duracion_meses'],
                'financiamiento' => $datos['financiamiento'],
                'id_archivo_documento' => $idArchivo,
            ])->refresh();

            foreach ($integrantes as $i => $id) {
                ProyectoEstudiante::create([
                    'id_proyecto' => $proyecto->id_proyecto,
                    'id_estudiante' => $id,
                    'orden_autor' => $i + 1,
                    'estado' => 'Activo',
                    'fecha_incorporacion' => Carbon::today(),
                ]);
            }

            $this->guardarDetalleF1($proyecto, $datos);

            $this->historial->inicial($proyecto, $autor->idUsuario, 'Registro del proyecto (F1).');

            $this->notificaciones->notificar(
                $this->identidad->usuariosDeEstudiantes($integrantes),
                'proyecto.registrado',
                "Tu proyecto {$proyecto->codigo} fue registrado",
                'El Director solicitará la designación de tu asesor al Decanato.',
                $proyecto,
            );
            $this->notificaciones->notificar(
                $this->identidad->usuariosConRol('director_escuela'),
                'proyecto.registrado',
                "Nuevo proyecto {$proyecto->codigo}",
                'Requiere solicitar asesor al Decanato.',
                $proyecto,
            );

            return $proyecto;
        });
    }

    /** @param array<string, mixed> $datos */
    private function guardarDetalleF1(Proyecto $proyecto, array $datos): void
    {
        foreach (array_values($datos['colaboradores'] ?? []) as $c) {
            if (trim((string) ($c['nombres'] ?? '')) === '') {
                continue;
            }
            Colaborador::create([
                'id_proyecto' => $proyecto->id_proyecto,
                'nombres_completos' => trim($c['nombres']),
                'institucion' => $c['institucion'] ?? null,
                'rol_colaboracion' => trim((string) ($c['rol'] ?? '')) ?: 'Colaborador',
            ]);
        }

        foreach (array_values($datos['actividades'] ?? []) as $i => $a) {
            ActividadCronograma::create([
                'id_proyecto' => $proyecto->id_proyecto,
                'descripcion' => trim($a['descripcion']),
                'fecha_inicio' => $a['fecha_inicio'],
                'fecha_fin' => $a['fecha_fin'],
                'horas_semanales' => $a['horas'] ?? null,
                'orden' => $i + 1,
            ]);
        }

        foreach (array_values($datos['recursos'] ?? []) as $r) {
            Recurso::create([
                'id_proyecto' => $proyecto->id_proyecto,
                'tipo' => $r['tipo'],
                'descripcion' => trim($r['descripcion']),
                'cantidad' => $r['cantidad'] ?? null,
            ]);
        }

        foreach (array_values($datos['partidas'] ?? []) as $p) {
            PartidaPresupuesto::create([
                'id_proyecto' => $proyecto->id_proyecto,
                'codigo_clasificador' => $p['codigo'] ?? null,
                'descripcion' => trim($p['descripcion']),
                'cantidad' => 1,
                'unidad' => 'Global',
                'costo_unitario' => $p['monto'],
                'fuente_financiamiento' => $proyecto->financiamiento,
            ]);
        }
    }

    private function siguienteCodigo(): string
    {
        $prefijo = 'PT-'.Carbon::now()->year.'-';
        $ultimo = Proyecto::query()->where('codigo', 'like', $prefijo.'%')->pluck('codigo')
            ->map(fn ($c) => (int) substr($c, strlen($prefijo)))
            ->max() ?? 0;

        return $prefijo.str_pad((string) ($ultimo + 1), 3, '0', STR_PAD_LEFT);
    }
}
