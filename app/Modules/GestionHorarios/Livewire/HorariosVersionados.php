<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ColaGeneracionDemo;
use App\Modules\GestionHorarios\Services\HorariosVersionadosDemo;
use App\Modules\GestionHorarios\Services\HorarioUnicoDemo;
use App\Modules\GestionHorarios\Services\CuadriculaSemanalDemo;
use App\Modules\GestionHorarios\Services\ConsultaSemanalDemo;
use App\Modules\GestionHorarios\Services\EdicionCpsatDemo;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class HorariosVersionados extends Component
{
    public string $nombre = '';
    #[Locked] public string $propuesta = '';
    #[Locked] public int $version = 0;
    public string $actividad = '';
    public string $dia = '';
    public string $inicio = '';
    public string $ambienteEdicion = '';
    public string $ciclo = '';
    public string $seccion = '';
    public string $compararVersion = '';
    #[Locked] public string $vistaHorario = 'ciclo';
    public string $docenteFiltro = '';
    public string $ambienteFiltro = '';
    public string $cursoFiltro = '';
    #[Locked] public int $detalleSesion = 0;
    public string $mensaje = '';
    public string $segundosCpsat = '150';

    public function solicitarCpsat(string $modo): void
    {
        abort_unless((new HorariosVersionadosDemo)->disponible(), 422, 'Registre el horario de trabajo primero.');
        if ($modo==='reorganizar') {
            abort_unless($this->version === (int) DB::table(HorariosVersionadosDemo::TABLA)->where('propuesta',$this->propuesta)->max('version'),422,'Abra la revisión vigente.');
        }
        $this->validate(['segundosCpsat' => 'required|integer|min:1|max:300']);
        (new ColaGeneracionDemo)->solicitar(auth()->user(), $modo, (int) $this->segundosCpsat);
    }

    public function cancelarSolicitudPendiente(string $id): void
    {
        $this->mensaje = (new ColaGeneracionDemo)->cancelarPendiente(auth()->user(), $id)
            ? 'Solicitud cancelada. El horario se conserva.'
            : 'La solicitud ya cambió de estado; no se canceló.';
    }

    public function boot(): void { abort_unless(ConsultaLocalDemostracion::permitida(auth()->user()), 403); }

    public function mount(): void
    {
        $servicio = new HorariosVersionadosDemo;
        if ($servicio->disponible() && ($id = HorarioUnicoDemo::id())) {
            $version = request()->query('version');
            abort_if($version !== null && (!is_string($version) || !ctype_digit($version) || strlen($version) > 9 || (int)$version < 1), 422);
            $this->abrir($id, $version === null ? null : (int)$version);
        }
    }

    public function renombrar(): void
    {
        $this->validate(['nombre' => 'required|string|max:100']);
        $this->version = (new HorariosVersionadosDemo)->renombrar(auth()->user(), $this->propuesta, $this->version, $this->nombre);
        $this->mensaje = 'Nombre del horario guardado.';
    }

    public function abrir(string $id, ?int $version = null): void
    {
        $r = (new HorariosVersionadosDemo)->leer(auth()->user(), $id, $version);
        $this->propuesta = $r['id']; $this->version = $r['version']; $this->nombre = $r['nombre'];
        $this->actividad = ''; $this->dia = ''; $this->inicio = ''; $this->compararVersion = ''; $this->mensaje = '';
        $this->detalleSesion = 0;
        $this->resetValidation();
    }

    public function consultarSesion(int $id): void
    {
        $r = (new HorariosVersionadosDemo)->leer(auth()->user(), $this->propuesta, $this->version);
        abort_unless(isset($r['datos']['sesiones'][$id]) && in_array($id, array_column($r['datos']['actividades'], 'id'), true), 404);
        $this->detalleSesion = $id; $this->actividad = ''; $this->resetValidation();
    }

    public function cerrarDetalle(): void { $this->detalleSesion = 0; $this->actividad = ''; $this->resetValidation(); }

    public function cambiarVista(string $vista): void
    {
        abort_unless(in_array($vista, ConsultaSemanalDemo::VISTAS, true), 422);
        if ($vista !== $this->vistaHorario) {
            $this->vistaHorario = $vista;
            $this->detalleSesion = 0;
            $this->actividad = ''; $this->dia = ''; $this->inicio = '';
            $this->resetValidation();
        }
    }

    public function updatedCiclo(): void { $this->seccion = ''; $this->cursoFiltro = ''; $this->detalleSesion = 0; }
    public function updatedSeccion(): void { $this->cursoFiltro = ''; $this->detalleSesion = 0; }
    public function updatedCursoFiltro(): void { $this->detalleSesion = 0; }
    public function updatedDocenteFiltro(): void { $this->detalleSesion = 0; }
    public function updatedAmbienteFiltro(): void { $this->detalleSesion = 0; }

    public function limpiarFiltros(): void
    {
        match ($this->vistaHorario) {
            'docente' => $this->docenteFiltro = '',
            'ambiente' => $this->ambienteFiltro = '',
            default => $this->ciclo = $this->seccion = $this->cursoFiltro = '',
        };
        $this->detalleSesion = 0;
    }

    public function seleccionar(int $id): void
    {
        $r = (new HorariosVersionadosDemo)->leer(auth()->user(), $this->propuesta, $this->version);
        abort_unless($r['version']===(new HorariosVersionadosDemo)->leer(auth()->user(), $this->propuesta)['version'], 422, 'Abra la última versión.');
        if (isset($r['datos']['cpsat_qa'])) { abort_unless(EdicionCpsatDemo::disponible(auth()->user()), 403, 'Validador local no configurado.'); }
        abort_unless(in_array($id, array_column($r['datos']['actividades'], 'id'), true), 404);
        $s = $r['datos']['sesiones'][$id] ?? null;
        $this->actividad = (string) $id; $this->dia = $s['dia'] ?? '';
        $this->inicio = $s ? HorariosVersionadosDemo::hora($s['inicio']) : '';
        $this->ambienteEdicion = $s['ambiente'] ?? '';
        $this->detalleSesion = $id;
        $this->mensaje = ''; $this->resetValidation();
    }

    public function previsualizarMovimiento(int $id, string $dia, int $minutos): void
    {
        abort_unless(in_array($dia, HorariosVersionadosDemo::DIAS, true) && $minutos >= 0 && $minutos < 1440 && $minutos % 60 === 0, 422);
        $this->seleccionar($id);
        $this->dia = $dia; $this->inicio = HorariosVersionadosDemo::hora($minutos);
    }

    public function guardar(): void
    {
        $this->validate(['actividad' => 'required|integer|min:1', 'dia' => 'required|string', 'inicio' => 'required|string|max:5']);
        $r = (new HorariosVersionadosDemo)->leer(auth()->user(), $this->propuesta, $this->version);
        if (isset($r['datos']['cpsat_qa'])) {
            $this->validate(['ambienteEdicion'=>'nullable|string|max:100']);
            $resultado=(new EdicionCpsatDemo)->mover(auth()->user(),$this->version,(int)$this->actividad,$this->dia,$this->inicio,$this->ambienteEdicion);
            $this->version=$resultado['version']; $this->actividad=''; $this->compararVersion='';
            $this->mensaje=$resultado['nueva_version']?'Movimiento validado y guardado.':'Sin cambios; se conserva la versión vigente.';
            return;
        }
        $this->persistir($this->dia, $this->inicio);
    }

    public function retirar(): void
    {
        $this->validate(['actividad' => 'required|integer|min:1']);
        $this->persistir(null, null);
    }

    private function persistir(?string $dia, ?string $inicio): void
    {
        $anterior = $this->version;
        $this->version = (new HorariosVersionadosDemo)->editar(auth()->user(), $this->propuesta, $this->version, (int) $this->actividad, $dia, $inicio);
        $this->actividad = ''; $this->dia = ''; $this->inicio = '';
        $this->mensaje = $anterior === $this->version ? 'No hubo cambios.' : 'Nueva versión de borrador guardada.';
    }

    public function render(): View
    {
        $servicio = new HorariosVersionadosDemo;
        $disponible = $servicio->disponible();
        $propuestas = $disponible ? $servicio->listar(auth()->user()) : [];
        $actual = $disponible && $this->propuesta !== '' ? $servicio->leer(auth()->user(), $this->propuesta, $this->version) : null;
        $ultima = $actual ? $servicio->leer(auth()->user(), $this->propuesta)['version'] : 0;
        $datos = $actual['datos'] ?? ['actividades'=>[], 'secciones'=>[], 'sesiones'=>[], 'bloques'=>[]];
        $secciones = array_column($datos['secciones'], null, 'id_seccion');
        $actividades = ConsultaSemanalDemo::filtrar($datos['actividades'], $secciones, $datos['sesiones'], $this->vistaHorario, $this->ciclo, $this->seccion, $this->docenteFiltro, $this->ambienteFiltro, $this->cursoFiltro);
        $seccionesCiclo = collect($secciones)->filter(fn ($s) => $this->ciclo === '' || (string) $s['ciclo'] === $this->ciclo);
        $seccionesFiltro = $seccionesCiclo->pluck('seccion')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE);
        $cambios = [];
        if ($actual && ctype_digit($this->compararVersion) && (int) $this->compararVersion >= 1 && (int) $this->compararVersion < $this->version) {
            $base = $servicio->leer(auth()->user(), $this->propuesta, (int) $this->compararVersion);
            $cambios = HorariosVersionadosDemo::comparar($base['datos']['sesiones'], $datos['sesiones']);
        }
        // Resolver etiquetas actuales sin alterar las asignaciones congeladas de cada revisión.
        $nombresDocentes = [];
        if ($disponible) {
            $nombresDocentes = DB::table('core.docente as d')->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
                ->where('p.tipo_documento', 'QA')->where('d.codigo_docente', 'like', 'QA-%')
                ->selectRaw("d.codigo_docente, trim(concat_ws(' ', p.nombres, p.apellido_paterno, p.apellido_materno)) as nombre")
                ->pluck('nombre', 'codigo_docente')->all();
        }
        $nombresCursos = $disponible ? DB::table('core.curso')->pluck('nombre', 'codigo')->all() : [];
        $cuadricula = CuadriculaSemanalDemo::construir($actividades, $datos['sesiones'], $datos['bloques']);
        $detalle = collect($actividades)->firstWhere('id', $this->detalleSesion);
        $docentesFiltro = ConsultaSemanalDemo::opciones(
            collect($datos['actividades'])->flatMap(fn ($a) => array_column($a['docentes'], 'codigo_docente'))->unique()->mapWithKeys(fn ($codigo) => [$codigo => ($nombresDocentes[$codigo] ?? '') ?: 'Docente sin nombre conciliado'])->all(),
            '', $this->docenteFiltro);
        $nombresAmbientes = $disponible ? DB::table('core.ambiente')->pluck('nombre', 'codigo')->all() : [];
        $ambientes = $nombresAmbientes;
        foreach (collect($datos['sesiones'])->pluck('ambiente')->filter()->unique() as $codigo) {
            $ambientes[$codigo] ??= $codigo;
        }
        $ambientesFiltro = ConsultaSemanalDemo::opciones($ambientes, '', $this->ambienteFiltro);
        $cursosFiltro = ConsultaSemanalDemo::opciones($seccionesCiclo->filter(fn ($s) => $this->seccion === '' || $s['seccion'] === $this->seccion)->pluck('nombre', 'curso')->all());
        $cola = new ColaGeneracionDemo;
        $pendientes = array_filter($actividades, fn ($a) => ! isset($datos['sesiones'][$a['id']]));
        $colaDisponible = $disponible && $cola->disponible(auth()->user());
        $ejecucionCpsat = $colaDisponible ? $cola->ultima(auth()->user()) : null;
        $edicionDisponible = $actual && $this->version === $ultima && (!isset($datos['cpsat_qa']) || EdicionCpsatDemo::disponible(auth()->user())) && !in_array($ejecucionCpsat['estado'] ?? '', ColaGeneracionDemo::ACTIVOS, true);
        return view('modules.gestion-horarios.horarios-versionados', compact('disponible', 'propuestas', 'actual', 'ultima', 'datos', 'secciones', 'actividades', 'cambios', 'nombresDocentes', 'colaDisponible', 'ejecucionCpsat', 'nombresCursos', 'cuadricula', 'detalle', 'docentesFiltro', 'ambientesFiltro', 'cursosFiltro', 'seccionesFiltro', 'nombresAmbientes', 'pendientes', 'edicionDisponible'));
    }
}
