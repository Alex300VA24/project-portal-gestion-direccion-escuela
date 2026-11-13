<?php

namespace Database\Seeders;

use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\Core\Persona;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de prueba de COTECU: semestres académicos y cuentas institucionales.
 *
 * Idempotente: se puede ejecutar cuantas veces sea necesario.
 * Uso: php artisan db:seed --class=CotecuDemoSeeder
 *
 * core.periodo_academico, core.persona y seguridad.usuario son tablas
 * institucionales compartidas: este seeder solo siembra lo mínimo para
 * probar el módulo (los FK de cotecu.silabos apuntan a ellas). En
 * producción los carga quien administre core/seguridad; COTECU solo lee.
 */
class CotecuDemoSeeder extends Seeder
{
    /**
     * Periodos de prueba. El último queda Activo: es el que usan las
     * pantallas por defecto y el único en el que se puede subir un sílabo.
     */
    private const PERIODOS = [
        [
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-04-13',
            'fecha_fin' => '2026-08-08',
            'numero_semanas' => 17,
            'estado' => 'Cerrado',
        ],
        [
            'codigo' => '2026-II',
            'anio' => 2026,
            'tipo' => 'II',
            'fecha_inicio' => '2026-08-24',
            'fecha_fin' => '2026-12-19',
            'numero_semanas' => 17,
            'estado' => 'Activo',
        ],
    ];

    /**
     * Cuentas del portal (public.users) con su contraparte institucional:
     * core.persona + seguridad.usuario, que es a donde apuntan las claves
     * foráneas de todos los módulos (cotecu.silabos.id_profesor, etc.).
     *
     * Puente: seguridad.usuario.correo_institucional = users.email.
     * Son filas de prueba: la app solo las lee y el acceso sigue siendo
     * el de Laravel (public.users). Si el grupo de gestión redefine la
     * estrategia de identidad, se limpian con:
     *   DELETE FROM seguridad.usuario WHERE correo_institucional LIKE '%@portal.local';
     *   DELETE FROM core.persona WHERE numero_documento LIKE '7000000%';
     */
    private const USUARIOS = [
        ['email' => 'director@portal.local', 'numero_documento' => '70000001', 'nombres' => 'Director', 'apellido_paterno' => 'Escuela'],
        ['email' => 'secretaria@portal.local', 'numero_documento' => '70000002', 'nombres' => 'Secretaría', 'apellido_paterno' => 'Académica'],
        ['email' => 'profesor@portal.local', 'numero_documento' => '70000003', 'nombres' => 'Profesor', 'apellido_paterno' => 'Demo'],
        ['email' => 'alumno@portal.local', 'numero_documento' => '70000004', 'nombres' => 'Alumno', 'apellido_paterno' => 'Demo'],
    ];

    public function run(): void
    {
        foreach (self::PERIODOS as $periodo) {
            PeriodoAcademico::firstOrCreate(['codigo' => $periodo['codigo']], $periodo);
        }

        foreach (self::USUARIOS as $cuenta) {
            $persona = Persona::firstOrCreate(
                ['tipo_documento' => 'DNI', 'numero_documento' => $cuenta['numero_documento']],
                [
                    'nombres' => $cuenta['nombres'],
                    'apellido_paterno' => $cuenta['apellido_paterno'],
                    'correo_personal' => $cuenta['email'],
                ],
            );

            Usuario::firstOrCreate(
                ['correo_institucional' => $cuenta['email']],
                [
                    'id_persona' => $persona->id_persona,
                    'contrasena_hash' => Hash::make('Portal2026!'),
                    'estado' => 'Activo',
                    'debe_cambiar_contrasena' => false,
                ],
            );
        }

        $this->command?->info('Datos demo de COTECU listos: 2 periodos y 4 cuentas institucionales.');
    }
}
