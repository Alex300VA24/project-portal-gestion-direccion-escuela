<?php

namespace App\Modules\PreMatricula\Services;

use DomainException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

/**
 * Genera la constancia de prematrícula (A4, una sola página) a partir del snapshot de un envío.
 * Usa el mismo formato que la constancia de la rama master (PreEnrollmentReceipt).
 */
final class ConstanciaPdf
{
    public const UNIVERSIDAD = 'Universidad Nacional de Trujillo';

    public const OFICINA = 'OFICINA DE SISTEMAS';

    public const FACULTAD = 'CIENCIAS FISICAS Y MATEMATICAS';

    public const ESCUELA = 'INFORMATICA';

    public const SEDE = 'TRUJILLO';

    private const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** @var list<array<string, string>> */
    private const TAMANOS = [
        ['fuente' => '8pt', 'relleno' => '3pt 4pt', 'celda' => '2.5pt 4pt', 'datos' => '7pt', 'fotoAlto' => '68pt', 'fotoAncho' => '58pt', 'firmas' => '42pt', 'pie' => '38pt'],
        ['fuente' => '6.5pt', 'relleno' => '1.5pt 2.5pt', 'celda' => '1.5pt 2.5pt', 'datos' => '5pt', 'fotoAlto' => '68pt', 'fotoAncho' => '58pt', 'firmas' => '14pt', 'pie' => '10pt'],
        ['fuente' => '5.5pt', 'relleno' => '0.8pt 2pt', 'celda' => '0.8pt 2pt', 'datos' => '3pt', 'fotoAlto' => '52pt', 'fotoAncho' => '44pt', 'firmas' => '4pt', 'pie' => '4pt'],
        ['fuente' => '4.5pt', 'relleno' => '0.4pt 1pt', 'celda' => '0.4pt 1pt', 'datos' => '2pt', 'fotoAlto' => '42pt', 'fotoAncho' => '36pt', 'firmas' => '0pt', 'pie' => '2pt'],
        ['fuente' => '3.5pt', 'relleno' => '0.2pt 1pt', 'celda' => '0.2pt 1pt', 'datos' => '2pt', 'fotoAlto' => '42pt', 'fotoAncho' => '36pt', 'firmas' => '0pt', 'pie' => '2pt'],
        ['fuente' => '2.8pt', 'relleno' => '0pt 1pt', 'celda' => '0pt 1pt', 'datos' => '1pt', 'fotoAlto' => '36pt', 'fotoAncho' => '30pt', 'firmas' => '0pt', 'pie' => '0pt'],
    ];

    /** @param array<string, mixed> $snapshot */
    public function generar(array $snapshot): string
    {
        $cursos = count($snapshot['detalles'] ?? []);

        // Si con las medidas que tocan no entra en una página, se prueban las más compactas.
        foreach (array_slice(self::TAMANOS, $this->nivel($cursos)) as $tam) {
            $pdf = $this->dompdf($this->html($snapshot, $tam));
            if ($pdf->getCanvas()->get_page_count() === 1) {
                return (string) $pdf->output();
            }
        }

        throw new DomainException('La constancia debe tener exactamente una página.');
    }

    private function dompdf(string $html): Dompdf
    {
        $pdf = new Dompdf(new Options([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'fontCache' => storage_path('framework/cache'),
        ]));
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, string>|null  $tam
     */
    public function html(array $snapshot, ?array $tam = null): string
    {
        $estudiante = $snapshot['estudiante'] ?? [];
        $apellidos = mb_strtoupper((string) ($estudiante['apellidos'] ?? ''));
        $nombres = mb_strtoupper((string) ($estudiante['nombres'] ?? ''));
        $fecha = Carbon::parse($snapshot['enviada_en'])->timezone($snapshot['zona_horaria'] ?? VentanaPrematricula::ZONA_HORARIA);
        $escuela = self::ESCUELA;

        return view('modules.pre-matricula.pdf.constancia', [
            'snapshot' => $snapshot,
            'universidad' => self::UNIVERSIDAD,
            'oficina' => self::OFICINA,
            'codigo' => (string) ($estudiante['codigo_universitario'] ?? ''),
            'apellidos' => $apellidos,
            'nombres' => $nombres,
            'nombreCompleto' => trim($apellidos.' '.$nombres),
            'facultad' => self::FACULTAD,
            'escuela' => $escuela,
            'directorEscuela' => stripos($escuela, 'ESCUELA') === false ? 'ESCUELA '.$escuela : $escuela,
            'sede' => self::SEDE,
            'periodo' => (string) ($snapshot['periodo']['codigo'] ?? ''),
            'fechaHoraEmision' => $fecha->format('Y-m-d H:i:s'),
            'fechaPie' => sprintf('%s, %s %d de %s del %d', self::SEDE, self::DIAS[$fecha->dayOfWeekIso], $fecha->day, self::MESES[$fecha->month], $fecha->year),
            'detalles' => $snapshot['detalles'] ?? [],
            'creditosTotales' => $snapshot['creditos_totales'] ?? 0,
            'tam' => $tam ?? self::TAMANOS[$this->nivel(count($snapshot['detalles'] ?? []))],
            'escudo' => $this->imagen('unt_escudo.png'),
            'foto' => $this->imagen('avatar_placeholder.png'),
            'qr' => $this->imagen('qr_default.png'),
        ])->render();
    }

    /** Medidas por nivel; hasta 10 cursos (el caso normal) son exactamente las de master. */
    private function nivel(int $cursos): int
    {
        return match (true) {
            $cursos > 40 => 3,
            $cursos > 20 => 2,
            $cursos > 10 => 1,
            default => 0,
        };
    }

    private function imagen(string $archivo): string
    {
        $ruta = public_path('img/pre-matricula/'.$archivo);
        $datos = is_file($ruta) ? file_get_contents($ruta) : false;

        return $datos === false ? '' : 'data:image/png;base64,'.base64_encode($datos);
    }
}
