"""Validador independiente: H01–H22 y H23 operativo sin variables CP-SAT.

No importa model, hard, domains ni sus predicados. Las comprobaciones vuelven
a leer los valores del horario y las fuentes normalizadas.
"""
from collections import Counter, defaultdict
from itertools import combinations
from math import ceil
from .policy import policy_metadata


def _declared_windows(i, a, scenario):
    """Lectura independiente de las fuentes, también para habilitar el sábado."""
    if i.raw.get('disponibilidad_actividad_v2'):
        return a['ventanas_operativas']
    rule = a['regla_disponibilidad']
    if rule in ('FLEXIBLE_INSTITUCIONAL_SUPUESTO_REGULAR', 'ESPECIAL_FLEXIBILIDAD_EXP_NO_APROBADA') and not a['es_repeticion']:
        return None
    roman = ['I','II','III','IV','V','VI','VII','VIII','IX','X']
    cycle = a['ciclo_curricular']
    label = (roman[cycle-1] if 1 <= cycle <= 10 else str(cycle)) + '-' + i.sections[a['seccion_id']]['etiqueta']
    result = []
    for w in i.raw['ventanas_disponibilidad']:
        if w['docente_id'] != a['docente_id']:
            continue
        if a['es_repeticion']:
            applies = w['escenario_fuente'] == 'REP_B_RESTRINGIDO' and w['alcance'] == f"CURSO_{a['curso_id']}"
        elif rule == 'DECLARADA_POR_SECCION':
            applies = w['escenario_fuente'] == 'A_Y_B' and w['alcance'] == label
        else:
            applies = w['escenario_fuente'] == 'A_Y_B' and w['alcance'] in ('GENERAL', f"CURSO_{a['curso_id']}")
            if scenario == 'B' and rule == 'DECLARADA_CON_EXTRA_B':
                applies |= w['escenario_fuente'] == 'SOLO_B' and w['alcance'] == 'GENERAL_EXPERIMENTAL'
        if applies:
            result.append(w)
    return result


def validate(i, outcome, scenario='B'):
    if i.raw.get('politica_repeticion_fija'):
        from .validation_fixed import validate_fixed
        return validate_fixed(i,outcome,scenario)
    checks = {f'H{k:02d}':{'estado':'PASS','violaciones':[]} for k in range(1,23)}
    extra = []
    def fail(rule, detail):
        checks[rule]['estado'] = 'FAIL'
        checks[rule]['violaciones'].append(detail)
    # Solo las instancias operativas versionadas cambian el aforo académico LAB.
    # La política histórica conserva exactamente 20 y no se reinterpreta.
    operative = i.raw.get('politica_operativa')
    lab_limit = 20
    if operative is not None:
        if (not isinstance(operative, dict) or operative.get('version') != 'HORARIOS_OPERATIVOS_V1' or
                type(operative.get('capacidad_subgrupo_autorizada')) is not int or operative['capacidad_subgrupo_autorizada'] < 1):
            fail('H15', 'Política operativa inválida')
        else:
            lab_limit = operative['capacidad_subgrupo_autorizada']
    # Cálculo propio, sin importar conflict_cycle ni el constructor CP-SAT.
    cycles={}
    cohorts={}
    for a in i.activities.values():
        if a['es_repeticion']:
            group=a.get('grupo_repeticion')
            roman=group.split('-')[0] if isinstance(group,str) else ''
            numerals=['I','II','III','IV','V','VI','VII','VIII','IX','X']
            if roman not in numerals:
                fail('H04',f"{a['id']}: falta ciclo asistente de repetición")
                cycles[a['id']]=None
            else: cycles[a['id']]=numerals.index(roman)+1
        else: cycles[a['id']]=a['ciclo_curricular']
        if a['es_repeticion']:
            parts=(a.get('grupo_repeticion') or '').split('-',1)
            section=parts[1].strip().upper() if len(parts)==2 else ''
        else: section=i.sections[a['seccion_id']]['etiqueta'].strip().upper()
        if not section: fail('H04',f"{a['id']}: falta sección de horario")
        if i.raw.get('politica_conflictos_operativa') == 'PADRON_Y_COHORTE_ACREDITADA_V1' and not a['es_repeticion']:
            section = a.get('cohorte_acreditada') or 'SECCION_CURSO_'+str(a['seccion_id'])
        cohorts[a['id']]=(cycles[a['id']],section)
        if a.get('tipo_grupo') == 'TESIS' and a.get('grupo_id'):
            cohorts[a['id']] = (cycles[a['id']], 'TESIS_'+str(a['grupo_id']))
        if i.raw.get('politica_conflictos_operativa') == 'CICLO_COMPLETO_V3' and not a['es_repeticion']:
            cohorts[a['id']] = (a['ciclo_curricular'], 'TODAS_SECCIONES')
    cycle_crossings=[]
    selected_patterns = set(outcome.get('patrones_elegidos', []))
    # La apertura se reconstruye desde las secciones elegidas, no desde el
    # conjunto de alternativas que existía durante la búsqueda.
    selected_aids = {i.patterns[p]['actividad_id'] for p in selected_patterns if p in i.patterns}
    lab_groups = defaultdict(set)
    for aid in selected_aids:
        a = i.activities[aid]
        group = a.get('subgrupo_id') or a.get('grupo_id')
        if a['tipo'] == 'LAB' and not a['es_repeticion'] and a.get('tipo_grupo') != 'TESIS' and group:
            lab_groups[a['seccion_id']].add(group)
    def alternative_lab(a):
        return i.raw.get('politica_grupos_laboratorio') == 'ALTERNATIVAS_SIN_ASIGNACION_V1' and i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' and a['tipo'] == 'LAB' and not a['es_repeticion'] and a.get('tipo_grupo') != 'TESIS' and bool(a.get('subgrupo_id') or a.get('grupo_id')) and len(lab_groups[a['seccion_id']]) > 1
    all_sections_with_acts = {sid for a in i.activities.values() for sid in a.get('secciones_participantes', [a['seccion_id']])}
    selected_sections = {sid for aid in selected_aids for sid in i.activities[aid].get('secciones_participantes', [i.activities[aid]['seccion_id']])}
    opens = defaultdict(set)
    repeated_sections = {sid for a in i.activities.values() if a['es_repeticion'] for sid in a.get('secciones_participantes', [a['seccion_id']])}
    cycles_by_section = {sid: a['ciclo_curricular'] for a in i.activities.values() if not a['es_repeticion'] for sid in a.get('secciones_participantes', [a['seccion_id']])}
    for s in i.sections.values():
        if s['id'] in repeated_sections or s.get('es_repeticion') or s['id'] in all_sections_with_acts and s['id'] not in selected_sections: continue
        label = 'ASESORIA' if s.get('tipo_grupo') == 'TESIS' else s['etiqueta'].strip().upper()
        cycle = cycles_by_section.get(s['id'], i.courses[s['curso_id']].get('ciclo_curricular'))
        if cycle is not None: opens[cycle, s['curso_id']].add(label)
    # Currículo, demanda y estructura, independientemente del prechequeo.
    for course in i.courses.values():
        sections = [s for s in i.sections.values() if s['curso_id']==course['id']]
        if sum(s['estudiantes'] for s in sections) != course['demanda_experimental']:
            fail('H13',str(course['id']))
        if len(sections) != course['n_secciones']:
            fail('H14',f"{course['id']}: secciones declaradas")
        compatible = {rid for a in i.activities.values() if a['curso_id']==course['id']
                      and a['tipo'] in ('T','P') and a['requiere_ambiente'] for rid in a['ambientes_candidatos']
                      if i.rooms[rid]['tipo']==a['tipo_ambiente']}
        if compatible and len(sections) < ceil(course['demanda_experimental']/max(i.rooms[r]['capacidad'] for r in compatible)):
            fail('H14',str(course['id']))
        for section in ([] if i.raw.get('alcance_parcial') else sections):
            acts = [a for a in i.activities.values() if section['id'] in a.get('secciones_participantes', [a['seccion_id']])]
            for typ,key in (('T','ht'),('P','hp')):
                aa = [a for a in acts if a['tipo']==typ]
                if sum(a['horas_semanales'] for a in aa) != course[key]:
                    fail('H09',f"{section['id']} {typ}: currículo")
                if any((len(a['estudiantes_por_seccion'][section['id']]) if 'sesion_fisica_id' in a
                        else a['estudiantes']) != section['estudiantes'] for a in aa):
                    fail('H13',f"{section['id']} {typ}: tamaño")
            groups = defaultdict(list)
            for a in acts:
                if a['tipo']=='LAB': groups[a.get('subgrupo_id',a['id'])].append(a)
            if bool(groups) != bool(course['hl']): fail('H18',section['id'])
            if course['hl']:
                section_limit = section.get('capacidad_laboratorio') or lab_limit
                expected = section.get('cantidad_grupos_laboratorio') or ceil(section['estudiantes']/section_limit)
                if expected < ceil(section['estudiantes']/section_limit) or len(groups) != expected: fail('H16',section['id'])
                if sum(aa[0]['estudiantes'] for aa in groups.values()) != section['estudiantes']:
                    fail('H17',section['id'])
                for gid,aa in groups.items():
                    if len({a['estudiantes'] for a in aa})!=1 or not(0<aa[0]['estudiantes']<=section_limit): fail('H15',str(gid))
                    if sum(a['horas_semanales'] for a in aa)!=course['hl']: fail('H18',str(gid))
                    if all('estudiantes_ids' in a for a in aa) and any(set(a['estudiantes_ids'])!=set(aa[0]['estudiantes_ids']) for a in aa):
                        fail('H06',str(gid))
                if all('estudiantes_ids' in aa[0] for aa in groups.values()):
                    sets=[set(aa[0]['estudiantes_ids']) for aa in groups.values()]
                    if sum(map(len,sets))!=len(set().union(*sets)): fail('H06',section['id'])
                    for whole in (a for a in acts if a['tipo'] in ('T','P') and 'estudiantes_ids' in a):
                        if set().union(*sets)!=set(whole['estudiantes_ids']): fail('H17',section['id'])
    selected_patterns = outcome.get('patrones_elegidos',[])
    pattern_counts = Counter(selected_patterns)
    for pid,n in pattern_counts.items():
        if pid not in i.patterns or n!=1: fail('H01',f'Patrón inválido/duplicado: {pid}')
    for a in i.activities.values():
        chosen=[pid for pid in selected_patterns if pid in i.patterns and i.patterns[pid]['actividad_id']==a['id']]
        if len(chosen)!=1: fail('H01',f"{a['id']}: {len(chosen)} patrones")
        for p in (p for p in i.patterns.values() if p['actividad_id']==a['id']):
            ss=sorted([s for s in i.sessions.values() if s['patron_id']==p['id']],key=lambda s:s['numero'])
            if ([s['duracion'] for s in ss]!=p['duraciones'] or len(ss)!=p['n_sesiones']
                    or [s['numero'] for s in ss]!=list(range(1,len(ss)+1))): fail('H10',p['id'])
            if sum(s['duracion'] for s in ss)!=a['horas_semanales'] or p['horas_totales']!=a['horas_semanales']:
                fail('H09',p['id'])
    sessions=outcome.get('sesiones_elegidas',[])
    counts=Counter(c.get('session_id') for c in sessions)
    for s in i.sessions.values():
        expected=1 if s['patron_id'] in selected_patterns else 0
        if counts[s['id']]!=expected: fail('H01',f"{s['id']}: {counts[s['id']]} sesiones, esperaba {expected}")
    # Comprobación independiente del constructor: intervalos reales exportados.
    if i.raw.get('orden_actividades') in ('T_P_LAB_SEMANAL_V1', 'T_P_DURO_LAB_BLANDO_V2'):
        checks['H23'] = {'estado': 'PASS', 'violaciones': []}
        if i.raw.get('excepciones_orden_actividades'):
            checks['H23']['excepciones_cursos'] = [{'curso_id': c, 'curso': i.courses[c]['nombre']} for c in i.raw['excepciones_orden_actividades']]
        week = {day: n for n, day in enumerate(('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'))}
        ordered = defaultdict(lambda: defaultdict(list))
        for row in sessions:
            a = i.activities.get(row.get('actividad_id'))
            if a and row.get('dia') in week:
                for sid in a.get('secciones_participantes', [a['seccion_id']]):
                    ordered[sid][a['tipo']].append((24 * week[row['dia']] + row['inicio'], 24 * week[row['dia']] + row['fin']))
        for section, types in ordered.items():
            if i.sections[section]['curso_id'] in i.raw.get('excepciones_orden_actividades', []):
                continue
            sequence = ('T', 'P', 'LAB') if i.raw['orden_actividades'] == 'T_P_LAB_SEMANAL_V1' else ('T', 'P')
            present = [kind for kind in sequence if types[kind]]
            for previous, following in zip(present, present[1:]):
                if max(end for _, end in types[previous]) > min(start for start, _ in types[following]):
                    fail('H23', f'{section}: debe terminar {previous} antes de iniciar {following}')
    hours=Counter()
    occupancy=[]
    candidate_map={c.id:c for c in i.placements}
    candidate_ids=[]
    cal=i.raw['calendario']
    for row in sessions:
        ident=row.get('id')
        candidate_ids.append(ident)
        aid=row.get('actividad_id'); sid=row.get('session_id')
        if aid not in i.activities or sid not in i.sessions:
            fail('H01',f'Referencia de horario inexistente: {ident}')
            continue
        a=i.activities[aid]; s=i.sessions[sid]
        if (s['actividad_id']!=aid or row.get('patron_id')!=s['patron_id']
                or row.get('docente_id')!=a['docente_id'] or row.get('seccion_id')!=a['seccion_id']
                or row.get('tipo')!=a['tipo']): fail('H07',str(ident))
        # La pertenencia exacta al dominio exportado también se comprueba;
        # el resto de reglas se calcula sobre el horario, no sobre la candidata.
        original=candidate_map.get(ident)
        if original is None or any(row.get(k)!=getattr(original,k) for k in original.__dataclass_fields__):
            fail('H10',f'{ident}: no coincide exactamente con dominio de entrada')
        start,end=row.get('inicio'),row.get('fin')
        if type(start) is not int or type(end) is not int or end<=start:
            fail('H10',f'{ident}: intervalo inválido')
            continue
        day=row.get('dia'); room=row.get('ambiente_id')
        hours[aid]+=end-start
        if end-start!=s['duracion']: fail('H10',str(ident))
        # Independiente de las candidatas: solo la repetición declarada puede
        # utilizar sábado fuera del calendario regular.
        exceptional = (i.raw.get('politica_sabado_repeticion') == 'REPETICION_SABADO_DECLARADO_V1' and a['es_repeticion'] and day == 'SABADO')
        windows = _declared_windows(i, a, scenario)
        declared_saturday = exceptional and windows is not None and any(w['dia']=='SABADO' and w['inicio']<w['fin'] for w in windows)
        if (day not in cal['dias'] and not declared_saturday) or exceptional and not declared_saturday or start<cal['hora_inicial'] or end>cal['hora_final']:
            fail('H20',str(ident))
        if start<cal['receso']['fin'] and end>cal['receso']['inicio']: fail('H19',str(ident))
        permitted={b['inicio'] for b in cal['bloques_hora_base'] if b['fin']==b['inicio']+1}
        if any(h not in permitted for h in range(start,end)): fail('H10',f'{ident}: bloques')
        if day=='SABADO' and not declared_saturday and not cal['sabados_por_ciclo'].get(str(a['ciclo_politica_sabado']),False):
            extra.append({'regla':'SABADO','detalle':ident})
        if day == 'SABADO' and not exceptional and i.raw.get('politica_sabado_repeticion') == 'REPETICION_SABADO_DECLARADO_V1':
            if cal.get('sabados') == 'NINGUNO' or cal.get('sabados') == 'SELECCIONADOS' and not any(x['ciclo'] == a['ciclo_politica_sabado'] and (not x['secciones'] or i.sections[a['seccion_id']]['etiqueta'] in x['secciones']) for x in cal.get('sabados_alcances', [])):
                extra.append({'regla':'SABADO','detalle':ident})
        if bool(room)!=a['requiere_ambiente']: fail('H11',str(ident))
        if room:
            if room not in i.rooms or room not in a['ambientes_candidatos']:
                fail('H11',str(ident))
            elif i.rooms[room]['tipo']!=a['tipo_ambiente']: fail('H11',str(ident))
            if room in i.rooms and i.rooms[room]['capacidad']<a['estudiantes']: fail('H12',str(ident))
        covered={h for w in windows or [] if w['dia']==day for h in range(w['inicio'],w['fin'])}
        if windows is not None and not set(range(start,end))<=covered: fail('H08',str(ident))
        for b in i.raw['bloqueos']:
            if b['dia']!=day or end<=b['inicio'] or start>=b['fin']: continue
            scope=b['alcance']
            blocked=scope in ('GLOBAL','GLOBAL_CONDICIONAL')
            if scope=='AMBIENTE':
                blocked=bool(room) and (b['ambiente']==room or (room in i.rooms and b['ambiente']==i.rooms[room]['nombre']))
            if scope=='DOCENTE': blocked=b['docente_id']==a['docente_id']
            if scope=='SECCION': blocked=b['seccion_id']==a['seccion_id']
            if scope=='ACTIVIDAD': blocked=b['actividad_id']==a['id']
            if scope=='CURSO': blocked=b['curso_id']==a['curso_id']
            if scope=='GRUPO': blocked=b['grupo_id']==a.get('grupo_id')
            if scope=='CICLO': blocked=b['ciclo']==a['ciclo_curricular']
            if scope=='COHORTE':
                # Cálculo independiente del predicado usado por el constructor.
                if not a['es_repeticion']:
                    bc=a['ciclo_curricular'];bl=i.sections[a['seccion_id']]['etiqueta']
                    blocked=bc==b['ciclo'] and bl.strip().upper() in b['secciones_horario']
                elif b.get('incluir_repeticiones',False):
                    blocked=cycles[a['id']]==b['ciclo'] and cohorts[a['id']][1] in b['secciones_horario']
            if blocked: fail('H22' if scope=='AMBIENTE' else 'H21',f"{ident}: {b['id']}")
        occupancy.append((row,a,start,end))
    if len(candidate_ids)!=len(set(candidate_ids)): fail('H01','Colocaciones duplicadas en horario')
    for a in i.activities.values():
        if hours[a['id']]!=a['horas_semanales']: fail('H09',f"{a['id']}: {hours[a['id']]} / {a['horas_semanales']}")
        if a['tipo']=='LAB' and hours[a['id']]!=a['horas_semanales']:
            fail('H18',f"{a['id']}: laboratorio sin carga completa")
    pairs={frozenset(p) for p in i.raw.get('conflictos_estudiantes',[])}
    for (c,a,start,end),(e,b,begin,finish) in combinations(occupancy,2):
        if c['dia']!=e['dia'] or end<=begin or start>=finish: continue
        detail=f"{c['id']} / {e['id']} {c['dia']} [{max(start,begin)},{min(end,finish)})"
        if c['docente_id']==e['docente_id']: fail('H02',detail)
        if c['ambiente_id'] and c['ambiente_id']==e['ambiente_id']: fail('H03',detail)
        incompatible = cohorts[a['id']] == cohorts[b['id']]
        if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' and not a['es_repeticion'] and not b['es_repeticion']:
            sa,sb = i.sections[a['seccion_id']],i.sections[b['seccion_id']]
            la = 'ASESORIA' if sa.get('tipo_grupo') == 'TESIS' else sa['etiqueta'].strip().upper()
            lb = 'ASESORIA' if sb.get('tipo_grupo') == 'TESIS' else sb['etiqueta'].strip().upper()
            incompatible = cycles[a['id']] == cycles[b['id']] and (la == lb or len(opens[cycles[a['id']],a['curso_id']]) == 1 or len(opens[cycles[b['id']],b['curso_id']]) == 1)
            if alternative_lab(a) and alternative_lab(b): incompatible = False
            if 'sesion_fisica_id' in a or 'sesion_fisica_id' in b:
                incompatible = any(
                    i.sections[x]['etiqueta'].strip().upper() == i.sections[y]['etiqueta'].strip().upper()
                    or len(opens[cycles[a['id']], a['curso_id']]) == 1
                    or len(opens[cycles[b['id']], b['curso_id']]) == 1
                    for x in a.get('secciones_participantes', [a['seccion_id']])
                    for y in b.get('secciones_participantes', [b['seccion_id']])
                ) and cycles[a['id']] == cycles[b['id']]
        if a['curso_id']!=b['curso_id'] and cycles[a['id']] is not None and incompatible:
            fail('H04',f"CICLO_SECCION {cohorts[a['id']]}: {detail}")
            cycle_crossings.append({'ciclo':cycles[a['id']],'seccion_horario':cohorts[a['id']][1],
                'seccion_1': i.sections[a['seccion_id']]['etiqueta'], 'seccion_2': i.sections[b['seccion_id']]['etiqueta'],
                'curso_1':a['curso_id'],'curso_2':b['curso_id'],'detalle':detail})
        if set(a.get('secciones_participantes', [a['seccion_id']])) & set(b.get('secciones_participantes', [b['seccion_id']])):
            if a['tipo']!='LAB' and b['tipo']!='LAB': fail('H04',detail)
            elif (a['tipo']=='LAB')!=(b['tipo']=='LAB'): fail('H05',detail)
            elif a.get('subgrupo_id',a['id'])==b.get('subgrupo_id',b['id']): fail('H06',detail)
        if frozenset((a['id'],b['id'])) in pairs or set(a.get('estudiantes_ids',[])) & set(b.get('estudiantes_ids',[])):
            fail('H04',detail)
    limits=[]
    all_rosters=all('estudiantes_ids' in a for a in i.activities.values())
    if not all_rosters and not i.raw.get('cobertura_conflictos',{}).get('completa',False):
        detail = ('Actividades comunes protegidas por sección y cursos de sección única. LAB alternativos pueden coincidir sin conflictos de recursos ni padrones conocidos; no se garantiza cualquier combinación posterior de grupos.' if i.raw.get('politica_grupos_laboratorio') == 'ALTERNATIVAS_SIN_ASIGNACION_V1' else
            'Sin cruces dentro de cada sección ni con cursos de sección única del ciclo. Otros cambios entre secciones son compatibilidad SOFT; matrícula comprueba la selección real.' if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' else
            'No cruce entre cursos regulares distintos del mismo ciclo, incluyendo todas sus secciones y grupos. Faltan matrícula y compatibilidades individuales para otros ciclos y repeticiones.' if i.raw.get('politica_conflictos_operativa') == 'CICLO_COMPLETO_V3' else
            'No cruce del mismo ciclo/sección implementado; A/B pueden solaparse. Faltan matrícula y correspondencias de grupos especiales o secciones consolidadas para conflictos adicionales.')
        limits.append({'regla':'H04','detalle':detail})
    if not all_rosters: limits.append({'regla':'H06','detalle':'Disyunción de padrones de subgrupos no verificable; cobertura numérica sí comprobada.'})
    if i.raw.get('metadata',{}).get('calidad')=='SUPUESTOS_EXPERIMENTALES_ETIQUETADOS':
        limits.append({'regla':'H07','detalle':'Asignación preservada como entrada contrafactual, sin acreditación institucional.'})
    if any(a['regla_disponibilidad'] in ('DECLARADA_CON_EXTRA_B','REPETICION_SOLO_M0_RESTRINGIDA','ESPECIAL_FLEXIBILIDAD_EXP_NO_APROBADA','FLEXIBLE_INSTITUCIONAL_SUPUESTO_REGULAR') for a in i.activities.values()):
        limits.append({'regla':'H08','detalle':'Política experimental; disponibilidad formal no acreditada para todas las actividades.'})
    for lim in limits:
        check=checks[lim['regla']]
        if check['estado']=='PASS': check['estado']='PARCIAL_DATOS'
        check.setdefault('limitaciones',[]).append(lim['detalle'])
    ok=not extra and all(v['estado']!='FAIL' for v in checks.values())
    from .coverage import assess
    coverage=assess(i,checks)
    return {'estado':'VALIDO_EN_ALCANCE_EXPERIMENTAL' if ok and limits else 'VALIDO_HARD' if ok else 'INVALIDO',
            'cumple_reglas_con_datos_disponibles':ok,
            'certificacion_tecnica_con_datos_completos':ok and not limits,
            'certificacion_completa_H01_H22':ok and not limits and coverage['acreditacion_institucional_completa'],
            'cobertura_institucional':coverage,
            'escenario':scenario,'horas_programadas':sum(hours.values()),'n_sesiones':len(sessions),
            'politica_conflictos':policy_metadata(i),'cruces_cursos_mismo_ciclo':cycle_crossings,
            'certificacion_tecnica_sin_cruces_por_ciclo':ok and not cycle_crossings,
            'n_actividades':len(hours),'reglas':checks,'otras_violaciones':extra,'limitaciones':limits,
            'fuentes_sha256':i.hashes,'bloqueos_institucionales':i.raw.get('bloqueos_institucionales')}
