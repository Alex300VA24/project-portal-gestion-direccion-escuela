<?php

namespace App\Modules\SeguimientoEgresado\Services\Reportes;

/**
 * Una hoja de un libro de Excel: filas de celdas de texto con su estilo (constantes de LibroExcel), anchos y celdas combinadas.
 * Una celda es [texto, estilo]; null deja la celda vacía.
 */
final class HojaExcel
{
    /** @var list<array{0: list<array{0: string, 1: int}|null>, 1: ?float}> */
    private array $filas = [];

    /** @var list<string> */
    private array $combinadas = [];

    /** @param  list<float|int>  $anchos */
    public function __construct(public readonly string $nombre, public readonly array $anchos) {}

    /**
     * @param  list<array{0: string, 1: int}|null>  $celdas
     */
    public function fila(array $celdas, ?float $alto = null): self
    {
        $this->filas[] = [$celdas, $alto];

        return $this;
    }

    public function vacia(): self
    {
        return $this->fila([]);
    }

    /** Una fila con un solo texto que ocupa todas las columnas. */
    public function filaUnida(string $texto, int $estilo, ?float $alto = null): self
    {
        $this->fila([[$texto, $estilo]], $alto);
        $n = count($this->filas);
        $this->combinadas[] = 'A'.$n.':'.LibroExcel::letra(count($this->anchos) - 1).$n;

        return $this;
    }

    /** @return list<array{0: list<array{0: string, 1: int}|null>, 1: ?float}> */
    public function filas(): array
    {
        return $this->filas;
    }

    /** @return list<string> */
    public function combinadas(): array
    {
        return $this->combinadas;
    }
}
