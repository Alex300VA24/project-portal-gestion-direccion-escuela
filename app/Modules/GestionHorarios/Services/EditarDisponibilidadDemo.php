<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Escrituras exclusivamente en el catálogo local QA; no incluir en el PR. */
final class EditarDisponibilidadDemo
{
    public const DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
    public const ESTADOS = ['PENDIENTE', 'PRESENTADA', 'NO_PRESENTADA'];
    public const TRATAMIENTOS = ['ESTRICTA', 'FLEXIBLE'];

    private function contexto(User $usuario, int $periodo, int $docente): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        abort_unless(DB::table('core.periodo_academico')->where('id_periodo', $periodo)->where('codigo', '2026-II')->exists(), 404);
        abort_unless(DB::table('core.docente as d')->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
            ->where('d.id_docente', $docente)->where('d.codigo_docente', 'like', 'QA-%')->where('p.tipo_documento', 'QA')
            ->whereRaw('upper(d.estado) = ?', ['ACTIVO'])->exists(), 404);
    }

    private function datos(int $periodo, int $docente): array
    {
        $declaraciones = DB::table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->where('id_docente', $docente)->orderBy('id_declaracion')->get();
        $registros = DB::table('gestion_horarios.disponibilidad_docente')->where('id_periodo', $periodo)->where('id_docente', $docente)->orderBy('id_disponibilidad')->get();
        $bloques = DB::table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('id_bloque')->get();
        $d = $declaraciones->first();
        if ($declaraciones->count() > 1 || ($d && $d->id_registrado_por !== null)
            || $registros->contains(fn ($r) => ! $d || (int) $r->id_declaracion !== (int) $d->id_declaracion)) {
            $this->rechazar('La declaración tiene vínculos o autoría que requieren revisión institucional.');
        }
        $franjas = $registros->map(fn ($r) => $r->id_bloque.':'.$r->dia_semana)->all();
        return ['version' => hash('sha256', json_encode([$periodo, $docente, $declaraciones, $registros, $bloques], JSON_THROW_ON_ERROR)),
            'estado' => $d?->estado ?? 'PENDIENTE', 'tratamiento' => $d?->tratamiento_regular ?? 'ESTRICTA',
            'franjas' => $franjas, 'declaracion' => $d, 'bloques' => $bloques];
    }

    public function leer(User $usuario, int $periodo, int $docente): array
    {
        $this->contexto($usuario, $periodo, $docente);
        return DB::transaction(function () use ($usuario, $periodo, $docente) {
            DB::statement('LOCK TABLE gestion_horarios.bloque_horario, gestion_horarios.declaracion_disponibilidad, gestion_horarios.disponibilidad_docente IN SHARE MODE');
            $this->contexto($usuario, $periodo, $docente);
            return $this->datos($periodo, $docente);
        });
    }

    /** Valida las claves de la cuadrícula contra el calendario real, sin inventar bloques. */
    public static function normalizar(string $estado, string $tratamiento, array $franjas, array $bloques): array
    {
        if (! in_array($estado, self::ESTADOS, true) || ! in_array($tratamiento, self::TRATAMIENTOS, true)) {
            throw new \InvalidArgumentException('Estado o tratamiento inválido.');
        }
        if ($estado !== 'PRESENTADA' && $franjas !== []) {
            throw new \InvalidArgumentException('Solo una declaración presentada puede contener franjas.');
        }
        $permitidas = [];
        foreach ($bloques as $bloque) {
            foreach (self::DIAS as $dia) {
                $permitidas[$bloque.':'.$dia] = true;
            }
        }
        $seleccion = [];
        foreach ($franjas as $clave) {
            if (! is_string($clave) || ! isset($permitidas[$clave]) || isset($seleccion[$clave])) {
                throw new \InvalidArgumentException('Hay una franja inválida o duplicada.');
            }
            $seleccion[$clave] = true;
        }
        $claves = array_keys($seleccion);
        sort($claves, SORT_STRING);
        return $claves;
    }

    public function guardar(User $usuario, int $periodo, int $docente, string $version, string $estado, string $tratamiento, array $franjas): void
    {
        $this->contexto($usuario, $periodo, $docente);
        DB::transaction(function () use ($usuario, $periodo, $docente, $version, $estado, $tratamiento, $franjas) {
            // No modificar fuentes de horarios existentes ni aperturas ya incorporadas.
            DB::statement('LOCK TABLE gestion_horarios.horario, gestion_horarios.apertura_repeticion, gestion_horarios.bloque_horario IN SHARE MODE');
            DB::statement('LOCK TABLE gestion_horarios.declaracion_disponibilidad, gestion_horarios.disponibilidad_docente IN SHARE ROW EXCLUSIVE MODE');
            $this->contexto($usuario, $periodo, $docente);
            if (DB::table('gestion_horarios.horario')->where('id_periodo', $periodo)->exists()
                || DB::table('gestion_horarios.apertura_repeticion')->where('id_periodo', $periodo)->where('id_docente_propuesto', $docente)->where('estado', 'INCORPORADA')->exists()) {
                $this->rechazar('Existe un horario o una apertura incorporada: la modificación requiere revisión institucional.');
            }
            $actual = $this->datos($periodo, $docente);
            if (! hash_equals($actual['version'], $version)) {
                $this->rechazar('Los datos cambiaron. Cancele y vuelva a abrir la edición.');
            }
            try {
                $seleccion = self::normalizar($estado, $tratamiento, $franjas, $actual['bloques']->pluck('id_bloque')->all());
            } catch (\InvalidArgumentException $e) {
                $this->rechazar($e->getMessage());
            }
            $anteriores = $actual['franjas']; sort($anteriores, SORT_STRING);
            if ($actual['declaracion'] && $actual['estado'] === $estado && $actual['tratamiento'] === $tratamiento && $anteriores === $seleccion) {
                return;
            }
            $d = $actual['declaracion'];
            $valores = ['estado' => $estado, 'tratamiento_regular' => $tratamiento,
                'presentado_en' => $estado === 'PRESENTADA' ? now() : null,
                'observacion' => trim(($d?->observacion ?? '')."\nEdición local QA: ".now()->toIso8601String())];
            $tabla = DB::table('gestion_horarios.declaracion_disponibilidad');
            if ($d) {
                $id = $d->id_declaracion;
                $tabla->where('id_declaracion', $id)->update($valores);
            } else {
                $id = $tabla->insertGetId($valores + ['id_periodo' => $periodo, 'id_docente' => $docente], 'id_declaracion');
            }
            foreach (array_diff($anteriores, $seleccion) as $clave) {
                [$bloque, $dia] = explode(':', $clave, 2);
                DB::table('gestion_horarios.disponibilidad_docente')->where('id_periodo', $periodo)->where('id_docente', $docente)->where('id_bloque', $bloque)->where('dia_semana', $dia)->delete();
            }
            foreach (array_diff($seleccion, $anteriores) as $clave) {
                [$bloque, $dia] = explode(':', $clave, 2);
                DB::table('gestion_horarios.disponibilidad_docente')->insert(['id_periodo' => $periodo, 'id_docente' => $docente, 'id_declaracion' => $id, 'id_bloque' => (int) $bloque, 'dia_semana' => $dia]);
            }
        }, 3);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['edicionDisponibilidad' => $mensaje]);
    }
}
