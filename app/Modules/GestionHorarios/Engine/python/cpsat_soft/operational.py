"""Preferencias del horario operativo; no modifica las variantes históricas S01–S06."""
from collections import defaultdict
from fractions import Fraction
from ortools.sat.python import cp_model

from cpsat_hard.policy import conflict_cohort
from .specification import WEIGHTS, integer_weights

VERSION = 'CONTINUIDAD_Y_LAB_V1'
DAYS = ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO')


def improve_rooms(i, built, initial, seconds):
    """Reasigna aulas T/P sin mover las sesiones."""
    return _improve(i, built, initial, seconds, 'aulas')


def improve_single_hours(i, built, initial, seconds):
    """Ajusta solo sesiones de una hora que aparecen aisladas."""
    return _improve(i, built, initial, seconds, 'horas_sueltas')


def _improve(i, built, initial, seconds, mode):
    """Busca mejoras dentro de una vecindad acotada del horario factible.

    El clon mantiene todas las restricciones y el mismo objetivo. Su cota solo
    corresponde al subproblema de aulas; no certifica optimalidad del horario.
    """
    m = built.model.clone()
    chosen, candidates = {}, defaultdict(list)
    for j, c in enumerate(i.placements):
        if j not in built.placements:
            continue
        candidates[c.session_id].append(j)
        if initial.value(built.placements[j]):
            chosen[c.session_id] = (j, c)
    for pid, variable in built.patterns.items():
        m.add(variable == initial.value(variable))
    movable = set()
    if mode == 'horas_sueltas':
        from dataclasses import asdict
        islands = {(tuple(r['cohorte']), r['dia'], r['inicio']) for r in evaluate(i, [asdict(c) for _,c in chosen.values()])['horas_sueltas']}
        movable = {sid for sid,(_,c) in chosen.items() if c.fin-c.inicio == 1
                   and (built.cohorts[c.actividad_id], c.dia, c.inicio) in islands}
        if not movable:
            return initial, {'estado': 'NO_NECESARIO', 'aplicado': False, 'tiempo_s': 0, 'presupuesto_s': seconds}
    for sid, (selected, row) in chosen.items():
        if mode == 'horas_sueltas':
            if sid not in movable:
                m.add(built.placements[selected] == 1)
            continue
        a = i.activities[row.actividad_id]
        if a['tipo'] not in ('T', 'P') or not a['requiere_ambiente']:
            m.add(built.placements[selected] == 1)
            continue
        same_time = [built.placements[j] for j in candidates[sid]
                     if (i.placements[j].dia, i.placements[j].inicio, i.placements[j].fin) == (row.dia, row.inicio, row.fin)]
        m.add(sum(same_time) == 1)
    m.clear_hints()
    for index in range(len(m.proto.variables)):
        variable = m.get_int_var_from_proto_index(index)
        m.add_hint(variable, initial.value(variable))
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = seconds
    solver.parameters.num_search_workers = 1
    solver.parameters.random_seed = 1
    status = solver.solve(m)
    accepted = status in (cp_model.OPTIMAL, cp_model.FEASIBLE) and solver.objective_value <= initial.objective_value
    scope = 'Aulas T/P; días, horas, patrones, laboratorios y fijaciones conservados.' if mode == 'aulas' else 'Solo sesiones de una hora aisladas; otras sesiones, patrones y fijaciones conservados.'
    return (solver if accepted else initial), {'estado': solver.status_name(status), 'aplicado': accepted,
        'tiempo_s': solver.wall_time, 'presupuesto_s': seconds, 'alcance': scope}


def add(i, built, penalties, spec, student_cost, student_bound, thesis_crossings, *, reference=None):
    m = built.model
    blocked = {r['id'] for r in built.rejected}
    slots, rooms, sessions = defaultdict(list), defaultdict(list), defaultdict(list)
    single_slots, room_types = set(), defaultdict(set)
    anchors = {sid: room for sid,room in (reference or {}).items() if room and sid in i.sessions
               and i.activities[i.sessions[sid]['actividad_id']]['tipo'] == 'T'}
    theory_room_changes = []
    for j, c in enumerate(i.placements):
        if j not in built.placements or c.id in blocked:
            continue
        a, x = i.activities[c.actividad_id], built.placements[j]
        group = built.cohorts[c.actividad_id]
        sessions[c.session_id].append((c, x))
        if c.session_id in anchors and c.ambiente_id != anchors[c.session_id]:
            theory_room_changes.append(x)
        for h in range(c.inicio, c.fin):
            slots[group, c.dia, h].append(x)
            if c.fin - c.inicio == 1:
                single_slots.add((group, c.dia, h))
        if a['tipo'] in ('T', 'P') and c.ambiente_id:
            rooms[c.seccion_id, c.ambiente_id].append(x)
            room_types[c.seccion_id].add(a['tipo'])
    occupied = {}
    for n, (key, xs) in enumerate(slots.items()):
        occupied[key] = m.new_bool_var(f'op_ocupacion_{n}')
        m.add_max_equality(occupied[key], xs)
    hours = sorted(b['inicio'] for b in i.raw['calendario']['bloques_hora_base'])
    isolated = []
    for n, (group, day, h) in enumerate(sorted(single_slots)):
        at = hours.index(h)
        neighbours = [occupied[group, day, hours[k]] for k in (at-1, at+1)
                      if 0 <= k < len(hours) and (group, day, hours[k]) in occupied]
        one = m.new_bool_var(f'op_hora_suelta_{n}')
        m.add(one <= occupied[group, day, h])
        for neighbour in neighbours:
            m.add(one + neighbour <= 1)
        m.add(one >= occupied[group, day, h] - sum(neighbours))
        isolated.append(one)
    used = defaultdict(list)
    for n, ((section, room), xs) in enumerate(rooms.items()):
        if room_types[section] != {'T', 'P'}:
            continue
        present = m.new_bool_var(f'op_aula_{n}')
        m.add_max_equality(present, xs)
        used[section].append(present)
    room_excess, room_bound = [], 0
    for n, xs in enumerate(used.values()):
        excess = m.new_int_var(0, max(0, len(xs)-1), f'op_aulas_adicionales_{n}')
        m.add_max_equality(excess, [sum(xs)-1, 0])
        room_excess.append(excess)
        room_bound += max(0, len(xs)-1)
    previous, labs = defaultdict(list), defaultdict(list)
    for sid, rows in sessions.items():
        a = i.activities[rows[0][0].actividad_id]
        if a['tipo'] in ('T', 'P'):
            previous[a['seccion_id']].append(sum((24*DAYS.index(c.dia)+c.fin)*x for c,x in rows))
        elif a['tipo'] == 'LAB':
            labs[a['seccion_id']].append((sid, rows))
    early_labs = []
    for section, lab_sessions in labs.items():
        if not previous[section]:
            continue
        completion = m.new_int_var(0, 168, f'op_fin_tp_{section}')
        m.add_max_equality(completion, previous[section])
        for sid, rows in lab_sessions:
            if i.raw.get('orden_actividades') == 'T_P_LAB_SEMANAL_V1' and i.sections[section]['curso_id'] not in i.raw.get('excepciones_orden_actividades',[]):
                # H23 ya demuestra cero. Mantener la cota y los pesos históricos.
                early_labs.append(m.new_constant(0))
                continue
            presence = built.patterns[i.sessions[sid]['patron_id']]
            start = sum((24*DAYS.index(c.dia)+c.inicio)*x for c,x in rows)
            early = m.new_bool_var(f'op_lab_antes_{sid}')
            m.add(early <= presence)
            m.add(start < completion).only_enforce_if(early)
            m.add(start >= completion).only_enforce_if([presence, early.Not()])
            early_labs.append(early)
    one_hour_bound = sum(s['duracion'] == 1 for s in i.sessions.values())
    terms = {**penalties, 'HORA_SUELTA': sum(isolated), 'AULA_TP': sum(room_excess),
             'ORDEN_LAB': sum(early_labs), 'AULA_TEORIA': sum(theory_room_changes), 'PREFERENCIAS': student_cost, 'CRUCES_TESIS': sum(thesis_crossings)}
    bounds = {**spec['cotas'], 'HORA_SUELTA': min(len(isolated), one_hour_bound), 'AULA_TP': room_bound,
              'ORDEN_LAB': len(early_labs), 'AULA_TEORIA': len(anchors), 'PREFERENCIAS': student_bound, 'CRUCES_TESIS': len(thesis_crossings)}
    weights = {**WEIGHTS, 'HORA_SUELTA': Fraction(1, 2), 'AULA_TP': Fraction(1, 2),
               'ORDEN_LAB': Fraction(1, 8), 'AULA_TEORIA': Fraction(1, 4), 'PREFERENCIAS': Fraction(1, 16), 'CRUCES_TESIS': Fraction(1, 16)}
    version = i.raw.get('preferencias_operativas', VERSION)
    if version == 'CONTINUIDAD_TURNOS_Y_SECCIONES_V2':
        weights['S01'] = Fraction(1)
        if i.raw.get('politica_conflictos_operativa') != 'CICLO_COMPLETO_V3':
            from .interchange import add as add_interchange
            terms['CRUCE_SECCIONES'], bounds['CRUCE_SECCIONES'] = add_interchange(i, built)
            weights['CRUCE_SECCIONES'] = Fraction(1, 2)
    normalization = integer_weights(bounds, weights=weights)
    objective = sum(normalization['coeficientes'][key]*value for key,value in terms.items())
    return objective, {'version': version, 'cotas': bounds, 'normalizacion': normalization,
        'turnos_por_ciclo': spec['preferencia_por_ciclo']}, terms


def evaluate(i, rows, *, reference=None):
    """Recalcula desde las sesiones elegidas, sin variables ni valores del solver."""
    occupancy, room_sets, kinds, sections = defaultdict(set), defaultdict(set), defaultdict(set), defaultdict(list)
    for row in rows:
        a = i.activities[row['actividad_id']]
        group = conflict_cohort(i, a)
        occupancy[group, row['dia']].update(range(row['inicio'], row['fin']))
        sections[a['seccion_id']].append(row)
        if a['tipo'] in ('T', 'P') and row['ambiente_id']:
            room_sets[a['seccion_id']].add(row['ambiente_id'])
            kinds[a['seccion_id']].add(a['tipo'])
    hours = sorted(b['inicio'] for b in i.raw['calendario']['bloques_hora_base'])
    isolated = []
    for (group, day), occupied in occupancy.items():
        for h in sorted(occupied):
            n = hours.index(h)
            if not any(hours[k] in occupied for k in (n-1, n+1) if 0 <= k < len(hours)):
                isolated.append({'cohorte': list(group), 'dia': day, 'inicio': h, 'fin': h+1})
    extra = [{'seccion_id': s, 'ambientes': sorted(rs), 'adicionales': len(rs)-1}
             for s, rs in room_sets.items() if kinds[s] == {'T', 'P'} and len(rs) > 1]
    early_labs = []
    for section, own in sections.items():
        previous = [24*DAYS.index(r['dia'])+r['fin'] for r in own if i.activities[r['actividad_id']]['tipo'] in ('T', 'P')]
        if not previous:
            continue
        for row in own:
            if i.activities[row['actividad_id']]['tipo'] == 'LAB' and 24*DAYS.index(row['dia'])+row['inicio'] < max(previous):
                early_labs.append(row['session_id'])
    theory_changes = [r['session_id'] for r in rows if i.activities[r['actividad_id']]['tipo'] == 'T'
                      and (reference or {}).get(r['session_id']) and r['ambiente_id'] != reference[r['session_id']]]
    penalties = {'HORA_SUELTA': len(isolated), 'AULA_TP': sum(r['adicionales'] for r in extra), 'ORDEN_LAB': len(early_labs), 'AULA_TEORIA': len(theory_changes)}
    interchange = None
    if i.raw.get('preferencias_operativas') == 'CONTINUIDAD_TURNOS_Y_SECCIONES_V2' and i.raw.get('politica_conflictos_operativa') != 'CICLO_COMPLETO_V3':
        from .interchange import evaluate as evaluate_interchange
        interchange = evaluate_interchange(i, rows)
        penalties['CRUCE_SECCIONES'] = interchange['horas_de_cruce']
    return {'horas_sueltas': isolated, 'aulas_adicionales_tp': extra, 'laboratorios_antes_de_tp': early_labs, 'cambios_aula_teoria': theory_changes,
            'penalizaciones': penalties, 'compatibilidad_secciones': interchange,
            'alcance': 'Ocupación agregada por ciclo/sección y grupos independientes de asesoría; no sustituye matrícula individual. El receso no crea una hora aislada.'}
