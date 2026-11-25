"""Ejecución determinista y registro de versiones, límites y diagnóstico."""
import platform
import time
import ortools
from ortools.sat.python import cp_model
from .diagnostics import diagnose
from .exporting import schedule
from .policy import policy_metadata


def solve(i, built, time_limit=300, workers=1, seed=1):
    if time_limit<=0 or workers!=1 or seed<0:
        raise ValueError('Tiempo positivo, un trabajador y semilla no negativa requeridos')
    solver=cp_model.CpSolver()
    solver.parameters.max_time_in_seconds=time_limit
    solver.parameters.num_search_workers=workers
    solver.parameters.random_seed=seed
    start=time.perf_counter()
    status=solver.solve(built.model)
    label=solver.status_name(status)
    result={'estado':label,'objetivo':'FACTIBILIDAD_HARD_SIN_SOFT',
            'politica_conflictos':policy_metadata(i),'ciclo_conflicto_por_actividad':built.cycles,
            'horario_conflicto_por_actividad':built.cohorts,
            'parametros':{'max_time_in_seconds':time_limit,'num_search_workers':workers,'random_seed':seed},
            'versiones':{'python':platform.python_version(),'ortools':ortools.__version__},
            'tiempo_solver_s':solver.wall_time,'tiempo_ejecucion_s':time.perf_counter()-start,
            'fuentes_sha256':i.hashes,'n_actividades_entrada':len(i.activities),'n_patrones_entrada':len(i.patterns),
            'n_candidatas_entrada':len(i.placements),'n_candidatas_bloqueadas':len(built.rejected),
            'n_pares_estudiantiles_explicitos_o_padron':len(built.student_pairs),
            'estadisticas_solver':solver.response_stats(),'patrones_elegidos':[],'sesiones_elegidas':[]}
    result['politica_repeticion_fija']=i.raw.get('politica_repeticion_fija')
    result['excepciones_periodo']=i.raw.get('excepciones_periodo')
    if status in (cp_model.OPTIMAL,cp_model.FEASIBLE): result.update(schedule(i,built,solver))
    diagnostic=diagnose(i,built,solver,label) if label not in ('OPTIMAL','FEASIBLE') else None
    return result,diagnostic
