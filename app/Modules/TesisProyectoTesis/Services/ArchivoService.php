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

    /**
     * Guarda un PDF generado por el sistema (por ejemplo, documentos de los escenarios demo).
     */
    public function guardarContenido(string $contenido, string $nombre, int $idUsuario, string $subcarpeta = ''): int
    {
        $ruta = trim(Parametros::CARPETA.'/'.trim($subcarpeta, '/'), '/').'/'.Str::uuid()->toString().'.pdf';
        Storage::disk(Parametros::DISCO)->put($ruta, $contenido);

        return (int) DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $this->catalogo->idModulo(),
            'nombre_original' => Str::limit(preg_replace('/[^\pL\pN ._()-]/u', '_', $nombre) ?: 'documento.pdf', 200, ''),
            'ruta_almacenamiento' => $ruta,
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => strlen($contenido),
            'hash_sha256' => hash('sha256', $contenido),
            'subido_por' => $idUsuario,
        ], 'id_archivo');
    }

    /**
     * Elimina archivos y sus registros en comun.archivo (solo los del módulo).
     *
     * @param  list<int>  $ids
     */
    public function eliminar(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $q = DB::table('comun.archivo')->whereIn('id_archivo', $ids)->where('id_modulo', $this->catalogo->idModulo());
        foreach ($q->pluck('ruta_almacenamiento') as $ruta) {
            Storage::disk(Parametros::DISCO)->delete($ruta);
        }
        $q->delete();
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
