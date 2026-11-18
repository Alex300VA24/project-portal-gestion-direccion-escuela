<?php

namespace App\Modules\SeguimientoEgresado\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Completa el padrón de ejemplo con egresados inventados para que cada promoción concluida tenga una tasa de egreso creíble
 * (I1 entre 58 % y 78 %) y no unos pocos registros frente a 50 ingresantes.
 *
 * - Sin invitaciones ni respuestas: no cambia I2, I4, I5 ni las cifras de las campañas.
 * - Todos los correos son de dominio `.test`: ninguno puede ser de una persona real ni recibir un correo.
 * - Matrículas, documentos y correos en rangos propios, que no chocan con el CSV de demostración (`alumnos-aptos-demo.csv`).
 * - Los de la Promoción 2019 no dejan contacto: así «Seguimiento a egresados 2026-II» convoca solo a quienes ya lo tienen.
 * - Se puede ejecutar varias veces sin duplicar nada: cada persona se identifica por su matrícula.
 */
class PadronSinteticoSeeder extends Seeder
{
    /** Promoción => proporción de ingresantes que debe figurar como egresada. */
    private const TASAS = [2018 => 0.78, 2019 => 0.72, 2020 => 0.68, 2021 => 0.58];

    private const NOMBRES = ['Luis', 'María', 'Carlos', 'Ana', 'José', 'Rosa', 'Miguel', 'Lucía', 'Jorge', 'Carmen', 'Pedro', 'Elena', 'Diego', 'Sofía', 'Andrés',
        'Paola', 'Ricardo', 'Karla', 'Fernando', 'Gabriela', 'Héctor', 'Natalia', 'Óscar', 'Verónica', 'Raúl', 'Milagros', 'Eduardo', 'Pamela', 'Víctor', 'Daniela'];

    private const APELLIDOS = ['Rodríguez', 'García', 'Quispe', 'Flores', 'Sánchez', 'Ramírez', 'Torres', 'Chávez', 'Vásquez', 'Díaz', 'Castillo', 'Rojas', 'Mendoza', 'Ruiz',
        'Cruz', 'Alva', 'Paredes', 'Salazar', 'Vega', 'Neyra', 'Becerra', 'Campos', 'Terrones', 'Lozano', 'Cabrera', 'Aguilar', 'Zavaleta', 'Benites', 'Cerna', 'Llamo'];

    public function run(): void
    {
        $usuario = (int) DB::table('seguimiento_egresado.usuario_interno')->min('id');
        $estados = $this->estadosDeEgreso();
        if ($usuario === 0 || $estados === []) {
            return;
        }

        DB::transaction(function () use ($usuario, $estados) {
            foreach (self::TASAS as $anio => $tasa) {
                $cohorte = DB::table('seguimiento_egresado.cohorte')->where('anio_ingreso', $anio)->orderBy('id')->first(['id', 'total_ingresantes']);
                if ($cohorte === null) {
                    continue;
                }

                $existentes = DB::table('seguimiento_egresado.historial_estado_academico as h')
                    ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'h.id_egresado')
                    ->where('e.id_cohorte', $cohorte->id)->whereNull('h.vigente_hasta')->whereIn('h.id_estado_academico', array_values($estados))
                    ->distinct()->count('e.id');
                $faltan = max(0, (int) round($cohorte->total_ingresantes * $tasa) - $existentes);

                for ($i = 1; $i <= $faltan; $i++) {
                    $this->crear((int) $cohorte->id, $anio, $i, $estados, $usuario);
                }
            }
        });
    }

    /** @return array<string, int> código => id de los estados que cuentan como egreso */
    private function estadosDeEgreso(): array
    {
        return DB::table('seguimiento_egresado.cat_estado_academico')->whereIn('codigo', ['EGRESADO', 'BACHILLER', 'TITULADO'])->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();
    }

    /** @param  array<string, int>  $estados */
    private function crear(int $cohorte, int $anio, int $n, array $estados, int $usuario): void
    {
        $matricula = sprintf('%d1%05d', $anio, 50000 + $n);
        if (DB::table('seguimiento_egresado.egresado')->where('codigo_matricula', $matricula)->exists()) {
            return;
        }

        $nombre = self::NOMBRES[($n * 7 + $anio) % count(self::NOMBRES)];
        $paterno = self::APELLIDOS[($n * 11 + $anio) % count(self::APELLIDOS)];
        $materno = self::APELLIDOS[($n * 5 + 3 * $anio) % count(self::APELLIDOS)];
        $base = $this->sinAcentos(mb_strtolower($nombre.'.'.$paterno)).$n.($anio % 100);
        // Dos de cada cinco egresan en julio del quinto año; el resto, en diciembre del cuarto.
        [$egreso, $periodo] = $n % 5 < 2 ? [($anio + 5).'-07-19', ($anio + 5).'-I'] : [($anio + 4).'-12-15', ($anio + 4).'-II'];
        $codigoEstado = ['EGRESADO', 'EGRESADO', 'BACHILLER', 'EGRESADO', 'TITULADO', 'BACHILLER'][$n % 6];

        $egresado = DB::table('seguimiento_egresado.egresado')->insertGetId([
            'id_cohorte' => $cohorte, 'codigo_matricula' => $matricula, 'tipo_documento' => 'DNI', 'numero_documento' => sprintf('9%07d', ($anio % 100) * 10000 + $n),
            'nombres' => $nombre, 'apellido_paterno' => $paterno, 'apellido_materno' => $materno, 'programa' => 'Ingeniería Informática',
            'correo_institucional' => $base.'@correo.test', 'sede' => 'Sede Central (Trujillo)', 'fecha_ingreso' => $anio.'-03-18',
            'fecha_egreso' => $egreso, 'periodo_egreso' => $periodo,
        ]);
        DB::table('seguimiento_egresado.historial_estado_academico')->insert([
            'id_egresado' => $egresado, 'id_estado_academico' => $estados[$codigoEstado], 'apto_graduarse' => true, 'id_usuario_registro' => $usuario,
        ]);

        // Cuatro de cada cinco dejaron su contacto; la Promoción 2019 no, para no sumar ruido a su campaña de demostración.
        if ($anio !== 2019 && $n % 5 !== 0) {
            DB::table('seguimiento_egresado.contacto_egresado')->insert([
                'id_egresado' => $egresado, 'correo_personal' => $base.'@correo.test', 'celular_whatsapp' => sprintf('+51 9%02d %03d %03d', 40 + $n % 50, ($n * 37) % 1000, ($n * 91 + $anio) % 1000),
                'telefono_fijo' => null, 'direccion' => 'Trujillo', 'vigente_desde' => ($anio + 5).'-09-01 10:00:00-05:00', 'origen_registro' => 'SECRETARIA',
            ]);
        }
    }

    private function sinAcentos(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }
}
