<?php

namespace App\Modules\TesisProyectoTesis\Database\Seeders;

use App\Modules\TesisProyectoTesis\Models\TipoSolicitud;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Database\Seeder;

final class TipoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Parametros::TIPO_SOLICITUD_ASESOR, Parametros::TIPO_SOLICITUD_JURADO, Parametros::TIPO_SOLICITUD_SUSTENTACION] as $nombre) {
            TipoSolicitud::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
