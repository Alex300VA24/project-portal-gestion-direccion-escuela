<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte Institucional de Bienestar Universitario · Periodo {{ $periodo }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            background: #fff;
            margin: 0;
            padding: 20px;
            font-size: 11pt;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #12377B;
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0;
            color: #12377B;
            text-transform: uppercase;
        }
        .header p {
            margin: 3px 0 0 0;
            font-size: 9pt;
            color: #5F6670;
        }
        .meta-box {
            background: #F5F7FA;
            border: 1px solid #E3E6EA;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 9.5pt;
        }
        .grid-kpi {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .kpi-card {
            border: 1px solid #E3E6EA;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .kpi-card .label {
            font-size: 8pt;
            font-weight: bold;
            color: #5F6670;
            text-transform: uppercase;
        }
        .kpi-card .value {
            font-size: 18pt;
            font-weight: 800;
            color: #12377B;
            margin-top: 4px;
        }
        h2 {
            font-size: 12pt;
            color: #12377B;
            border-bottom: 1px solid #E3E6EA;
            padding-bottom: 4px;
            margin-top: 24px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 16px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            font-size: 8pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 8pt;
            color: #5F6670;
            display: flex;
            justify-content: space-between;
        }
        .no-print {
            background: #e2e8f0;
            padding: 10px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .btn-print {
            background: #12377B;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Imprimir / Guardar en PDF</button>
        <span style="margin-left: 10px; font-size: 9pt; color: #475569;">Sugerencia: use la opción "Guardar como PDF" de su navegador.</span>
    </div>

    <div class="header">
        <div>
            <h1>Universidad Nacional de Trujillo</h1>
            <p>Facultad de Ciencias Físicas y Matemáticas · Escuela Profesional de Informática</p>
            <p><strong>Dirección de Bienestar Universitario · Reporte Analítico Oficial</strong></p>
        </div>
        <div style="text-align: right;">
            <p><strong>Periodo Académico:</strong> {{ $periodo }}</p>
            <p>Generado: {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>

    <div class="meta-box">
        <strong>Alcance del Informe:</strong> Resumen consolidado del estado de alertas académicas preventivas, solicitudes de apoyo y atenciones especializadas. Cumple estrictamente con la Ley N° 29733 de Protección de Datos Personales, consolidando información agregada sin publicar nombres ni diagnósticos médicos o socioeconómicos confidenciales.
    </div>

    <!-- Tarjetas KPI -->
    <div class="grid-kpi">
        <div class="kpi-card">
            <div class="label">Total Alertas</div>
            <div class="value">{{ $kpis['total_alertas'] }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Alta Prioridad</div>
            <div class="value">{{ $kpis['alertas_alta'] }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Casos Activos</div>
            <div class="value">{{ $kpis['casos_abiertos'] }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Citas del Periodo</div>
            <div class="value">{{ $kpis['total_citas'] }}</div>
        </div>
    </div>

    <!-- Casos por Estado -->
    <h2>1. Casos Asistenciales por Estado</h2>
    <table>
        <thead>
            <tr><th>Estado del Caso</th><th class="text-right">Cantidad de Casos</th></tr>
        </thead>
        <tbody>
            @forelse ($casosPorEstado as $est => $cant)
                <tr><td>{{ $est }}</td><td class="text-right font-mono">{{ $cant }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center">Sin registros</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- Alertas por Regla Disparadora -->
    <h2>2. Alertas de Riesgo por Regla Disparadora</h2>
    <table>
        <thead>
            <tr><th>Regla Normativa / Causal</th><th class="text-right">Alertas Detectadas</th></tr>
        </thead>
        <tbody>
            @forelse ($alertasPorRegla as $reg => $cant)
                <tr><td>{{ ucwords(str_replace('_', ' ', $reg)) }}</td><td class="text-right font-mono">{{ $cant }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center">Sin registros</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- Solicitudes de Trámites UNT -->
    <h2>3. Solicitudes por Tipo de Trámite y Estado</h2>
    <table>
        <thead>
            <tr>
                <th>Procedimiento / Trámite UNT</th>
                <th class="text-center">Enviadas</th>
                <th class="text-center">Recibidas</th>
                <th class="text-center">Observadas</th>
                <th class="text-center">Remitidas</th>
                <th class="text-center">Aprobadas</th>
                <th class="text-center">Rechazadas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($solicitudesPorTipo as $tipo => $estados)
                <tr>
                    <td><strong>{{ $tipo }}</strong></td>
                    <td class="text-center font-mono">{{ $estados['enviada'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $estados['recibida'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $estados['observada'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $estados['remitida'] ?? 0 }}</td>
                    <td class="text-center font-mono font-bold">{{ $estados['aprobada'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $estados['rechazada'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Sin solicitudes</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach (['4. Alertas por Prioridad' => $alertasPorPrioridad, '5. Alertas por Origen' => $alertasPorOrigen, '6. Citas por Mes' => $citasPorMes, '7. Alertas por Periodo' => $tendenciaPeriodo, '8. Casos por Periodo' => $tendenciaCasos] as $titulo => $filas)
        <h2>{{ $titulo }}</h2>
        <table><tbody>
            @forelse ($filas as $clave => $cant)<tr><td>{{ ucfirst(str_replace('_', ' ', $clave)) }}</td><td class="text-right">{{ $cant }}</td></tr>@empty<tr><td class="text-center">Sin registros</td></tr>@endforelse
        </tbody></table>
    @endforeach

    <div class="footer">
        <span>Portal de Gestión de Dirección de Escuela · UNT Informática</span>
        <span>Página 1 de 1</span>
    </div>
</body>
</html>
