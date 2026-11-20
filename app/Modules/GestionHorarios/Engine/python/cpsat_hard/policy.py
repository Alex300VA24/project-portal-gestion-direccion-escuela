"""Política de conflictos confirmada por el investigador en esta conversación."""
from collections import defaultdict
from .data import InputError

CONFLICT_POLICY = {
    'version': 4,
    'cursos_distintos_mismo_ciclo_sin_solape': True,
    'alcance_secciones': 'MISMA_SECCION_DE_HORARIO_A_B_PUEDEN_SOLAPARSE',
    'ciclo_regular': 'ciclo_curricular',
    'ciclo_repeticion': 'grupo_repeticion',
    'repeticiones':'SESIONES_FISICAS_M0_FIJAS_CON_AMBIENTES_Y_TIPOS_DE_FUENTE',
    'conflictos_estudiantiles_repeticion':'SOLO_MATRICULA_O_PARES_CONFIRMADOS_SIN_COHORTE_III_R_INFERIDA',
    'fuente': 'INSTRUCCION_EXPRESA_USUARIO_Y_CONFIRMACION_CICLO_ASISTENTE',
}


def policy_metadata(i):
    if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4':
        return {**CONFLICT_POLICY, 'version': 7 if lab_alternatives_enabled(i) else 6, 'alcance_secciones': 'MISMA_SECCION_Y_CURSOS_DE_SECCION_UNICA',
            'laboratorios': i.raw.get('politica_grupos_laboratorio', 'HISTORICA_SIN_EXCEPCION'),
            'compatibilidad_adicional': 'SOFT_ENTRE_ALTERNATIVAS_MULTISECCION',
            'garantia': 'ITINERARIOS_POR_SECCION_MAS_CURSOS_COMPARTIDOS; MATRICULA_VALIDA_SU_SELECCION_REAL'}
    if i.raw.get('politica_conflictos_operativa') == 'CICLO_COMPLETO_V3':
        return {**CONFLICT_POLICY, 'version': 5,
            'alcance_secciones': 'TODAS_LAS_SECCIONES_DE_CURSOS_REGULARES_DISTINTOS_DEL_MISMO_CICLO',
            'garantia': 'COMBINACIONES_DE_SECCIONES_DEL_CICLO; OTROS_CICLOS_Y_REPETICIONES_REQUIEREN_SELECCION_INDIVIDUAL'}
    return CONFLICT_POLICY


LAB_ALTERNATIVES = 'ALTERNATIVAS_SIN_ASIGNACION_V1'


def lab_alternatives_enabled(i):
    return i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' and i.raw.get('politica_grupos_laboratorio') == LAB_ALTERNATIVES


def alternative_lab_activities(i):
    """Grupos identificados, sin deducir estudiantes por el número de LAB."""
    groups = defaultdict(set)
    for a in i.activities.values():
        if a['tipo'] == 'LAB' and not a['es_repeticion'] and a.get('tipo_grupo') != 'TESIS':
            group = a.get('subgrupo_id') or a.get('grupo_id')
            if group: groups[a['seccion_id']].add(group)
    return {a['id'] for a in i.activities.values() if a['tipo'] == 'LAB' and not a['es_repeticion'] and
            a.get('tipo_grupo') != 'TESIS' and (a.get('subgrupo_id') or a.get('grupo_id')) and len(groups[a['seccion_id']]) > 1}


def section_label(section):
    """Los grupos de asesoría son alternativas de un curso, no letras A/B."""
    return 'ASESORIA' if section.get('tipo_grupo') == 'TESIS' else section['etiqueta'].strip().upper()


def opened_sections(i):
    result = defaultdict(set)
    repeated = {a['seccion_id'] for a in i.activities.values() if a['es_repeticion']}
    cycles = {a['seccion_id']: a['ciclo_curricular'] for a in i.activities.values() if not a['es_repeticion']}
    for s in i.sections.values():
        if s['id'] in repeated or s.get('es_repeticion'):
            continue
        cycle = cycles.get(s['id'], i.courses[s['curso_id']].get('ciclo_curricular'))
        if cycle is None: continue
        result[cycle, s['curso_id']].add(section_label(s))
    return result


def conflict_cohort(i, activity):
    """Identidad del horario: ciclo asistente y sección, nunca curso_id."""
    cycle=conflict_cycle(activity)
    if i.raw.get('politica_conflictos_operativa') == 'CICLO_COMPLETO_V3' and not activity['es_repeticion']:
        return cycle, 'TODAS_SECCIONES'
    if activity.get('tipo_grupo') == 'TESIS' and activity.get('grupo_id'):
        return cycle, 'TESIS_'+str(activity['grupo_id'])
    if i.raw.get('politica_conflictos_operativa') == 'PADRON_Y_COHORTE_ACREDITADA_V1' and not activity['es_repeticion']:
        return cycle, activity.get('cohorte_acreditada') or 'SECCION_CURSO_'+str(activity['seccion_id'])
    if activity['es_repeticion']:
        if i.raw.get('politica_repeticion_fija'):
            return cycle,'REPETICION_FIJA_CURSO_'+str(activity['curso_id'])
        parts=activity['grupo_repeticion'].split('-',1)
        label=parts[1].strip().upper() if len(parts)==2 else ''
    else:
        label=i.sections[activity['seccion_id']]['etiqueta'].strip().upper()
    if not label:
        raise InputError(f"{activity['id']}: falta sección de horario")
    return cycle,label


def conflict_cycle(activity):
    if not activity['es_repeticion']:
        return activity['ciclo_curricular']
    group = activity.get('grupo_repeticion')
    roman = dict(zip(('I','II','III','IV','V','VI','VII','VIII','IX','X'),range(1,11)))
    prefix = group.split('-')[0] if isinstance(group,str) else ''
    if prefix not in roman:
        raise InputError(f"{activity['id']}: repetición sin ciclo asistente reconocible en grupo_repeticion")
    return roman[prefix]


def cycle_capacity_bounds(i, cohorts, blocked_ids):
    """Cotas necesarias: horas de un curso no pueden compartirse con otro.

    Dentro del curso sí pueden existir secciones/LAB paralelos. Por eso no se
    suman indiscriminadamente todas sus horas: se usa la mayor carga de un
    docente o de una sección (T/P más el subgrupo LAB de mayor carga).
    """
    targets = {a['id']: [cohorts[a['id']]] for a in i.activities.values()}
    if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4':
        opened = opened_sections(i)
        labels = defaultdict(set)
        for (cycle, _), values in opened.items(): labels[cycle].update(values)
        for cycle, values in labels.items():
            if len(values) > 1: values.discard('ASESORIA')
        for a in i.activities.values():
            if not a['es_repeticion']:
                cycle = a['ciclo_curricular']
                own = section_label(i.sections[a['seccion_id']])
                single = len(opened[cycle, a['curso_id']]) == 1
                targets[a['id']] = [(cycle, label) for label in labels[cycle] if single or own == label]
    groups = defaultdict(list)
    slots = defaultdict(set)
    alternative = alternative_lab_activities(i) if lab_alternatives_enabled(i) else set()
    for a in i.activities.values():
        if a['id'] in alternative: continue
        if i.raw.get('politica_repeticion_fija') and a['es_repeticion']: continue
        for cohort in targets[a['id']]: groups[(cohort,a['curso_id'])].append(a)
    for c in i.placements:
        if c.actividad_id in alternative: continue
        if i.raw.get('politica_repeticion_fija') and i.activities[c.actividad_id]['es_repeticion']: continue
        if c.id not in blocked_ids:
            for cohort in targets[c.actividad_id]:
                key = (cohort,i.activities[c.actividad_id]['curso_id'])
                slots[key].update((c.dia,h) for h in range(c.inicio,c.fin))
    by_cycle = defaultdict(list)
    for (cycle,course),aa in sorted(groups.items()):
        teachers = defaultdict(int)
        sections = defaultdict(lambda: {'tp':0,'labs':defaultdict(int)})
        for a in aa:
            teachers[a['docente_id']] += a['horas_semanales']
            section = sections[a['seccion_id']]
            if a['tipo']=='LAB': section['labs'][a.get('subgrupo_id',a['id'])] += a['horas_semanales']
            else: section['tp'] += a['horas_semanales']
        section_bounds = {s:row['tp']+max(row['labs'].values(),default=0) for s,row in sections.items()}
        bound = max(max(teachers.values(),default=0),max(section_bounds.values(),default=0))
        by_cycle[cycle].append({'curso_id':course,'minimo_horas_ocupadas':bound,
                               'cargas_por_docente':dict(teachers),'cotas_por_seccion':section_bounds,
                               'slots_permitidos':sorted(slots[(cycle,course)])})
    result=[]
    for cycle,rows in sorted(by_cycle.items()):
        union = {tuple(slot) for row in rows for slot in row['slots_permitidos']}
        minimum = sum(row['minimo_horas_ocupadas'] for row in rows)
        result.append({'ciclo':cycle[0],'seccion_horario':cycle[1],'minimo_horas_cursos_sin_cruce':minimum,
                       'alcance': 'ACTIVIDADES_COMUNES' if lab_alternatives_enabled(i) else 'CURSOS_COMPLETOS',
                       'horas_unicas_en_dominio':len(union),'deficit':max(0,minimum-len(union)),
                       'cursos':rows})
    return result
