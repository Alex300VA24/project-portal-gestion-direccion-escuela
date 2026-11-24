@if ($alertas)
    <section class="space-y-2 rounded-lg bg-white p-5" aria-labelledby="alertas-expediente">
        <h2 id="alertas-expediente" class="font-semibold text-gray-900">Alertas registradas</h2>
        <ul class="space-y-2">
            @forelse ($alertas as $alerta)
                <li>
                    <span class="font-medium">{{ $alerta->codigo }}</span> · {{ $alerta->estado }} · {{ $alerta->periodo }}
                    <p class="text-sm text-gray-700">
                        {{ \Illuminate\Support\Carbon::parse($alerta->generada_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                        · Valor detectado {{ $alerta->valor_detectado ?? 'sin dato' }}
                    </p>
                </li>
            @empty
                <li>Sin alertas registradas en los períodos bajo tu asignación vigente.</li>
            @endforelse
        </ul>
        {{ $alertas->links() }}
    </section>
@endif

@if ($situaciones)
    <section class="space-y-2 rounded-lg bg-white p-5" aria-labelledby="situacion-expediente">
        <h2 id="situacion-expediente" class="font-semibold text-gray-900">Situación académica registrada</h2>
        <ul class="space-y-2">
            @forelse ($situaciones as $situacion)
                <li>
                    <span class="font-medium">{{ $situacion->periodo }}</span> · {{ $situacion->condicion ?? 'Condición sin dato' }}
                    <p class="text-sm text-gray-700">
                        {{ \Illuminate\Support\Carbon::parse($situacion->calculado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                        · Promedio ponderado {{ $situacion->promedio_ponderado ?? 'sin dato' }}
                    </p>
                </li>
            @empty
                <li>Sin registros de situación académica en los períodos bajo tu asignación vigente.</li>
            @endforelse
        </ul>
        {{ $situaciones->links() }}
    </section>
@endif
