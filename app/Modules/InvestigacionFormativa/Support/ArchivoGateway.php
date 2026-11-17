<?php

namespace App\Modules\InvestigacionFormativa\Support;

interface ArchivoGateway
{
    /**
     * Create one row in the shared archive catalog.
     *
     * @param array{
     *     id_modulo: int,
     *     nombre_original: string,
     *     ruta_almacenamiento: string,
     *     tipo_mime: string,
     *     tamano_bytes: int,
     *     hash_sha256: string,
     *     subido_por: int
     * } $metadata
     * @return int Shared archive id.
     */
    public function crear(array $metadata): int;

    /**
     * Read one shared archive row.
     *
     * @return array{
     *     id_archivo: int,
     *     id_modulo: int,
     *     nombre_original: string,
     *     ruta_almacenamiento: string,
     *     tipo_mime: string,
     *     tamano_bytes: int,
     *     hash_sha256: string|null,
     *     subido_por: int,
     *     subido_en: string
     * }|null
     */
    public function buscar(int $idArchivo): ?array;
}
