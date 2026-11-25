"""Resolución SOFT y cotejo contra mediciones independientes del horario real."""
import time
from ortools.sat.python import cp_model
from cpsat_hard.exporting import schedule
from .metrics import evaluate
from .official import DEFAULT_VARIANT, DEFAULT_SEARCH_PROFILE, MODEL_VERSION, VARIANTS
from .search import complete_hint, configure, peak_memory, PROFILES


def add_hint(i,built,outcome):
    selected={e['id'] for e in outcome['sesiones_elegidas']}
    patterns=set(outcome['patrones_elegidos'])
    for j,v in built.placements.items(): built.model.add_hint(v,int(i.placements[j].id in selected))
    for pid,v in built.patterns.items(): built.model.add_hint(v,int(pid in patterns))
    for v in built.fixed_events.values(): built.model.add_hint(v,1)
    for e in outcome.get('sesiones_fijas_repeticion',[]):
        for typ,value in e.get('composicion_curricular_modelada',{}).items():
            built.model.add_hint(built.mixed_composition[(e['id'],typ)],value)


def solve(i,built,penalties,spec,time_limit=300,workers=1,seed=1,*,variant=None,search_profile=None):
    if time_limit<=0 or workers!=1 or seed<0: raise ValueError('Parámetros inválidos')
    variant=variant or DEFAULT_VARIANT
    search_profile=search_profile or (DEFAULT_SEARCH_PROFILE if variant=='M1-C' else 'complete_hint')
    if variant not in VARIANTS or search_profile not in PROFILES: raise ValueError('Variante o perfil no válido')
    if variant!='M1-C' and search_profile!='complete_hint': raise ValueError('Perfil de búsqueda solo permitido en M1-C')
    total_start=time.perf_counter()
    hint_record={'estado':'NO_APLICA','tiempo_s':0.0,'variables_completadas':0}
    if variant=='M1-C': hint_record=complete_hint(built.model,min(5.0,time_limit*0.1),seed)
    preparation=time.perf_counter()-total_start
    # Callback solo observa; no cambia dominios ni detiene la búsqueda.
    class Observer(cp_model.CpSolverSolutionCallback):
        def __init__(self): super().__init__();self.first=None;self.count=0;self.progress=[]
        def on_solution_callback(self):
            self.count+=1
            if self.first is None: self.first=preparation+self.wall_time
            self.progress.append({'tiempo_s':preparation+self.wall_time,'objetivo':self.objective_value,'cota':self.best_objective_bound})
    observer=Observer();solver=cp_model.CpSolver()
    solver.parameters.max_time_in_seconds=max(0.001,time_limit-preparation)
    solver.parameters.num_search_workers=workers;solver.parameters.random_seed=seed
    if variant=='M1-C': configure(solver,search_profile)
    bounds=[]
    solver.best_bound_callback=lambda bound: bounds.append({'tiempo_s':time.perf_counter()-total_start,'cota':bound})
    start=time.perf_counter();status=solver.solve(built.model,observer)
    label=solver.status_name(status)
    import ortools,platform
    result={'estado':label,'objetivo':'MIN_S01_S06_NORMALIZADO_ENTERO',
        'parametros':{'max_time_in_seconds':time_limit,'num_search_workers':workers,'random_seed':seed},
        'versiones':{'python':platform.python_version(),'ortools':ortools.__version__,'sistema':platform.platform()},
        'tiempo_solver_s':solver.wall_time,'tiempo_ejecucion_s':time.perf_counter()-total_start,
        'variante':variant,'version_modelo':MODEL_VERSION,'preparacion_hint':hint_record,
        'perfil_busqueda':search_profile if variant=='M1-C' else 'AUTOMATIC_SEARCH',
        'parametros_solver_efectivos':str(solver.parameters),
        'progreso_objetivo':observer.progress,'progreso_cota':bounds,'memoria_pico':peak_memory(),
        'tiempo_primera_solucion_s':observer.first,'soluciones_observadas':observer.count,
        'fuentes_sha256':i.hashes,'excepciones_periodo':i.raw.get('excepciones_periodo'),
        'politica_repeticion_fija':i.raw.get('politica_repeticion_fija'),
        'estadisticas_solver':solver.response_stats(),'mejor_cota':solver.best_objective_bound,
        'valor_objetivo':None,'brecha_relativa':None,'sesiones_elegidas':[],'patrones_elegidos':[]}
    if status in (cp_model.FEASIBLE,cp_model.OPTIMAL):
        result.update(schedule(i,built,solver))
        costs={k:solver.value(v) for k,v in penalties.items()}
        value=sum(spec['normalizacion']['coeficientes'][k]*v for k,v in costs.items())
        if value!=int(round(solver.objective_value)): raise ValueError('Objetivo del solver no coincide')
        measured=evaluate(i,result,spec)
        if measured['penalizaciones']!=costs or measured['objetivo_entero_recalculado']!=value or measured['violaciones_evaluacion']:
            raise ValueError(f'Penalizaciones modeladas difieren del horario: {costs} / {measured}')
        result.update(valor_objetivo=value,penalizaciones_solver=costs,
            brecha_relativa=(value-solver.best_objective_bound)/max(1,abs(value)))
        return result,measured
    return result,None
