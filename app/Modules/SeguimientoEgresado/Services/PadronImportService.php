<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Models\CatEstadoAcademico;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\HistorialEstadoAcademico;
use App\Modules\SeguimientoEgresado\Models\ImportacionFila;
use App\Modules\SeguimientoEgresado\Models\ImportacionPadron;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PadronImportService
{
    /**
     * Procesa un archivo CSV o Excel (.xlsx) para importar egresados al padrón oficial.
     * Evita duplicados, valida campos obligatorios y audita cada fila.
     *
     * @param  UploadedFile|string  $archivo  Archivo subido o ruta en disco
     * @param  int|null  $usuarioId  ID del usuario de secretaría que realiza la carga
     * @param  bool  $atomico  Si es true (por defecto), revierte toda la carga ante cualquier inconsistencia
     * @return array Resumen de la importación
     */
    public function importarArchivo($archivo, ?int $usuarioId = null, bool $atomico = true): array
    {
        $usuarioId = $usuarioId ?? UsuarioInterno::idActual();
        $nombreOriginal = ($archivo instanceof UploadedFile) ? $archivo->getClientOriginalName() : basename($archivo);
        $rutaReal = ($archivo instanceof UploadedFile) ? $archivo->getRealPath() : $archivo;

        $contenido = file_get_contents($rutaReal);
        $sha256 = hash('sha256', $contenido);

        // Crear registro cabecera de importación
        $importacion = ImportacionPadron::create([
            'id_usuario' => $usuarioId,
            'nombre_archivo' => $nombreOriginal,
            'hash_archivo' => $sha256,
            'origen' => 'ARCHIVO',
            'fecha_importacion' => now(),
            'estado' => 'EN_PROCESO',
        ]);

        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $esXlsx = $extension === 'xlsx' || str_starts_with($contenido, "PK\x03\x04");

        try {
            if ($esXlsx) {
                $filas = $this->parsearXlsx($rutaReal);
            } else {
                $filas = $this->parsearFilas($contenido);
            }
        } catch (\Throwable $e) {
            $importacion->update(['estado' => 'CON_ERRORES']);
            ImportacionFila::create([
                'id_importacion' => $importacion->id,
                'numero_fila' => 1,
                'datos_entrada' => ['archivo' => $nombreOriginal],
                'estado' => 'RECHAZADA',
                'detalle_error' => 'No se pudo leer el archivo: '.$e->getMessage(),
            ]);

            return [
                'importacion_id' => $importacion->id,
                'nombre_archivo' => $nombreOriginal,
                'hash_archivo' => $sha256,
                'total_procesadas' => 0,
                'aceptadas' => 0,
                'rechazadas' => 1,
                'estado' => 'CON_ERRORES',
                'detalles' => [
                    ['numero_fila' => 1, 'estado' => 'RECHAZADA', 'error' => $e->getMessage()],
                ],
            ];
        }

        if (empty($filas)) {
            $importacion->update(['estado' => 'CON_ERRORES']);

            return [
                'importacion_id' => $importacion->id,
                'nombre_archivo' => $nombreOriginal,
                'hash_archivo' => $sha256,
                'total_procesadas' => 0,
                'aceptadas' => 0,
                'rechazadas' => 0,
                'estado' => 'CON_ERRORES',
                'detalles' => [],
            ];
        }

        $estadoEgresadoId = CatEstadoAcademico::where('codigo', 'EGRESADO')->value('id');
        $estadoBachillerId = CatEstadoAcademico::where('codigo', 'BACHILLER')->value('id');
        $estadoTituladoId = CatEstadoAcademico::where('codigo', 'TITULADO')->value('id');

        // MODO ATÓMICO (Todo o Nada)
        if ($atomico) {
            return $this->procesarImportacionAtomica(
                $filas,
                $importacion,
                $usuarioId,
                $estadoEgresadoId,
                $estadoBachillerId,
                $estadoTituladoId
            );
        }

        // MODO TOLERANTE (Por fila con bitácora de auditoría)
        $aceptadas = 0;
        $rechazadas = 0;
        $detalles = [];

        foreach ($filas as $indice => $filaData) {
            $numeroFila = $indice + 1;

            $resultadoFila = $this->procesarFila(
                $filaData,
                $importacion->id,
                $numeroFila,
                $usuarioId,
                $estadoEgresadoId,
                $estadoBachillerId,
                $estadoTituladoId
            );

            if ($resultadoFila['estado'] === 'ACEPTADA') {
                $aceptadas++;
            } else {
                $rechazadas++;
            }

            $detalles[] = $resultadoFila;
        }

        $estadoFinal = ($rechazadas > 0 && $aceptadas === 0)
            ? 'CON_ERRORES'
            : (($rechazadas > 0) ? 'VALIDADA' : 'VALIDADA');

        $importacion->update(['estado' => $estadoFinal]);

        return [
            'importacion_id' => $importacion->id,
            'nombre_archivo' => $nombreOriginal,
            'hash_archivo' => $sha256,
            'total_procesadas' => count($filas),
            'aceptadas' => $aceptadas,
            'rechazadas' => $rechazadas,
            'estado' => $estadoFinal,
            'detalles' => $detalles,
        ];
    }

    /**
     * Procesa la importación en modo estrictamente atómico (Todo o Nada).
     * Si alguna fila presenta inconsistencias, se cancela la inserción de todos los egresados.
     */
    protected function procesarImportacionAtomica(
        array $filas,
        ImportacionPadron $importacion,
        int $usuarioId,
        int $estEgresadoId,
        int $estBachillerId,
        int $estTituladoId
    ): array {
        $errores = [];
        $matriculasVistas = [];
        $dnisVistos = [];

        // Fase 1: Pre-validación sin persistencia
        foreach ($filas as $indice => $filaData) {
            $numeroFila = $indice + 1;
            $err = $this->validarFilaPrevia($filaData, $matriculasVistas, $dnisVistos);
            if ($err !== null) {
                $errores[$numeroFila] = $err;
            }
        }

        // Si existen errores, no se guarda ningún egresado
        if (! empty($errores)) {
            $detalles = [];
            foreach ($filas as $indice => $filaData) {
                $num = $indice + 1;
                $tieneError = isset($errores[$num]);
                $msgError = $tieneError
                    ? $errores[$num]
                    : 'Carga cancelada: Se detectaron inconsistencias en otras filas del archivo (Modo Estricto Todo o Nada).';

                ImportacionFila::create([
                    'id_importacion' => $importacion->id,
                    'numero_fila' => $num,
                    'datos_entrada' => $filaData,
                    'estado' => 'RECHAZADA',
                    'detalle_error' => $msgError,
                ]);

                $detalles[] = [
                    'numero_fila' => $num,
                    'matricula' => $filaData['matricula'] ?? $filaData['codigo'] ?? 'N/A',
                    'nombre' => $filaData['nombres'] ?? $filaData['nombre'] ?? 'N/A',
                    'estado' => 'RECHAZADA',
                    'error' => $msgError,
                ];
            }

            $importacion->update(['estado' => 'CON_ERRORES']);

            return [
                'importacion_id' => $importacion->id,
                'nombre_archivo' => $importacion->nombre_archivo,
                'hash_archivo' => $importacion->hash_archivo,
                'total_procesadas' => count($filas),
                'aceptadas' => 0,
                'rechazadas' => count($filas),
                'estado' => 'CON_ERRORES',
                'modo' => 'ATÓMICO_TODO_O_NADA',
                'detalles' => $detalles,
            ];
        }

        // Fase 2: Todo válido -> inserción atómica en bloque
        return DB::transaction(function () use (
            $filas,
            $importacion,
            $usuarioId,
            $estEgresadoId,
            $estBachillerId,
            $estTituladoId
        ) {
            $detalles = [];
            foreach ($filas as $indice => $filaData) {
                $res = $this->procesarFila(
                    $filaData,
                    $importacion->id,
                    $indice + 1,
                    $usuarioId,
                    $estEgresadoId,
                    $estBachillerId,
                    $estTituladoId
                );
                $detalles[] = $res;
            }

            $importacion->update(['estado' => 'VALIDADA']);

            return [
                'importacion_id' => $importacion->id,
                'nombre_archivo' => $importacion->nombre_archivo,
                'hash_archivo' => $importacion->hash_archivo,
                'total_procesadas' => count($filas),
                'aceptadas' => count($filas),
                'rechazadas' => 0,
                'estado' => 'VALIDADA',
                'modo' => 'ATÓMICO_TODO_O_NADA',
                'detalles' => $detalles,
            ];
        });
    }

    /**
     * Valida reglas de integridad de una fila sin persistir cambios en la base de datos.
     */
    protected function validarFilaPrevia(array $data, array &$matriculasVistas, array &$dnisVistos): ?string
    {
        $norm = $this->normalizarClaves($data);

        $matricula = $norm['matricula'] ?? $norm['codigo_matricula'] ?? $norm['codigo'] ?? null;
        $numDoc = $norm['numero_documento'] ?? $norm['dni'] ?? $norm['documento'] ?? null;
        $tipoDoc = $norm['tipo_documento'] ?? $norm['tipo_doc'] ?? 'DNI';
        $nombres = $norm['nombres'] ?? $norm['nombre'] ?? null;
        $apPaterno = $norm['apellido_paterno'] ?? $norm['paterno'] ?? $norm['primer_apellido'] ?? null;
        $anioCohorte = $norm['cohorte'] ?? $norm['anio_ingreso'] ?? $norm['promocion'] ?? null;

        if (empty($matricula)) {
            return 'Fila sin código de matrícula universitario obligatorio.';
        }
        if (isset($matriculasVistas[$matricula])) {
            return "La matrícula '{$matricula}' se encuentra repetida dentro del mismo archivo.";
        }
        $matriculasVistas[$matricula] = true;

        if (Egresado::where('codigo_matricula', $matricula)->exists()) {
            return "La matrícula '{$matricula}' ya se encuentra registrada en el padrón institucional.";
        }

        if (empty($numDoc)) {
            return 'Fila sin número de documento de identidad obligatorio.';
        }
        if (isset($dnisVistos[$numDoc])) {
            return "El {$tipoDoc} '{$numDoc}' se encuentra repetido dentro del mismo archivo.";
        }
        $dnisVistos[$numDoc] = true;

        if (Egresado::where('tipo_documento', $tipoDoc)->where('numero_documento', $numDoc)->exists()) {
            return "El {$tipoDoc} '{$numDoc}' ya pertenece a otro egresado registrado en el padrón.";
        }

        if (empty($nombres) || empty($apPaterno)) {
            return 'Nombres y apellido paterno son obligatorios.';
        }

        if (empty($anioCohorte)) {
            return 'La cohorte de ingreso (año) es obligatoria.';
        }
        $anioNum = (int) preg_replace('/[^0-9]/', '', $anioCohorte);
        if (! Cohorte::where('anio_ingreso', $anioNum)->exists()) {
            return "La cohorte '{$anioCohorte}' no existe en el catálogo institucional. Debe registrarla previamente en 'Cohortes y Denominador I1' con su número oficial de ingresantes.";
        }

        return null;
    }

    /**
     * Normaliza las claves de un arreglo asociativo a formato canónico.
     */
    protected function normalizarClaves(array $data): array
    {
        $norm = [];
        foreach ($data as $k => $v) {
            $cleanKey = strtolower(trim((string) $k));
            $cleanKey = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $cleanKey);
            $cleanKey = preg_replace('/[^a-z0-9_]/', '_', $cleanKey);
            $norm[$cleanKey] = trim((string) $v);
        }

        return $norm;
    }

    /**
     * Procesa una única fila con validación atómica y registro en importacion_fila.
     */
    protected function procesarFila(
        array $data,
        int $importacionId,
        int $numeroFila,
        int $usuarioId,
        int $estEgresadoId,
        int $estBachillerId,
        int $estTituladoId
    ): array {
        // Normalización de claves (minúsculas y sin acentos ni espacios extra)
        $norm = [];
        foreach ($data as $k => $v) {
            $cleanKey = strtolower(trim($k));
            $cleanKey = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $cleanKey);
            $cleanKey = preg_replace('/[^a-z0-9_]/', '_', $cleanKey);
            $norm[$cleanKey] = trim((string) $v);
        }

        $matricula = $norm['matricula'] ?? $norm['codigo_matricula'] ?? $norm['codigo'] ?? null;
        $numDoc = $norm['numero_documento'] ?? $norm['dni'] ?? $norm['documento'] ?? null;
        $tipoDoc = $norm['tipo_documento'] ?? $norm['tipo_doc'] ?? 'DNI';
        $nombres = $norm['nombres'] ?? $norm['nombre'] ?? null;
        $apPaterno = $norm['apellido_paterno'] ?? $norm['paterno'] ?? $norm['primer_apellido'] ?? null;
        $apMaterno = $norm['apellido_materno'] ?? $norm['materno'] ?? $norm['segundo_apellido'] ?? null;
        $anioCohorte = $norm['cohorte'] ?? $norm['anio_ingreso'] ?? $norm['promocion'] ?? null;
        $correoInst = $norm['correo_institucional'] ?? $norm['correo_inst'] ?? null;
        $correoPers = $norm['correo_personal'] ?? $norm['correo'] ?? $norm['email'] ?? null;
        $telefono = $norm['celular'] ?? $norm['telefono'] ?? $norm['whatsapp'] ?? null;
        $fijo = $norm['telefono_fijo'] ?? $norm['fijo'] ?? null;
        $direccion = $norm['direccion'] ?? null;
        $fechaIngreso = $norm['fecha_ingreso'] ?? null;
        $fechaEgreso = $norm['fecha_egreso'] ?? null;
        $estadoAcademicoStr = strtoupper($norm['estado_academico'] ?? $norm['estado'] ?? 'EGRESADO');

        // Validaciones obligatorias
        if (empty($matricula)) {
            return $this->registrarFilaRechazada($importacionId, $numeroFila, $data, 'La matrícula es obligatoria.');
        }

        if (empty($nombres) || empty($apPaterno)) {
            return $this->registrarFilaRechazada($importacionId, $numeroFila, $data, 'Los nombres y apellido paterno son obligatorios.');
        }

        if (empty($numDoc)) {
            return $this->registrarFilaRechazada($importacionId, $numeroFila, $data, 'El número de documento (DNI) es obligatorio.');
        }

        // 1. Verificación de duplicados por matrícula
        $duplicadoMatricula = Egresado::where('codigo_matricula', $matricula)->first();
        if ($duplicadoMatricula) {
            return $this->registrarFilaRechazada(
                $importacionId,
                $numeroFila,
                $data,
                "La matrícula '{$matricula}' ya se encuentra registrada en el padrón para el expediente ID #{$duplicadoMatricula->id} ({$duplicadoMatricula->nombre_completo}).",
                $duplicadoMatricula->id
            );
        }

        // 2. Verificación de duplicados por DNI / documento
        $duplicadoDoc = Egresado::where('tipo_documento', $tipoDoc)->where('numero_documento', $numDoc)->first();
        if ($duplicadoDoc) {
            return $this->registrarFilaRechazada(
                $importacionId,
                $numeroFila,
                $data,
                "El {$tipoDoc} '{$numDoc}' ya pertenece a otro egresado registrado (Matrícula: {$duplicadoDoc->codigo_matricula}, Nombre: {$duplicadoDoc->nombre_completo}).",
                $duplicadoDoc->id
            );
        }

        // 3. Resolución de Cohorte (debe existir previamente en el catálogo institucional)
        if (empty($anioCohorte)) {
            return $this->registrarFilaRechazada(
                $importacionId,
                $numeroFila,
                $data,
                'La cohorte de ingreso (año) es obligatoria.'
            );
        }

        $anioNum = (int) preg_replace('/[^0-9]/', '', $anioCohorte);
        $cohorte = Cohorte::where('anio_ingreso', $anioNum)->first();
        if (! $cohorte && ! empty($anioCohorte)) {
            $cohorte = Cohorte::where('codigo_promocion', trim($anioCohorte))->first();
        }

        if (! $cohorte) {
            return $this->registrarFilaRechazada(
                $importacionId,
                $numeroFila,
                $data,
                "La cohorte '{$anioCohorte}' no existe en el catálogo institucional. Debe registrarla previamente en 'Cohortes y Denominador I1' con su número oficial de ingresantes."
            );
        }

        // 4. Mapeo del estado académico inicial
        $estadoId = match ($estadoAcademicoStr) {
            'TITULADO' => $estTituladoId,
            'BACHILLER' => $estBachillerId,
            default => $estEgresadoId,
        };

        // Inserción atómica en BD
        try {
            return DB::transaction(function () use (
                $cohorte,
                $matricula,
                $tipoDoc,
                $numDoc,
                $nombres,
                $apPaterno,
                $apMaterno,
                $correoInst,
                $correoPers,
                $telefono,
                $fijo,
                $direccion,
                $fechaIngreso,
                $fechaEgreso,
                $estadoId,
                $estBachillerId,
                $estTituladoId,
                $usuarioId,
                $importacionId,
                $numeroFila,
                $data
            ) {
                $egresado = Egresado::create([
                    'id_cohorte' => $cohorte->id,
                    'codigo_matricula' => $matricula,
                    'tipo_documento' => $tipoDoc,
                    'numero_documento' => $numDoc,
                    'nombres' => ucwords(strtolower($nombres)),
                    'apellido_paterno' => ucwords(strtolower($apPaterno)),
                    'apellido_materno' => $apMaterno ? ucwords(strtolower($apMaterno)) : null,
                    'programa' => 'Ingeniería Informática',
                    'correo_institucional' => $correoInst ?: "{$matricula}@unitru.edu.pe",
                    'sede' => 'Sede Central (Trujillo)',
                    'fecha_ingreso' => $fechaIngreso ?: "{$cohorte->anio_ingreso}-04-01",
                    'fecha_egreso' => $fechaEgreso ?: (($cohorte->anio_ingreso + 4).'-12-20'),
                    'periodo_egreso' => ($cohorte->anio_ingreso + 4).'-II',
                    'creado_en' => now(),
                ]);

                // Crear contacto inicial
                ContactoEgresado::create([
                    'id_egresado' => $egresado->id,
                    'correo_personal' => $correoPers ?: "egresado.{$matricula}@gmail.com",
                    'celular_whatsapp' => $telefono ?: '999000000',
                    'telefono_fijo' => $fijo,
                    'direccion' => $direccion ?: 'Trujillo, La Libertad',
                    'vigente_desde' => now(),
                    'vigente_hasta' => null,
                    'origen_registro' => 'SECRETARIA',
                ]);

                // Crear estado académico vigente
                HistorialEstadoAcademico::create([
                    'id_egresado' => $egresado->id,
                    'id_estado_academico' => $estadoId,
                    'apto_graduarse' => true,
                    'creditos_aprobados' => 220.0,
                    'fecha_bachiller' => ($estadoId === $estBachillerId || $estadoId === $estTituladoId) ? now()->subMonths(6) : null,
                    'resolucion_bachiller' => ($estadoId === $estBachillerId || $estadoId === $estTituladoId) ? "R-AUTO-{$matricula}-UNT" : null,
                    'fecha_titulo' => ($estadoId === $estTituladoId) ? now()->subMonths(1) : null,
                    'resolucion_titulo' => ($estadoId === $estTituladoId) ? "R-TIT-{$matricula}-UNT" : null,
                    'vigente_desde' => now(),
                    'vigente_hasta' => null,
                    'id_usuario_registro' => $usuarioId,
                ]);

                // Registrar fila aceptada
                ImportacionFila::create([
                    'id_importacion' => $importacionId,
                    'numero_fila' => $numeroFila,
                    'datos_entrada' => $data,
                    'estado' => 'ACEPTADA',
                    'detalle_error' => null,
                    'id_egresado' => $egresado->id,
                ]);

                return [
                    'numero_fila' => $numeroFila,
                    'matricula' => $matricula,
                    'nombre' => $egresado->nombre_completo,
                    'estado' => 'ACEPTADA',
                    'id_egresado' => $egresado->id,
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            Log::error("Error importando fila #{$numeroFila}: ".$e->getMessage());

            return $this->registrarFilaRechazada(
                $importacionId,
                $numeroFila,
                $data,
                'Error en base de datos: '.$e->getMessage()
            );
        }
    }

    protected function registrarFilaRechazada(
        int $importacionId,
        int $numeroFila,
        array $datos,
        string $error,
        ?int $egresadoId = null
    ): array {
        ImportacionFila::create([
            'id_importacion' => $importacionId,
            'numero_fila' => $numeroFila,
            'datos_entrada' => $datos,
            'estado' => 'RECHAZADA',
            'detalle_error' => $error,
            'id_egresado' => $egresadoId,
        ]);

        return [
            'numero_fila' => $numeroFila,
            'matricula' => $datos['matricula'] ?? $datos['codigo'] ?? 'N/A',
            'nombre' => $datos['nombres'] ?? $datos['nombre'] ?? 'N/A',
            'estado' => 'RECHAZADA',
            'id_egresado' => $egresadoId,
            'error' => $error,
        ];
    }

    /**
     * Parsea un texto CSV detectando automáticamente el delimitador (, ; o \t)
     */
    public function parsearFilas(string $contenido): array
    {
        // Limpiar BOM UTF-8 si existe
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if (empty($lineas)) {
            return [];
        }

        $primeraLinea = $lineas[0];
        $delimitador = ',';
        if (substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',')) {
            $delimitador = ';';
        } elseif (substr_count($primeraLinea, "\t") > substr_count($primeraLinea, ',')) {
            $delimitador = "\t";
        }

        $cabeceras = str_getcsv($lineas[0], $delimitador);
        $cabeceras = array_map('trim', $cabeceras);

        $filas = [];
        for ($i = 1; $i < count($lineas); $i++) {
            $linea = trim($lineas[$i]);
            if (empty($linea)) {
                continue;
            }

            $valores = str_getcsv($linea, $delimitador);
            $filaAsoc = [];
            foreach ($cabeceras as $idx => $col) {
                $filaAsoc[$col] = $valores[$idx] ?? '';
            }

            $filas[] = $filaAsoc;
        }

        return $filas;
    }

    /**
     * Parsea un archivo de libro de cálculo Microsoft Excel (.xlsx) de manera nativa sin librerías externas.
     * Utiliza ZipArchive y SimpleXML para máxima compatibilidad institucional.
     */
    public function parsearXlsx(string $ruta): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($ruta) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo XLSX como contenedor ZIP.');
        }

        // 1. Cargar tabla de cadenas compartidas (sharedStrings.xml)
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $sstXml = $zip->getFromName('xl/sharedStrings.xml');
            $sstObj = @simplexml_load_string($sstXml);
            if ($sstObj && isset($sstObj->si)) {
                foreach ($sstObj->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Identificar hoja de trabajo principal
        $sheetName = 'xl/worksheets/sheet1.xml';
        if ($zip->locateName($sheetName) === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                    $sheetName = $name;
                    break;
                }
            }
        }

        if ($zip->locateName($sheetName) === false) {
            $zip->close();
            throw new \RuntimeException('No se encontró ninguna hoja de cálculo válida en el archivo XLSX.');
        }

        $sheetXml = $zip->getFromName($sheetName);
        $zip->close();

        $sheetObj = @simplexml_load_string($sheetXml);
        if (! $sheetObj || ! isset($sheetObj->sheetData->row)) {
            return [];
        }

        // 3. Extraer celdas y mapear columnas
        $filasMatriz = [];
        foreach ($sheetObj->sheetData->row as $row) {
            $filaArr = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/^([A-Z]+)/i', $ref, $matches);
                $colLetters = $matches[1] ?? 'A';
                $colIdx = $this->columnaAIndice($colLetters);

                $type = (string) $c['t'];
                if ($type === 's') {
                    $sIdx = (int) $c->v;
                    $val = $sharedStrings[$sIdx] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                } else {
                    $val = (string) $c->v;
                }

                $filaArr[$colIdx] = trim($val);
            }

            if (! empty($filaArr)) {
                $maxIdx = max(array_keys($filaArr));
                $filaCompleta = [];
                for ($k = 0; $k <= $maxIdx; $k++) {
                    $filaCompleta[$k] = $filaArr[$k] ?? '';
                }
                $filasMatriz[] = $filaCompleta;
            }
        }

        if (empty($filasMatriz)) {
            return [];
        }

        $cabeceras = array_map('trim', $filasMatriz[0]);
        $resultado = [];

        for ($i = 1; $i < count($filasMatriz); $i++) {
            $fila = $filasMatriz[$i];
            if (empty(array_filter($fila, fn ($v) => $v !== ''))) {
                continue;
            }

            $filaAsoc = [];
            foreach ($cabeceras as $idx => $colName) {
                if ($colName !== '') {
                    $filaAsoc[$colName] = $fila[$idx] ?? '';
                }
            }
            $resultado[] = $filaAsoc;
        }

        return $resultado;
    }

    /**
     * Convierte una referencia de columna alfabética (A, B, ..., Z, AA, AB) a índice base 0.
     */
    protected function columnaAIndice(string $col): int
    {
        $col = strtoupper($col);
        $len = strlen($col);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($col[$i]) - 64);
        }

        return $num - 1;
    }
}
