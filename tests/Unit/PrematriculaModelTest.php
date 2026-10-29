<?php

namespace Tests\Unit;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\DetalleSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrematriculaModelTest extends TestCase
{
    #[DataProvider('models')]
    public function test_models_use_the_expected_schema_table_and_primary_key(
        string $modelClass,
        string $table,
        string $primaryKey,
    ): void {
        $model = new $modelClass;

        $this->assertSame($table, $model->getTable());
        $this->assertSame($primaryKey, $model->getKeyName());
        $this->assertFalse($model->usesTimestamps());
    }

    /** @return array<string, array{class-string, string, string}> */
    public static function models(): array
    {
        return [
            'cronograma' => [
                CronogramaPrematricula::class,
                'prematricula.cronograma_prematricula',
                'id_cronograma',
            ],
            'solicitud' => [
                SolicitudPrematricula::class,
                'prematricula.solicitud_prematricula',
                'id_solicitud',
            ],
            'detalle' => [
                DetalleSolicitud::class,
                'prematricula.detalle_solicitud',
                'id_detalle',
            ],
        ];
    }
}
