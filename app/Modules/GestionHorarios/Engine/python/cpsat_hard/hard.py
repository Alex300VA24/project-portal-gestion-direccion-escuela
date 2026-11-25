"""Restricciones HARD; agrupación por regla para núcleos de infactibilidad."""
from collections import defaultdict
from itertools import combinations
from .precheck import subgroup


class Constraints:
    def __init__(self, model):
        self.model = model
        self.labels = {}

    def add(self, expression, rule, detail):
        gate = self.model.new_bool_var(f'assumption_{len(self.labels)}')
        self.model.add(expression).only_enforce_if(gate)
        self.model.add_assumption(gate)
        self.labels[gate.index] = {'regla':rule,'detalle':detail}


def select_patterns(i, model, constraints, choose, x, activity_presence=None):
    activity_presence = activity_presence or {}
    for a in i.activities.values():
        if i.raw.get('politica_repeticion_fija') and a['es_repeticion']: continue
        ps = [p for p in i.patterns.values() if p['actividad_id']==a['id']]
        constraints.add(sum(choose[p['id']] for p in ps)==activity_presence.get(a['id'], 1),'H01',a['id'])
    by_session = defaultdict(list)
    by_activity = defaultdict(list)
    for j,c in enumerate(i.placements):
        if j not in x: continue
        by_session[c.session_id].append(x[j])
        by_activity[c.actividad_id].append((c.fin-c.inicio)*x[j])
    for s in i.sessions.values():
        if s['patron_id'] not in choose: continue
        constraints.add(sum(by_session[s['id']])==choose[s['patron_id']], 'H10', s['id'])
    for a in i.activities.values():
        if i.raw.get('politica_repeticion_fija') and a['es_repeticion']: continue
        constraints.add(sum(by_activity[a['id']])==a['horas_semanales']*activity_presence.get(a['id'], 1),'H09',a['id'])


def resource_conflicts(i, constraints, x, fixed=None):
    docs,rooms,tp,labs = (defaultdict(list) for _ in range(4))
    fixed_rooms=defaultdict(list)
    for j,c in enumerate(i.placements):
        if j not in x: continue
        a = i.activities[c.actividad_id]
        for h in range(c.inicio,c.fin):
            docs[(a['docente_id'],c.dia,h)].append(x[j])
            if c.ambiente_id:
                rooms[(c.ambiente_id,c.dia,h)].append(x[j])
            for sid in a.get('secciones_participantes', [a['seccion_id']]):
                key = (sid,c.dia,h)
                if a['tipo'] in ('T','P'): tp[key].append(x[j])
                else: labs[key].append((subgroup(a),x[j]))
    for e in i.raw.get('repeticiones_fijas_m0',[]):
        for h in range(e['inicio'],e['fin']):
            docs[(e['docente_id'],e['dia'],h)].append(fixed[e['id']])
            fixed_rooms[(e['ambiente_id'],e['dia'],h)].append(e)
    for occ,rule in ((docs,'H02'),):
        for key,vs in occ.items():
            if len(vs)>1: constraints.add(sum(vs)<=1,rule,str(key))
    from .exceptions import room_pair_allowed
    for key in set(rooms)|set(fixed_rooms):
        regular=rooms[key]; events=fixed_rooms[key]
        if len(regular)>1: constraints.add(sum(regular)<=1,'H03',str(key))
        for e in events:
            if regular: constraints.add(sum(regular)+fixed[e['id']]<=1,'H03',f'{key} {e["m0_id"]}')
        for a,b in combinations(events,2):
            if not room_pair_allowed(i,a['m0_id'],b['m0_id'],key[1],key[2],key[0]):
                constraints.add(fixed[a['id']]+fixed[b['id']]<=1,'H03',f'{key} {a["m0_id"]} {b["m0_id"]}')
    for key in sorted(set(tp)|set(labs)):
        if len(tp[key])>1: constraints.add(sum(tp[key])<=1,'H04',str(key))
        groups = defaultdict(list)
        for gid,v in labs[key]: groups[gid].append(v)
        for gid,vs in groups.items():
            if len(vs)>1: constraints.add(sum(vs)<=1,'H06',f'{key} {gid}')
            if tp[key]: constraints.add(sum(tp[key])+sum(vs)<=1,'H05',f'{key} {gid}')


def student_conflicts(i, constraints, x):
    pairs = {tuple(sorted(p)) for p in i.raw.get('conflictos_estudiantes',[])}
    acts = list(i.activities.values())
    for a,b in combinations(acts,2):
        if set(a.get('estudiantes_ids',[])) & set(b.get('estudiantes_ids',[])):
            pairs.add(tuple(sorted((a['id'],b['id']))))
    slots = defaultdict(list)
    for j,c in enumerate(i.placements):
        if j not in x: continue
        for h in range(c.inicio,c.fin):
            slots[(c.actividad_id,c.dia,h)].append(x[j])
    times = {(c.dia,h) for c in i.placements for h in range(c.inicio,c.fin)}
    for a,b in sorted(pairs):
        for day,h in sorted(times):
            left,right = slots[(a,day,h)],slots[(b,day,h)]
            if left and right:
                constraints.add(sum(left)+sum(right)<=1,'H04',f'{a} / {b} {day} {h}')
    return sorted(pairs)


def cycle_conflicts(i, model, constraints, x, cohorts, bounds, *, choose=None, activity_presence=None):
    """Un curso ocupa el bloque si alguna de sus secciones/sesiones lo ocupa.

    Sumamos presencias de cursos, no todas sus candidatas: las secciones y los
    subgrupos del mismo curso pueden ir en paralelo respetando H02–H06.
    """
    if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4':
        return itinerary_conflicts(i, model, constraints, x, cohorts, choose, activity_presence or {})
    course_slots=defaultdict(list)
    for j,c in enumerate(i.placements):
        if j not in x: continue
        for h in range(c.inicio,c.fin):
            course_slots[(cohorts[c.actividad_id],i.activities[c.actividad_id]['curso_id'],c.dia,h)].append(x[j])
    cycle_slots=defaultdict(list)
    course_presence=defaultdict(list)
    for k,(key,variables) in enumerate(sorted(course_slots.items())):
        cycle,course,day,h=key
        occupied=model.new_bool_var(f'curso_ocupado_{k}')
        model.add_max_equality(occupied,variables)
        cycle_slots[(cycle,day,h)].append(occupied)
        course_presence[(cycle,course)].append(occupied)
    for key,variables in sorted(cycle_slots.items()):
        if len(variables)>1:
            constraints.add(sum(variables)<=1,'H04',f'CICLO_SECCION_SIN_CRUCE {key}')
    # Cortes redundantes demostrables que facilitan detectar déficits de ciclo.
    for row in bounds:
        for course in row['cursos']:
            key=((row['ciclo'],row['seccion_horario']),course['curso_id'])
            constraints.add(sum(course_presence[key])>=course['minimo_horas_ocupadas'],
                            'H04',f"COTA_OCUPACION_CURSO {key} >= {course['minimo_horas_ocupadas']}")


def itinerary_conflicts(i, model, constraints, x, cohorts, choose, activity_presence):
    """Presencias por curso/letra, sin pares de candidatas ni suma de alternativas."""
    from .policy import section_label, lab_alternatives_enabled
    alternatives = lab_alternatives_enabled(i)
    acts_by_section, labels, slots = defaultdict(list), defaultdict(list), defaultdict(list)
    common_slots = defaultdict(list)
    repeated = {}
    for a in i.activities.values():
        for sid in a.get('secciones_participantes', [a['seccion_id']]):
            acts_by_section[sid].append(a)
    for s in i.sections.values():
        acts = acts_by_section[s['id']]
        if s.get('es_repeticion') or any(a['es_repeticion'] for a in acts): continue
        cycle = acts[0]['ciclo_curricular'] if acts else i.courses[s['curso_id']].get('ciclo_curricular')
        if cycle is None: continue
        key = (cycle, s['curso_id'], section_label(s))
        if not acts or any(a['id'] not in activity_presence for a in acts):
            labels[key].append(1)
        else:
            labels[key].extend(choose[p['id']] for p in i.patterns.values() if p['actividad_id'] in {a['id'] for a in acts})
    by_course = defaultdict(list)
    for n, (key, presences) in enumerate(sorted(labels.items())):
        if any(isinstance(v, int) and v == 1 for v in presences): present = 1
        else:
            present = model.new_bool_var(f'apertura_letra_{n}')
            model.add_max_equality(present, presences)
        by_course[key[:2]].append(present)
    single = {}
    for n, (key, presences) in enumerate(sorted(by_course.items())):
        if all(isinstance(v, int) for v in presences): single[key] = int(sum(presences) == 1)
        else:
            single[key] = model.new_bool_var(f'curso_seccion_unica_{n}')
            model.add(sum(presences) == 1).only_enforce_if(single[key])
            model.add(sum(presences) != 1).only_enforce_if(single[key].Not())
    multi_lab = {}
    if alternatives:
        for section, acts in acts_by_section.items():
            groups = defaultdict(list)
            for a in acts:
                group = a.get('subgrupo_id') or a.get('grupo_id')
                if a['tipo'] == 'LAB' and not a['es_repeticion'] and a.get('tipo_grupo') != 'TESIS' and group:
                    groups[group].append(a)
            presences = []
            for group, members in groups.items():
                if any(a['id'] not in activity_presence for a in members): present = 1
                else:
                    present = model.new_bool_var(f'apertura_lab_{section}_{group}')
                    model.add_max_equality(present,[choose[p['id']] for p in i.patterns.values() if p['actividad_id'] in {a['id'] for a in members}])
                presences.append(present)
            if all(isinstance(v,int) for v in presences): multi_lab[section] = int(sum(presences) >= 2)
            else:
                multi_lab[section] = model.new_bool_var(f'labs_alternativos_{section}')
                model.add(sum(presences) >= 2).only_enforce_if(multi_lab[section])
                model.add(sum(presences) < 2).only_enforce_if(multi_lab[section].Not())
    for j,c in enumerate(i.placements):
        if j not in x: continue
        a = i.activities[c.actividad_id]
        if a['es_repeticion']:
            repeated[j] = x[j]
            continue
        keys = {(a['ciclo_curricular'], a['curso_id'], section_label(i.sections[sid]))
                for sid in a.get('secciones_participantes', [a['seccion_id']])}
        common = x[j]
        if alternatives and a['tipo'] == 'LAB' and (a.get('subgrupo_id') or a.get('grupo_id')) and a.get('tipo_grupo') != 'TESIS':
            multiple = multi_lab.get(a['seccion_id'],0)
            if isinstance(multiple,int): common = 0 if multiple else x[j]
            else:
                common = model.new_bool_var(f'lab_comun_{j}')
                model.add(common <= x[j]); model.add(common + multiple <= 1); model.add(common >= x[j]-multiple)
        for h in range(c.inicio, c.fin):
            for key in keys:
                slots[key,c.dia,h].append(x[j])
                if not isinstance(common,int): common_slots[key,c.dia,h].append(common)
    occupied = defaultdict(dict)
    for n, ((key,day,h), values) in enumerate(sorted(slots.items())):
        present = model.new_bool_var(f'itinerario_curso_{n}')
        model.add_max_equality(present, values)
        occupied[key][day,h] = present
    common_occupied = defaultdict(dict)
    for n, ((key,day,h), values) in enumerate(sorted(common_slots.items())):
        present = model.new_bool_var(f'itinerario_comun_{n}')
        model.add_max_equality(present,values)
        common_occupied[key][day,h] = present
    same_label = defaultdict(list)
    for key, times in common_occupied.items():
        for (day,h), value in times.items(): same_label[key[0],key[2],day,h].append(value)
    for key, values in same_label.items():
        if len(values) > 1: constraints.add(sum(values) <= 1, 'H04', f'ITINERARIO_SECCION {key}')
    for left,right in combinations(sorted(occupied), 2):
        if left[0] != right[0] or left[1] == right[1] or left[2] != right[2]: continue
        for common,other in ((left,right),(right,left)):
            for day,h in sorted(common_occupied[common].keys() & occupied[other].keys()):
                constraints.add(common_occupied[common][day,h]+occupied[other][day,h] <= 1,'H04',f'COMUN_LAB {common} / {other} {day} {h}')
    gates = {}
    for left,right in combinations(sorted(occupied), 2):
        if left[0] != right[0] or left[1] == right[1] or left[2] == right[2]: continue
        pair = tuple(sorted((left[:2], right[:2])))
        if pair not in gates:
            a,b = single[left[:2]], single[right[:2]]
            if isinstance(a,int) and isinstance(b,int): gates[pair] = max(a,b)
            elif isinstance(a,int): gates[pair] = 1 if a else b
            elif isinstance(b,int): gates[pair] = 1 if b else a
            else:
                gates[pair] = model.new_bool_var(f'compartido_{len(gates)}')
                model.add_max_equality(gates[pair], [a,b])
        gate = gates[pair]
        if isinstance(gate,int) and gate == 0: continue
        for common,other in ((left,right),(right,left)):
            for day,h in sorted(common_occupied[common].keys() & occupied[other].keys()):
                constraints.add(common_occupied[common][day,h] + occupied[other][day,h] <= 2-gate,
                    'H04', f'ITINERARIO {common} / {other} {day} {h}')
    # Las repeticiones conservan su política y los conflictos nominales existentes.
    if repeated:
        legacy = dict(i.raw); legacy['politica_conflictos_operativa'] = 'CICLO_SECCION_V2'
        from dataclasses import replace
        cycle_conflicts(replace(i, raw=legacy), model, constraints, repeated, cohorts, [])


def activity_order(i, model, constraints, x):
    """H23 operativo: terminar cada tipo antes de iniciar el siguiente, por sección.

    Cortes semanales, sin productos de pares de candidatas. Una sesión de un
    patrón o sección opcional no elegido no impone su inicio ni su final.
    """
    policy = i.raw.get('orden_actividades')
    if policy is None:
        return
    if policy not in ('T_P_LAB_SEMANAL_V1', 'T_P_DURO_LAB_BLANDO_V2'):
        from .data import InputError
        raise InputError('Política de orden de actividades desconocida')
    days = {day: n for n, day in enumerate(('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'))}
    sections = defaultdict(lambda: defaultdict(lambda: defaultdict(list)))
    for j, c in enumerate(i.placements):
        if j in x:
            for sid in i.activities[c.actividad_id].get('secciones_participantes', [c.seccion_id]):
                sections[sid][c.tipo][c.session_id].append((c, x[j]))
    for section, types in sorted(sections.items()):
        if i.sections[section]['curso_id'] in i.raw.get('excepciones_orden_actividades', []):
            continue
        sequence = ('T', 'P', 'LAB') if policy == 'T_P_LAB_SEMANAL_V1' else ('T', 'P')
        present = [kind for kind in sequence if kind in types]
        for previous, following in zip(present, present[1:]):
            boundary = model.new_int_var(0, 168, f'orden_{section}_{previous}_{following}')
            for rows in types[previous].values():
                end = sum((24 * days[c.dia] + c.fin) * v for c, v in rows)
                constraints.add(end <= boundary, 'H23', f'{section}: {previous} antes de {following}')
            for rows in types[following].values():
                start = sum((24 * days[c.dia] + c.inicio) * v for c, v in rows)
                absent = 1 - sum(v for _, v in rows)
                constraints.add(start + 168 * absent >= boundary, 'H23', f'{section}: {previous} antes de {following}')
