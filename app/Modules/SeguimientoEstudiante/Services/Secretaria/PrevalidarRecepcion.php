<?php

namespace App\Modules\SeguimientoEstudiante\Services\Secretaria;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PrevalidarRecepcion
{
    private const REFERENCIA = ['registration', 'student', 'course', 'section'];

    private const COLUMNAS = [
        'id' => ['id', 'identificador', 'identificador_matricula'],
        'student' => ['student', 'cod_estudiante', 'codigo_estudiante', 'codigo_alumno', 'cod_alumno', 'alumno', 'estudiante'],
        'course' => ['course', 'cod_curso', 'codigo_curso', 'curso'],
        'section' => ['section', 'seccion'],
        'times' => ['times', 'numero_vez', 'vez'],
        'registration' => ['registration', 'identificador_matricula', 'matricula'],
        'unit' => ['unit', 'evaluacion', 'evaluacion_o_unidad', 'unidad'],
        'value' => ['value', 'nota', 'valor'],
        'total' => ['total', 'total_clases'],
        'absent' => ['absent', 'inasistencias', 'faltas'],
    ];

    private const REGLAS = [
        'matriculas' => [
            'id' => 'nullable|string|max:50',
            'student' => 'required|string|max:50',
            'course' => 'required|string|max:50',
            'section' => 'required|string|max:10',
            'times' => 'required|integer|min:1|max:10',
        ],
        'notas' => [
            'unit' => 'required|string|max:50',
            'value' => 'required|numeric|min:0|max:20',
        ],
        'asistencia' => [
            'total' => 'required|integer|min:1|max:500',
            'absent' => 'required|integer|min:0|lte:total',
        ],
    ];

    public function __construct(private readonly CsvReader $lector) {}

    public function revisar(string $tipo, string $bytes): array
    {
        $resultado = $this->normalizar($tipo, $bytes);
        unset($resultado['registros']);

        return $resultado;
    }

    public function encabezados(string $tipo): array
    {
        return array_keys($this->reglas($tipo));
    }

    private function reglas(string $tipo): array
    {
        $reglas = self::REGLAS[$tipo];
        if ($tipo !== 'matriculas') {
            $reglas += [
                'registration' => 'nullable|string|max:50',
                'student' => 'required_without:registration|nullable|string|max:50',
                'course' => 'required_without:registration|nullable|string|max:50',
                'section' => 'required_without:registration|nullable|string|max:10',
            ];
        }

        return $reglas;
    }

    public function normalizar(string $tipo, string $bytes): array
    {
        $csv = $this->lector->read($bytes);
        $reglas = $this->reglas($tipo);
        $columnas = $this->correspondencia(array_keys($reglas), $csv['headers']);
        $resultados = [];
        $vistos = [];
        $idsVistos = [];
        $registros = [];
        foreach ($csv['rows'] as $fila) {
            $datos = [];
            foreach ($columnas as $campo => $posicion) {
                $valor = $fila['values'][$posicion];
                $datos[$campo] = $valor === '' ? null : $valor;
            }
            $validacion = validator($datos, $reglas);
            if ($validacion->fails()) {
                $resultados[$fila['line']] = collect(array_keys($validacion->failed()))
                    ->map(fn (string $campo) => 'Revisa la columna '.$campo.' y su formato o rango.')->all();

                continue;
            }

            $referencia = isset($datos['registration'])
                ? [$datos['registration']]
                : [$datos['student'], $datos['course'], $datos['section']];
            $clave = serialize([$referencia, $datos['unit'] ?? null]);
            $id = $datos['id'] ?? null;
            $previa = $vistos[$clave] ?? ($id !== null ? ($idsVistos[$id] ?? null) : null);
            if ($previa !== null) {
                $resultados[$fila['line']] = ['La fila repite la referencia de la fila '.$previa.'.'];
            } else {
                $registros[$fila['line']] = $datos;
            }
            $vistos[$clave] ??= $fila['line'];
            if ($id !== null) {
                $idsVistos[$id] ??= $fila['line'];
            }
        }

        return ['filas' => count($csv['rows']), 'delimitador' => $csv['delimiter'], 'errores' => $resultados, 'registros' => $registros];
    }

    private function correspondencia(array $campos, array $encabezados): array
    {
        $normalizados = array_map(fn (string $campo) => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', Str::ascii($campo))), $encabezados);
        $columnas = [];
        foreach ($campos as $campo) {
            $coincidencias = array_keys(array_intersect($normalizados, self::COLUMNAS[$campo]));
            if (count($coincidencias) > 1) {
                throw ValidationException::withMessages(['archivo' => 'Hay varias columnas para '.$campo.'. Deja una sola.']);
            }
            if ($coincidencias !== []) {
                $columnas[$campo] = $coincidencias[0];
            }
        }

        $obligatorios = array_diff($campos, ['id', ...self::REFERENCIA]);
        if (! in_array('registration', $campos, true) || ! isset($columnas['registration'])) {
            $obligatorios = array_merge($obligatorios, ['student', 'course', 'section']);
        }
        $faltantes = array_diff($obligatorios, array_keys($columnas));
        if ($faltantes !== []) {
            throw ValidationException::withMessages(['archivo' => 'Faltan columnas reconocidas para '.implode(', ', $faltantes).'.']);
        }

        return $columnas;
    }
}
