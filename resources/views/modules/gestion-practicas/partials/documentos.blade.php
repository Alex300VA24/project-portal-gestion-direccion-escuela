{{-- Parámetros: $documentos (colección), $puedeRevisar (bool) --}}
@php($tipos = \App\Modules\GestionPracticas\Support\Catalogos::TIPOS_DOCUMENTO)
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="text-left text-gray-500">
            <tr>
                <th class="py-2 pe-4 font-medium">Documento</th>
                <th class="py-2 pe-4 font-medium">Archivo</th>
                <th class="py-2 pe-4 font-medium">Subido</th>
                <th class="py-2 pe-4 font-medium">Estado</th>
                <th class="py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($documentos as $documento)
                <tr>
                    <td class="py-2 pe-4">{{ $tipos[$documento->tipo_documento] ?? $documento->tipo_documento }}</td>
                    <td class="py-2 pe-4">
                        <a class="text-emerald-700 hover:underline" href="{{ route('gestion-practicas.documentos.descargar', $documento->id_documento) }}">{{ $documento->nombre_original }}</a>
                    </td>
                    <td class="py-2 pe-4 text-gray-500">{{ \Illuminate\Support\Carbon::parse($documento->subido_en)->format('d/m/Y H:i') }}</td>
                    <td class="py-2 pe-4">@include('modules.gestion-practicas.partials.estado', ['estado' => $documento->estado])</td>
                    <td class="py-2 text-end whitespace-nowrap">
                        @if ($puedeRevisar && $documento->estado !== 'reemplazado')
                            <form method="POST" action="{{ route('gestion-practicas.documentos.revisar', $documento->id_documento) }}" class="inline">
                                @csrf
                                <button name="estado" value="aprobado" class="text-emerald-700 hover:underline">Aprobar</button>
                                <button name="estado" value="observado" class="ms-3 text-red-700 hover:underline">Observar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-gray-500">Aún no hay documentos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
