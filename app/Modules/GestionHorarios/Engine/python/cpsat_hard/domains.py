"""Políticas de dominio explícitas, sin ampliar ventanas observadas."""
from .data import InputError
from .calendar_policy import allowed_day

ROMAN = dict(enumerate(('I','II','III','IV','V','VI','VII','VIII','IX','X'), 1))
FLEXIBLE = {'FLEXIBLE_INSTITUCIONAL_SUPUESTO_REGULAR','ESPECIAL_FLEXIBILIDAD_EXP_NO_APROBADA'}
RESTRICTED = {'DECLARADA_POR_SECCION','DECLARADA_ESTRICTA','DECLARADA_CON_EXTRA_B',
              'ESPECIAL_CON_DISP_DECLARADA','REPETICION_SOLO_M0_RESTRINGIDA'}


def windows_for(i, a, scenario):
    if i.raw.get('disponibilidad_actividad_v2'):
        return a['ventanas_operativas']
    rule = a['regla_disponibilidad']
    if rule not in FLEXIBLE | RESTRICTED:
        raise InputError(f"{a['id']}: política de disponibilidad desconocida: {rule}")
    if rule in FLEXIBLE:
        if a['es_repeticion']:
            raise InputError(f"{a['id']}: repetición no puede heredar flexibilidad regular")
        return None
    label = ROMAN.get(a['ciclo_curricular'], str(a['ciclo_curricular']))+'-'+i.sections[a['seccion_id']]['etiqueta']
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


def violations(i, c, scenario):
    """Cada rechazo conserva su regla y evidencia; nunca se borra del CSV."""
    a = i.activities[c.actividad_id]
    cal = i.raw['calendario']
    result = []
    def fail(rule, detail):
        result.append({'regla': rule, 'detalle': detail})
    ws = windows_for(i,a,scenario)
    day_ok = allowed_day(cal, c.dia, repetition=a['es_repeticion'], windows=ws,
                         cycle=a['ciclo_politica_sabado'], label=i.sections[a['seccion_id']]['etiqueta'], policy=i.raw.get('politica_sabado_repeticion'))
    if not day_ok or not (cal['hora_inicial'] <= c.inicio < c.fin <= cal['hora_final']):
        fail('H20','Fuera del calendario/jornada')
    recess = cal['receso']
    if c.inicio < recess['fin'] and c.fin > recess['inicio']:
        fail('H19','Cruza el receso')
    allowed_slots = {(b['inicio'], b['fin']) for b in cal['bloques_hora_base']}
    if any((h,h+1) not in allowed_slots for h in range(c.inicio,c.fin)):
        fail('H10','No ocupa bloques base consecutivos permitidos')
    if c.dia == 'SABADO' and not day_ok:
        fail('SABADO','Sábado deshabilitado para ciclo de política')
    if a['requiere_ambiente']:
        if c.ambiente_id not in a['ambientes_candidatos']:
            fail('H11','Ambiente fuera de lista compatible')
        if c.ambiente_id in i.rooms:
            room = i.rooms[c.ambiente_id]
            if room['tipo'] != a['tipo_ambiente']:
                fail('H11','Tipo de ambiente incompatible')
            if room['capacidad'] < a['estudiantes']:
                fail('H12','Aforo insuficiente')
        else:
            fail('H11','Falta ambiente requerido')
    elif c.ambiente_id:
        fail('H11','Actividad sin recurso compartido recibió ambiente')
    if ws is not None and any(not any(w['dia']==c.dia and w['inicio']<=h<w['fin'] for w in ws) for h in range(c.inicio,c.fin)):
        fail('H08','Fuera de unión de ventanas aplicables al curso/sección/escenario')
    for b in i.raw['bloqueos']:
        if b['dia'] != c.dia or not(c.inicio < b['fin'] and b['inicio'] < c.fin):
            continue
        scope = b['alcance']
        applies = scope in ('GLOBAL','GLOBAL_CONDICIONAL')
        if scope == 'AMBIENTE' and c.ambiente_id:
            applies = b['ambiente'] in (c.ambiente_id,i.rooms[c.ambiente_id]['nombre'])
        if scope == 'DOCENTE': applies = b['docente_id'] == a['docente_id']
        if scope == 'SECCION': applies = b['seccion_id'] == a['seccion_id']
        if scope == 'ACTIVIDAD': applies = b['actividad_id'] == a['id']
        if scope == 'CURSO': applies = b['curso_id'] == a['curso_id']
        if scope == 'GRUPO': applies = b['grupo_id'] == a.get('grupo_id')
        if scope == 'CICLO': applies = b['ciclo'] == a['ciclo_curricular']
        if scope == 'COHORTE':
            from .institutional_blocks import targets
            applies = targets(i,b,a)
        if applies:
            fail('H22' if scope=='AMBIENTE' else 'H21',b['id'])
    return result
