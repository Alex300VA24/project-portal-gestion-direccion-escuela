<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Guarda los PDF fuera de public (disco local, carpeta tesis/) con nombre generado
 * y los registra en comun.archivo. La descarga solo se hace por la ruta protegida del módulo.
 */
final class ArchivoService
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function guardar(UploadedFile $archivo, int $idUsuario): int
    {
        $ruta = $archivo->storeAs(Parametros::CARPETA, Str::uuid()->toString().'.pdf', Parametros::DISCO);

        if ($ruta === false) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        $nombre = Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '_', $archivo->getClientOriginalName()) ?: 'documento.pdf', 200, '');

        return (int) DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $this->catalogo->idModulo(),
            'nombre_original' => $nombre,
            'ruta_almacenamiento' => $ruta,
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => (int) $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath()) ?: null,
            'subido_por' => $idUsuario,
        ], 'id_archivo');
    }

    /** @return array{nombre: string, tamano: int}|null */
    public function datos(?int $idArchivo): ?array
    {
        if ($idArchivo === null) {
            return null;
        }

        $a = DB::table('comun.archivo')->where('id_archivo', $idArchivo)->first(['nombre_original', 'tamano_bytes']);

        return $a ? ['nombre' => (string) $a->nombre_original, 'tamano' => (int) $a->tamano_bytes] : null;
    }

    public function descargar(int $idArchivo): StreamedResponse
    {
        $a = DB::table('comun.archivo')
            ->where('id_archivo', $idArchivo)
            ->where('id_modulo', $this->catalogo->idModulo())
            ->first(['nombre_original', 'ruta_almacenamiento']);

        abort_if($a === null || ! Storage::disk(Parametros::DISCO)->exists($a->ruta_almacenamiento), 404, 'El archivo no está disponible.');

        return Storage::disk(Parametros::DISCO)->response($a->ruta_almacenamiento, $a->nombre_original, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
