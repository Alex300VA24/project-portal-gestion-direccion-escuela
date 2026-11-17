<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha de Derivación #{{ $derivacion->id_derivacion }} · UNT</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #111;
            line-height: 1.5;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        .no-print {
            background: #f1f5f9;
            padding: 12px;
            margin-bottom: 24px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-print {
            background: #1e293b;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 13pt;
            margin: 4px 0 0 0;
            font-weight: normal;
        }
        .header h3 {
            font-size: 11pt;
            margin: 4px 0 0 0;
            font-style: italic;
        }
        .doc-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 24px 0;
            letter-spacing: 0.5px;
        }
        .section-box {
            border: 1px solid #333;
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 4px;
        }
        .section-title {
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .data-row {
            margin-bottom: 6px;
            font-size: 11pt;
        }
        .data-label {
            font-weight: bold;
        }
        .consent-box {
            background-color: #fafafa;
            border: 1px dashed #666;
            padding: 12px;
            font-size: 10pt;
            text-align: justify;
            margin-top: 20px;
        }
        .signatures {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            text-align: center;
            page-break-inside: avoid;
        }
        .signature-col {
            width: 45%;
            border-top: 1px solid #111;
            padding-top: 8px;
            font-size: 10pt;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <span>Ficha oficial de derivación para impresión o exportación.</span>
        <div>
            <button onclick="window.print()" class="btn-print">Imprimir / Guardar en PDF</button>
        </div>
    </div>

    <div class="header">
        <h1>Universidad Nacional de Trujillo</h1>
        <h2>Facultad de Ciencias Físicas y Matemáticas · Escuela de Informática</h2>
        <h3>Dirección de Bienestar Universitario · Programa de Acompañamiento Estudiantil</h3>
    </div>

    <div class="doc-title">
        FICHA DE DERIVACIÓN INTERDISCIPLINARIA N° {{ str_pad($derivacion->id_derivacion, 5, '0', STR_PAD_LEFT) }}-2026-DBU
    </div>

    <div class="section-box">
        <div class="section-title">1. Datos del Estudiante</div>
        <div class="grid-2">
            <div class="data-row">
                <span class="data-label">Apellidos y Nombres:</span>
                {{ $derivacion->caso?->estudiante?->persona?->apellido_paterno }} {{ $derivacion->caso?->estudiante?->persona?->apellido_materno }}, {{ $derivacion->caso?->estudiante?->persona?->nombres }}
            </div>
            <div class="data-row">
                <span class="data-label">Código Universitario:</span>
                {{ $derivacion->caso?->estudiante?->codigo_universitario }}
            </div>
            <div class="data-row">
                <span class="data-label">Documento de Identidad:</span>
                {{ $derivacion->caso?->estudiante?->persona?->tipo_documento }}: {{ $derivacion->caso?->estudiante?->persona?->numero_documento }}
            </div>
            <div class="data-row">
                <span class="data-label">Escuela Profesional:</span>
                Informática
            </div>
        </div>
    </div>

    <div class="section-box">
        <div class="section-title">2. Destino de la Derivación</div>
        <div class="data-row">
            <span class="data-label">Especialista / Servicio Destino:</span>
            {{ $derivacion->destino_texto }}
        </div>
        <div class="data-row">
            <span class="data-label">Fecha de Emisión:</span>
            {{ $derivacion->derivado_en?->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="section-box">
        <div class="section-title">3. Motivo y Diagnóstico de Derivación</div>
        <p style="font-size: 11pt; text-align: justify; margin: 4px 0;">
            {{ $derivacion->motivo }}
        </p>
    </div>

    <div class="consent-box">
        <strong>Cláusula de Consentimiento Informado:</strong><br>
        En cumplimiento de la Ley de Protección de Datos Personales (Ley N° 29733) y la normativa universitaria de permanencia, declaro haber sido informado(a) con claridad sobre los motivos y alcances de la presente derivación al especialista. Brindo de manera libre, previa y voluntaria mi consentimiento expreso para la interconsulta y seguimiento interdisciplinario de mi situación académica y de bienestar.
        <br><br>
        <strong>Fecha de conformidad:</strong> {{ $derivacion->fecha_consentimiento?->format('d/m/Y') ?? date('d/m/Y') }}
    </div>

    <div class="signatures">
        <div class="signature-col">
            <strong>Firma del Estudiante</strong><br>
            Cód. {{ $derivacion->caso?->estudiante?->codigo_universitario }}<br>
            DNI: {{ $derivacion->caso?->estudiante?->persona?->numero_documento }}
        </div>

        <div class="signature-col">
            <strong>Responsable de Bienestar Universitario</strong><br>
            Escuela Profesional de Informática<br>
            Universidad Nacional de Trujillo
        </div>
    </div>
</body>
</html>
