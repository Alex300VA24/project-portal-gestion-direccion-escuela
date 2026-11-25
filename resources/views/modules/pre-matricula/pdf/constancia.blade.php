{{-- Constancia de prematrícula con el formato de la rama master (PreEnrollmentReceipt). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de Prematrícula {{ $snapshot['codigo_constancia'] ?? '' }}</title>
    <style>
        @page {
            margin: 18pt 30pt 16pt 30pt;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            color: #111111;
            line-height: 1.25;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 130pt;
            left: 0;
            width: 100%;
            text-align: center;
            z-index: 0;
            pointer-events: none;
        }
        .watermark img {
            width: 360pt;
            height: auto;
            opacity: 0.22;
            margin: 0 auto;
            display: inline-block;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3pt;
        }
        .header-left {
            vertical-align: middle;
            text-align: left;
        }
        .header-university {
            font-size: 11pt;
            font-weight: bold;
            color: #000000;
            margin-bottom: 2pt;
        }
        .header-office {
            font-size: 9pt;
            color: #222222;
        }
        .header-right {
            width: 75pt;
            text-align: center;
            vertical-align: top;
        }
        .photo-box {
            display: inline-block;
            width: {{ $tam['fotoAncho'] }};
            height: {{ $tam['fotoAlto'] }};
            border: 1px solid #666666;
            text-align: center;
            overflow: hidden;
            background-color: #f2f2f2;
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .student-code-header {
            font-size: 7pt;
            font-weight: bold;
            margin-top: 2pt;
            color: #111111;
        }
        .header-line {
            border-bottom: 1.5pt solid #000000;
            margin-top: 2pt;
            margin-bottom: 7pt;
        }
        .doc-title {
            font-size: 12.5pt;
            font-weight: bold;
            text-align: center;
            margin-top: 3pt;
            margin-bottom: 6pt;
            letter-spacing: 0.3pt;
        }
        .statement-p {
            font-size: 8.5pt;
            text-align: justify;
            margin-top: 0;
            margin-bottom: 4pt;
            line-height: 1.25;
        }
        .statement-valid {
            font-size: 8.5pt;
            text-align: justify;
            margin-top: 0;
            margin-bottom: 6pt;
            line-height: 1.25;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: {{ $tam['datos'] }};
        }
        .details-table td {
            padding: 1.2pt 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .details-table .lbl {
            font-weight: bold;
            width: 170pt;
            color: #000000;
        }
        .details-table .val {
            font-weight: bold;
            color: #111111;
        }
        .courses-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3pt;
            margin-bottom: 4pt;
        }
        .courses-table th {
            border-top: 1.5pt solid #333333;
            border-bottom: 1.5pt solid #333333;
            font-weight: bold;
            text-align: left;
            padding: 3pt 4pt;
        }
        .courses-table td {
            border-bottom: 0.5pt solid #cccccc;
            vertical-align: middle;
            padding: {{ $tam['celda'] }};
        }
        .courses-table .col-code {
            width: 75pt;
            font-weight: bold;
        }
        .courses-table .col-name {
            
        }
        .courses-table .col-credits {
            width: 55pt;
            text-align: center;
        }
        .courses-table .total-row td {
            border-top: 1.5pt solid #333333;
            border-bottom: 1.5pt solid #333333;
            background-color: transparent;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: {{ $tam['firmas'] }};
            margin-bottom: 4pt;
        }
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 15pt;
        }
        .sig-space {
            height: 38pt;
        }
        .sig-line {
            border-top: 1.2pt solid #000000;
            margin: 0 auto 5pt auto;
            width: 185pt;
        }
        .sig-role {
            font-size: 7.5pt;
            font-weight: bold;
            line-height: 1.3;
        }
        .sig-name {
            font-size: 7.5pt;
            font-weight: bold;
            line-height: 1.3;
        }
        .sig-sub {
            font-size: 7.5pt;
            color: #222222;
        }
        .bottom-section {
            margin-top: {{ $tam['pie'] }};
        }
        .date-line {
            text-align: right;
            font-size: 8pt;
            margin-top: 0;
            margin-bottom: 6pt;
            color: #111111;
        }
        .instructions-box {
            border: 1px solid #222222;
            padding: 4pt 6pt;
            margin-top: 0;
            margin-bottom: 5pt;
            background-color: #ffffff;
        }
        .instructions-table {
            width: 100%;
            border-collapse: collapse;
        }
        .instructions-table .qr-col {
            width: 70pt;
            text-align: center;
            vertical-align: middle;
            padding-right: 5pt;
            border-right: 1px solid #cccccc;
        }
        .instructions-table .qr-col img {
            width: 60pt;
            height: 60pt;
            display: block;
            margin: 0 auto;
        }
        .instructions-table .text-col {
            padding-left: 7pt;
            vertical-align: middle;
            font-size: 6.8pt;
            line-height: 1.3;
        }
        .instructions-list {
            margin: 0;
            padding-left: 10pt;
        }
        .instructions-list li {
            margin-bottom: 1.5pt;
        }
        .footer-line {
            border-bottom: 1px solid #555555;
            margin-top: 3pt;
            margin-bottom: 2pt;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            color: #222222;
        }
        .footer-left {
            text-align: left;
            width: 33%;
        }
        .footer-center {
            text-align: center;
            width: 33%;
        }
        .footer-right {
            text-align: right;
            width: 34%;
        }
    </style>
</head>
<body>
    <div style="position: relative;">
    @if ($escudo)<div class="watermark"><img src="{{ $escudo }}" alt="Escudo UNT" /></div>@endif

    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="header-university">{{ $universidad }}</div>
                <div class="header-office">{{ $oficina }}</div>
            </td>
            <td class="header-right">
                <div class="photo-box">
                    @if ($foto)<img src="{{ $foto }}" alt="Foto estudiante" />@else<div style="width:100%;height:100%;background:#e5e5e5;"></div>@endif
                </div>
                <div class="student-code-header">Código: {{ $codigo }}</div>
            </td>
        </tr>
    </table>

    <div class="header-line"></div>

    <div class="doc-title">CONSTANCIA DE PREMATRÍCULA</div>

    <p class="statement-p">
        El presente documento da constancia de que, el estudiante en mención ha realizado y finalizado correctamente con el registro de su <strong>PREMATRÍCULA - {{ $periodo }}</strong>
    </p>
    <p class="statement-valid">
        Siendo válido y parte de los requisitos para la matrícula en el periodo correspondiente.
    </p>

    <table class="details-table">
        <tr>
            <td class="lbl">CÓDIGO:</td>
            <td class="val">{{ $codigo }}</td>
        </tr>
        <tr>
            <td class="lbl">APELLIDOS:</td>
            <td class="val">{{ $apellidos }}</td>
        </tr>
        <tr>
            <td class="lbl">NOMBRES:</td>
            <td class="val">{{ $nombres }}</td>
        </tr>
        <tr>
            <td class="lbl">FACULTAD:</td>
            <td class="val">{{ $facultad }}</td>
        </tr>
        <tr>
            <td class="lbl">ESCUELA:</td>
            <td class="val">{{ $escuela }}</td>
        </tr>
        <tr>
            <td class="lbl">SEDE:</td>
            <td class="val">{{ $sede }}</td>
        </tr>
        <tr>
            <td class="lbl">PERIODO:</td>
            <td class="val">{{ $periodo }}</td>
        </tr>
        <tr>
            <td class="lbl">FECHA Y HORA DE EMISIÓN:</td>
            <td class="val">{{ $fechaHoraEmision }}</td>
        </tr>
    </table>

    @if (count($detalles) > 0)
    <table class="courses-table" style="font-size: {{ $tam['fuente'] }};">
        <thead>
            <tr>
                <th class="col-code" style="padding: {{ $tam['relleno'] }};">CÓDIGO</th>
                <th class="col-name" style="padding: {{ $tam['relleno'] }};">CURSO / ASIGNATURA</th>
                <th class="col-credits" style="padding: {{ $tam['relleno'] }};">CRÉDITOS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detalles as $d)
                <tr><td class="col-code">{{ $d['codigo'] ?? '' }}</td><td class="col-name">{{ $d['nombre'] ?? '' }}</td><td class="col-credits">{{ $d['creditos'] ?? '' }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" style="text-align: right; padding: {{ $tam['relleno'] }}; font-weight: bold;">Créditos totales:</td>
                <td class="col-credits" style="padding: {{ $tam['relleno'] }}; font-weight: bold;">{{ $creditosTotales }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-role">DIRECTOR</div>
                <div class="sig-role">{{ $directorEscuela }}</div>
            </td>
            <td>
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $nombreCompleto }}</div>
                <div class="sig-sub">{{ $codigo }}</div>
            </td>
        </tr>
    </table>

    <div class="bottom-section">
        <div class="date-line">{{ $fechaPie }}</div>

        <div class="instructions-box">
            <table class="instructions-table">
                <tr>
                    <td class="qr-col">
                        @if ($qr)<img src="{{ $qr }}" alt="Código QR de validación" />@endif
                    </td>
                    <td class="text-col">
                        <ul class="instructions-list">
                            <li>El presente documento es parte de los requisitos para matricularse en el periodo correspondiente.</li>
                            <li>Imprimir, firmar la CONSTANCIA y presentarlo con su secretaria de escuela para su validación.</li>
                            <li>Se recomienda que la entrega del documento sea de forma presencial en OFICINA DE SU ESCUELA y se realice dentro las fechas establecidas para evitar inconvenientes.</li>
                            <li>Esta constancia no constituye matrícula definitiva.</li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-line"></div>

        <table class="footer-table">
            <tr>
                <td class="footer-left">🌐 https://unitru.edu.pe/</td>
                <td class="footer-center">📞 44209020</td>
                <td class="footer-right">📍 AV. JUAN PABLO II S/N URB. SAN ANDRES</td>
            </tr>
        </table>
    </div>
    </div>
</body>
</html>
