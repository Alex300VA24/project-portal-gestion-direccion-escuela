<table>
    <thead>
        <tr>
            <th style="width: 18%">Fecha</th>
            @unless ($personal)<th style="width: 14%">Grupo</th>@endunless
            <th>Actividad</th>
            <th style="width: 24%">Ambiente</th>
            @if ($personal)<th style="width: 18%">Asistencia</th>@endif
        </tr>
    </thead>
    <tbody>
    @forelse ($sesiones as $sesion)
        <tr>
            <td>{{ $fechaCalendario($sesion->fecha) }}</td>
            @unless ($personal)<td>{{ $sesion->grupo }}</td>@endunless
            <td>{{ $sesion->tema ?? 'Sin tema registrado' }}</td>
            <td>{{ $sesion->ambiente ?? 'Sin ambiente registrado' }}</td>
            @if ($personal)<td>{{ $sesion->asistencia ?? 'Sin registro' }}</td>@endif
        </tr>
    @empty
        <tr><td colspan="4">No hay sesiones registradas.</td></tr>
    @endforelse
    </tbody>
</table>
