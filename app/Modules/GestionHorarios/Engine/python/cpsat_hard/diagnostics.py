"""Diagnósticos comprobables; no relaja restricciones ni cambia entradas."""
from collections import Counter, defaultdict


def itinerary_infeasibility(entry, *, seconds=15):
    """Aislar un conjunto incompatible, con presupuesto y prueba del submodelo.

    Retirar cursos completos solo elimina obligaciones; no cierra letras de un
    curso retenido. Un UNKNOWN nunca se interpreta como prueba ni permite retirar
    un curso del conjunto. Las soluciones diagnósticas no se aplican al horario.
    """
    from time import monotonic
    from ortools.sat.python import cp_model
    from scripts.motor_aplicacion import normalizar, ventanas_actividad
    from .model import build
    from .search_support import fixed_assumptions_copy
    start = monotonic()
    by_cycle = defaultdict(list)
    for section in entry['secciones']: by_cycle[section['ciclo']].append(section)
    loads = Counter()
    cycles = {s['id']:s['ciclo'] for s in entry['secciones']}
    for a in entry['actividades']: loads[cycles[a['seccion_id']]] += a['horas']

    def check(sections):
        if monotonic()-start >= seconds: return 'UNKNOWN'
        ids = {s['id'] for s in sections}
        acts = [a for a in entry['actividades'] if a['seccion_id'] in ids]
        own = {a['id'] for a in acts}
        sub = {**entry,'secciones':sections,'actividades':acts,
               'sesiones':[s for s in entry['sesiones'] if s['actividad_id'] in own]}
        i = normalizar(sub); built = build(i)
        for row in sub['sesiones']:
            if not row['fijada']: continue
            matches = [j for j,c in enumerate(i.placements) if c.session_id == row['sesion_motor'] and
                (c.dia,c.inicio,c.fin,c.ambiente_id) == (row['dia'],row['inicio'],row['fin'],row['ambiente_id'] or '')]
            if len(matches) != 1: return 'UNKNOWN'
            built.model.add(built.placements[matches[0]] == 1)
        remaining = seconds-(monotonic()-start)
        if remaining <= 0: return 'UNKNOWN'
        solver = cp_model.CpSolver()
        solver.parameters.max_time_in_seconds = min(2,remaining)
        solver.parameters.num_search_workers = 8
        solver.parameters.random_seed = 1
        return solver.status_name(solver.solve(fixed_assumptions_copy(built.model)))

    for cycle in sorted(by_cycle,key=lambda c:(-loads[c],c)):
        sections = by_cycle[cycle]
        if check(sections) != 'INFEASIBLE': continue
        tests = []
        for course in dict.fromkeys(s['curso_id'] for s in sections):
            trial = [s for s in sections if s['curso_id'] != course]
            if not trial: continue
            status = check(trial)
            tests.append({'curso_retirado':course,'estado':status})
            if status == 'INFEASIBLE': sections = trial
        course_rows = [{'curso_id':s['curso_id'],'curso':s['nombre'], 'seccion':s['etiqueta']} for s in sections]
        names = {str(d['id']):d['nombre'] for d in entry.get('docentes',[])}
        secs = {s['id']:s for s in sections}
        activities = [{'curso':secs[a['seccion_id']]['nombre'],'seccion':secs[a['seccion_id']]['etiqueta'],
            'tipo':a['tipo'],'grupo':a.get('etiqueta_grupo') or a.get('subgrupo'), 'horas':a['horas'],
            'docente':names.get(str(a['docente_id']),str(a['docente_id'])),
            'franjas_compatibles_declaradas':ventanas_actividad(entry,a),
            'fijadas':[{'dia':s['dia'],'inicio':s['inicio'],'fin':s['fin']} for s in entry['sesiones'] if s['actividad_id']==a['id'] and s['fijada']]}
            for a in entry['actividades'] if a['seccion_id'] in secs]
        courses = ', '.join(dict.fromkeys(f"{s['curso']} ({s['seccion']})" for s in course_rows))
        return {'categoria':'ITINERARIO_INCOMPATIBLE','certeza':'COMPROBADA','ciclo':cycle,
            'detalle':f'Ciclo {cycle}: no es posible programar conjuntamente {courses} con las disponibilidades y reglas actuales. La incompatibilidad persiste incluso sin los otros ciclos.',
            'alcance_prueba':'Submodelo HARD con cursos completos y todas sus secciones abiertas; no identifica una causa única ni autoriza relajar reglas.',
            'cursos':course_rows,'actividades':activities,'pruebas_reduccion':tests,'tiempo_diagnostico_s':monotonic()-start}
    return None


def activity_order_shortages(i, built):
    """Cotas necesarias de horas compatibles tras cada etapa; nunca relaja reglas.

    La unión de horas ignora empaquetado y otros cursos: da un final optimista.
    Si ni con ese final caben las horas del tipo siguiente, el déficit es probado.
    """
    if i.raw.get('orden_actividades') not in ('T_P_LAB_SEMANAL_V1', 'T_P_DURO_LAB_BLANDO_V2') or built.optional_presence:
        return []
    days = {d: n for n, d in enumerate(('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'))}
    blocked = {r['id'] for r in built.rejected}
    rows = defaultdict(list)
    for j, c in enumerate(i.placements):
        if j in built.placements and c.id not in blocked:
            rows[c.actividad_id].append((24 * days[c.dia] + c.inicio, 24 * days[c.dia] + c.fin))
    sections = defaultdict(list)
    for a in i.activities.values():
        sections[a['seccion_id']].append(a)
    shortages = []
    for section, activities in sections.items():
        if i.sections[section]['curso_id'] in i.raw.get('excepciones_orden_actividades', []):
            continue
        boundary, previous = 0, None
        sequence = ('T', 'P', 'LAB') if i.raw['orden_actividades'] == 'T_P_LAB_SEMANAL_V1' else ('T', 'P')
        for kind in sequence:
            own = [a for a in activities if a['tipo'] == kind]
            if not own:
                continue
            ends = []
            for a in own:
                slots = sorted({h for start, end in rows[a['id']] if start >= boundary for h in range(start, end)})
                required = a['horas_semanales']
                if len(slots) < required:
                    if previous is not None:
                        shortages.append({'seccion_id': section, 'actividad_id': a['id'], 'curso_id': a['curso_id'],
                                          'tipo': kind, 'despues_de': previous, 'horas_requeridas': required,
                                          'horas_compatibles': len(slots), 'deficit': required - len(slots)})
                    break
                ends.append(slots[required - 1] + 1)
            else:
                boundary = max(ends)
                previous = kind
                continue
            break
    return shortages


def diagnose(i, built, solver, status):
    blocked={r['id'] for r in built.rejected}
    counts=Counter(c.session_id for j,c in enumerate(i.placements) if j in built.placements and c.id not in blocked)
    patterns=[]
    for p in i.patterns.values():
        if p['id'] not in built.patterns: continue
        empty=[s['id'] for s in i.sessions.values() if s['patron_id']==p['id'] and not counts[s['id']]]
        if empty: patterns.append({'patron_id':p['id'],'sesiones_sin_dominio':empty})
    empty_patterns={p['patron_id'] for p in patterns}
    impossible=[a['id'] for a in i.activities.values() if not(i.raw.get('politica_repeticion_fija') and a['es_repeticion']) and all(p['id'] in empty_patterns for p in i.patterns.values() if p['actividad_id']==a['id'])]
    slots=defaultdict(set)
    for j,c in enumerate(i.placements):
        if j not in built.placements: continue
        if c.id not in blocked:
            slots[c.docente_id].update((c.dia,h) for h in range(c.inicio,c.fin))
    for e in i.raw.get('repeticiones_fijas_m0',[]):
        slots[e['docente_id']].update((e['dia'],h) for h in range(e['inicio'],e['fin']))
    shortages=[]
    for teacher in i.teachers:
        load=sum(a['horas_semanales'] for a in i.activities.values() if a['docente_id']==teacher)
        if load>len(slots[teacher]):
            shortages.append({'docente_id':teacher,'carga':load,'horas_unicas_en_dominio':len(slots[teacher]),'deficit':load-len(slots[teacher]),'slots':sorted(slots[teacher])})
    core=[]
    if status=='INFEASIBLE':
        for literal in solver.sufficient_assumptions_for_infeasibility():
            index=literal if literal>=0 else -literal-1
            core.append({'literal':literal,**built.labels.get(index,{'detalle':'literal sin etiqueta'})})
    return {'estado':status,'interpretacion':'Infactibilidad demostrada para el modelo exportado.' if status=='INFEASIBLE' else 'No se demostró factibilidad ni infactibilidad antes del límite.' if status=='UNKNOWN' else 'Ver estadísticas del solucionador.',
            'nucleo_suficiente_no_necesariamente_minimo':core,'actividades_sin_patron_viable':impossible,
            'patrones_con_sesiones_vacias':patterns,'cotas_docentes_insuficientes':shortages,
            'cotas_por_ciclo':built.cycle_bounds,
            'ciclos_con_deficit':[r for r in built.cycle_bounds if r['deficit']>0],
            'rechazos_de_dominio':built.rejected,'estadisticas_solver':solver.response_stats(),
            'fuentes_sha256':i.hashes}
