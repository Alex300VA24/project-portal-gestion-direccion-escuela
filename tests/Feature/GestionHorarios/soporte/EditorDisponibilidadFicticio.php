<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Arnés Livewire sin ruta: únicamente para la base ficticia de la suite. */
final class EditorDisponibilidadFicticio extends Component
{
    public int $periodo = 1;

    public ?int $docente = null;

    public int $version = 0;

    public string $preferencia = 'SIN_PREFERENCIA';

    public array $seleccion = [];

    public string $mensaje = '';

    public function alternar(string $dia, int $bloque): void
    {
        $existe = false;
        $nueva = [];
        foreach ($this->seleccion as $item) {
            if (($item['dia'] ?? null) === $dia && ($item['bloque'] ?? null) === $bloque) {
                $existe = true;
            } else {
                $nueva[] = $item;
            }
        }
        if (! $existe) {
            $nueva[] = compact('dia', 'bloque');
        }
        $this->seleccion = $nueva;
    }

    public function seleccionarJornada(string $dia): void
    {
        $config = DB::table('prueba.disp_periodo')->where('periodo', $this->periodo)->first();
        if (! $config || ! in_array($dia, json_decode($config->dias, true, 512, JSON_THROW_ON_ERROR), true)) {
            return;
        }
        $bloques = DB::table('gestion_horarios.bloque_horario as b')->join('prueba.disp_jornada as j', function ($join) use ($dia) {
            $join->on('j.periodo', '=', 'b.id_periodo')->on('j.bloque', '=', 'b.id_bloque')->where('j.dia', '=', $dia);
        })->where('b.id_periodo', $this->periodo)->where('b.hora_inicio', '>=', $config->inicio)->where('b.hora_fin', '<=', $config->fin)->select('b.*')->get();
        $this->seleccion = array_values(array_filter($this->seleccion, fn ($f) => $f['dia'] !== $dia));
        foreach ($bloques as $bloque) {
            if ($bloque->hora_inicio < $config->receso_fin && $bloque->hora_fin > $config->receso_inicio) {
                continue;
            }
            $this->seleccion[] = ['dia' => $dia, 'bloque' => (int) $bloque->id_bloque];
        }
    }

    public function guardar(): void
    {
        $this->persistir();
    }

    public function cancelar(): void
    {
        $this->seleccion = [];
        $this->mensaje = 'Cambios descartados';
    }

    private function persistir(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);
        $servicio = new GestionDisponibilidadFicticia(DB::connection(), app(ProveedorIdentidadDocente::class));
        $resultado = $servicio->guardar($usuario, $this->periodo, $this->docente, $this->version, $this->preferencia, $this->seleccion);
        $this->version = $resultado['version'];
        $this->mensaje = 'Declaración guardada';
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <p role="status">{{ $mensaje }}</p>
            <label>Turno<select wire:model="preferencia"><option value="SIN_PREFERENCIA">Sin preferencia</option><option value="MANANA">Mañana</option><option value="TARDE">Tarde</option></select></label>
            <table><caption>Disponibilidad semanal de prueba</caption><thead><tr><th>Bloque</th>@foreach (['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'] as $dia)<th>{{ $dia }} <button type="button" wire:click="seleccionarJornada('{{ $dia }}')">Jornada</button></th>@endforeach</tr></thead><tbody>
            @foreach (\Illuminate\Support\Facades\DB::table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('hora_inicio')->get() as $bloque)
                <tr><th>{{ $bloque->hora_inicio }}–{{ $bloque->hora_fin }}</th>@foreach (['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'] as $dia)<td><button type="button" wire:click="alternar('{{ $dia }}', {{ $bloque->id_bloque }})" aria-label="{{ $dia }} {{ $bloque->hora_inicio }}">{{ in_array(['dia' => $dia, 'bloque' => (int) $bloque->id_bloque], $seleccion, true) ? 'Seleccionado' : 'Sin seleccionar' }}</button></td>@endforeach</tr>
            @endforeach
            </tbody></table>
            @error('version') <p role="alert">{{ $message }}</p> @enderror
            @error('periodo') <p role="alert">{{ $message }}</p> @enderror
            @error('franjas') <p role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="guardar">Guardar</button>
            <button type="button" wire:click="cancelar">Cancelar</button>
        </div>
        BLADE;
    }
}
