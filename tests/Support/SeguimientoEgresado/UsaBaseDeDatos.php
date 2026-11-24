<?php

namespace Tests\Support\SeguimientoEgresado;

use Illuminate\Support\Facades\DB;

/**
 * Abre la transacción de la prueba antes de cada una y la revierte después (Laravel llama a setUp/tearDown de cada
 * trait). Además deja el personal interno del módulo que dan por existente los datos de prueba y las pantallas
 * (la Secretaría y la Dirección); se revierte junto con la prueba.
 */
trait UsaBaseDeDatos
{
    use UsaEsquemaPostgres;

    protected function setUpUsaBaseDeDatos(): void
    {
        $this->prepararPostgres();
        $this->asegurarPersonalDelModulo();
    }

    protected function tearDownUsaBaseDeDatos(): void
    {
        $this->revertirPostgres();
    }

    protected function asegurarPersonalDelModulo(): void
    {
        foreach ([
            ['SECRETARIA', 'secretaria.informatica@unitru.edu.pe', 'Secretaría de Informática'],
            ['DIRECTOR', 'direccion.informatica@unitru.edu.pe', 'Dirección de Informática'],
        ] as [$rol, $correo, $nombre]) {
            DB::table('seguimiento_egresado.usuario_interno')->insertOrIgnore([
                'id_rol' => DB::table('seguimiento_egresado.rol_interno')->where('codigo', $rol)->value('id'),
                'nombre_completo' => $nombre,
                'correo_institucional' => $correo,
                'password_hash' => '!portal',
                'activo' => true,
            ]);
        }
    }
}
