<?php

namespace App\Modules\SeguimientoEstudiante\Services\Secretaria;

use Illuminate\Validation\ValidationException;

/** Lee datos, nunca fórmulas. Conserva identificadores y ceros iniciales como texto. */
final class CsvReader
{
    public function read(string $bytes): array
    {
        if (strlen($bytes) > 2097152 || str_contains($bytes, "\0")) {
            $this->fail('Archivo demasiado grande o codificación no compatible. Usa UTF-8 o Windows-1252 y un máximo de 2 MB.');
        }
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        $first = fgets($stream);
        if ($first === false) {
            fclose($stream);
            $this->fail('El archivo está vacío.');
        }
        $delimiter = ',';
        $columns = 0;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = count(str_getcsv($first, $candidate, '"', ''));
            if ($count > $columns) {
                $delimiter = $candidate;
                $columns = $count;
            }
        }
        rewind($stream);
        $headers = array_map(fn ($h) => trim((string) $h), fgetcsv($stream, null, $delimiter, '"', '') ?: []);
        if (! $headers || in_array('', $headers, true) || count($headers) !== count(array_unique($headers)) || count($headers) > 80 || array_filter($headers, fn ($header) => mb_strlen($header) > 250)) {
            fclose($stream);
            $this->fail('La primera fila debe tener encabezados únicos, no vacíos y de hasta 250 caracteres; como máximo 80 columnas.');
        }
        $rows = [];
        $line = 1;
        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($row === [null] || ! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            if (count($row) !== count($headers)) {
                fclose($stream);
                $this->fail('La fila de datos '.$line.' tiene un número distinto de columnas. Revisa el delimitador y las comillas.');
            }
            if (count($rows) >= 3000) {
                fclose($stream);
                $this->fail('Cada lote admite hasta 3.000 filas. Divide el corte en lotes identificados.');
            }
            $rows[] = ['line' => $line, 'values' => array_map(fn ($v) => trim((string) $v), $row)];
        }
        fclose($stream);
        if (! $rows) {
            $this->fail('No hay filas de datos debajo de los encabezados.');
        }

        return ['headers' => $headers, 'rows' => $rows, 'delimiter' => $delimiter === "\t" ? 'tabulación' : $delimiter];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['archivo' => $message]);
    }
}
