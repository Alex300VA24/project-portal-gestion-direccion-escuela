"""Construcción del modelo de factibilidad, sin función objetivo."""
from dataclasses import dataclass, field
from ortools.sat.python import cp_model
from . import domains, hard
from .precheck import inspect_curriculum
from .data import InputError
from .policy import conflict_cycle, conflict_cohort, cycle_capacity_bounds


@dataclass
class BuiltModel:
    model: cp_model.CpModel
    patterns: dict
    placements: dict
    labels: dict
    rejected: list
    student_pairs: list
    cycles: dict
    cycle_bounds: list
    cohorts: dict
    fixed_events: dict
    mixed_composition: dict
    optional_presence: dict = field(default_factory=dict)


def build(i, scenario='B', *, diagnostic_window_expansion=False, optional_groups=None):
    if scenario not in ('A','B'):
        raise InputError('Escenario debe ser A o B')
    if diagnostic_window_expansion and i.raw.get('politica_repeticion_fija'):
        raise InputError('La política de repetición fija prohíbe ampliar o redistribuir sus horarios')
    errors = inspect_curriculum(i)
    if errors:
        raise InputError(f'Invariantes curriculares: {errors}')
    for a in i.activities.values():
        if i.raw.get('politica_repeticion_fija') and a['es_repeticion']: continue
        domains.windows_for(i,a,scenario)
    m = cp_model.CpModel()
    optional_groups = optional_groups or {}
    if optional_groups and i.raw.get('politica_repeticion_fija'):
        raise InputError('La selección de secciones no altera la política histórica de repetición fija')
    optional_presence = {gid: m.new_bool_var(f'seccion_plan_{n}') for n, gid in enumerate(optional_groups)}
    activity_presence = {}
    for gid, ids in optional_groups.items():
        for aid in ids:
            if aid not in i.activities or aid in activity_presence:
                raise InputError('Actividad opcional desconocida o repetida: '+str(aid))
            activity_presence[aid] = optional_presence[gid]
    cons = hard.Constraints(m)
    fixed_mode=bool(i.raw.get('politica_repeticion_fija'))
    choose = {pid:m.new_bool_var(f'pat_{k}') for k,pid in enumerate(i.patterns)
              if not(fixed_mode and i.activities[i.patterns[pid]['actividad_id']]['es_repeticion'])}
    x = {j:m.new_bool_var(f'col_{j}') for j,c in enumerate(i.placements)
         if not(fixed_mode and i.activities[c.actividad_id]['es_repeticion'])}
    rejected = []
    for j,c in enumerate(i.placements):
        if j not in x: continue
        reasons = domains.violations(i,c,scenario)
        # Solo el planificador de propuestas usa este modo explícito. El CLI
        # de producción nunca lo habilita y conserva íntegra la condición H08.
        blocking=[r for r in reasons if not(diagnostic_window_expansion and r['regla']=='H08')]
        if blocking:
            cons.add(x[j]==0,'DOMINIO',c.id)
            rejected.append({'id':c.id,'motivos':blocking})
    hard.select_patterns(i,m,cons,choose,x,activity_presence)
    hard.activity_order(i,m,cons,x)
    from .fixed_repetitions import add_constraints,real_student_conflicts
    fixed,mixed=add_constraints(i,m,cons)
    hard.resource_conflicts(i,cons,x,fixed)
    pairs = hard.student_conflicts(i,cons,x)
    real_student_conflicts(i,cons,x,fixed)
    cycles = {a['id']:conflict_cycle(a) for a in i.activities.values()}
    cohorts = {a['id']:conflict_cohort(i,a) for a in i.activities.values()}
    # Los cortes de carga obligatoria solo son válidos cuando todas las actividades existen.
    bounds = [] if optional_groups else cycle_capacity_bounds(i,cohorts,{r['id'] for r in rejected})
    hard.cycle_conflicts(i,m,cons,x,cohorts,bounds, choose=choose, activity_presence=activity_presence)
    error = m.validate()
    if error:
        raise InputError(f'CP-SAT MODEL_INVALID: {error}')
    return BuiltModel(m,choose,x,cons.labels,rejected,pairs,cycles,bounds,cohorts,fixed,mixed,optional_presence)
