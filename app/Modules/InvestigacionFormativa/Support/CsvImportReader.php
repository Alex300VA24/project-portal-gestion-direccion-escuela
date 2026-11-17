<?php

namespace App\Modules\InvestigacionFormativa\Support;

use RuntimeException;

final class CsvImportReader
{
    /**
     * @param  list<string>  $requiredHeaders
     * @return array{
     *     headers: list<string>,
     *     rows: list<array{line: int, data: array<string, string>}>
     * }
     */
    public function read(string $path, array $requiredHeaders): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("No se puede leer el archivo CSV: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("No se puede abrir el archivo CSV: {$path}");
        }

        try {
            $headers = $this->readHeaders($handle, $path);
            $this->assertRequiredHeaders($headers, $requiredHeaders, $path);

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new RuntimeException(
                        "La fila {$line} tiene ".count($values).' columnas; se esperaban '.count($headers).'.',
                    );
                }

                $data = [];

                foreach ($headers as $index => $header) {
                    $data[$header] = trim((string) $values[$index]);
                }

                $rows[] = ['line' => $line, 'data' => $data];
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return list<string>
     */
    private function readHeaders($handle, string $path): array
    {
        $raw = fgetcsv($handle, 0, ',', '"', '');

        if ($raw === false) {
            throw new RuntimeException("El archivo CSV está vacío: {$path}");
        }

        $headers = array_map(static function (string $header): string {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $raw);

        if (in_array('', $headers, true)) {
            throw new RuntimeException('El encabezado del CSV contiene una columna sin nombre.');
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw new RuntimeException('El encabezado del CSV contiene columnas duplicadas.');
        }

        return $headers;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $requiredHeaders
     */
    private function assertRequiredHeaders(array $headers, array $requiredHeaders, string $path): void
    {
        $missing = array_values(array_diff($requiredHeaders, $headers));

        if ($missing !== []) {
            throw new RuntimeException(
                "Faltan columnas obligatorias en {$path}: ".implode(', ', $missing).'.',
            );
        }
    }

    /**
     * @param  list<string|null>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
