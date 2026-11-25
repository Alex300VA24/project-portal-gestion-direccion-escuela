"""Sesiones físicas y ocupación temporal; nunca reconstruye matrícula ausente."""
from collections import defaultdict
from itertools import combinations
from cpsat_hard.policy import conflict_cycle
from cpsat_hard.validation import validate


def validated_events(instance, outcome):
    if outcome.get('estado') not in ('FEASIBLE', 'OPTIMAL'):
        raise ValueError('No hay una solución FEASIBLE/OPTIMAL para exportar')
    if outcome.get('fuentes_sha256') != instance.hashes:
        raise ValueError('Los hashes de entrada no corresponden a la solución')
    report = validate(instance, outcome, outcome.get('escenario', 'B'))
    if not report['cumple_reglas_con_datos_disponibles']:
        raise ValueError('La solución no supera el validador independiente HARD')
    events = []
    sources = [(e, [e['actividad_id']], 'CP_SAT') for e in outcome.get('sesiones_elegidas', [])]
    sources += [(e, e['actividad_ids'], 'REPETICION_FIJA_M0')
                for e in outcome.get('sesiones_fijas_repeticion', [])]
    for e, aids, origin in sources:
        a = instance.activities[aids[0]]
        cycle = conflict_cycle(a)
        section = instance.sections[e['seccion_id']]['etiqueta']
        if a['es_repeticion']:
            section = a['grupo_repeticion'].split('-', 1)[1]
        # Las tutorías no acreditan una sección común ni consumen ambiente.
        group = (cycle, str(section)) if a['requiere_ambiente'] else (cycle, 'TESIS_' + a['docente_id'])
        rid = e['ambiente_id']
        events.append({'id':e['id'], 'actividad_ids':list(aids), 'curso_id':a['curso_id'],
            'curso':instance.courses[a['curso_id']]['nombre'], 'ciclo':cycle, 'seccion':str(section),
            'seccion_id':e['seccion_id'], 'subgrupo':('NO_IDENTIFICADO_EN_M0' if origin=='REPETICION_FIJA_M0'
                else str(a.get('subgrupo_id', a['id']))) if e['tipo']=='LAB' else '',
            'tipo':e['tipo'], 'docente_id':e['docente_id'],
            'docente':instance.teachers[e['docente_id']].get('nombre', e['docente_id']),
            'ambiente_id':rid, 'ambiente':instance.rooms[rid]['nombre'] if rid else 'SIN AMBIENTE COMPARTIDO',
            'dia':e['dia'], 'inicio':e['inicio'], 'fin':e['fin'],
            'duracion_h':e['fin']-e['inicio'], 'origen':origin, 'grupo':group})
    days = instance.raw['calendario']['dias']
    events.sort(key=lambda e:(days.index(e['dia']),e['inicio'],e['fin'],e['id']))
    if len({e['id'] for e in events}) != len(events):
        raise ValueError('Identificadores de sesión física duplicados')
    for n, e in enumerate(events, 1):
        e['ref'] = f'S{n:03d}'
    if sum(e['duracion_h'] for e in events) != report['horas_programadas']:
        raise ValueError('Cobertura de horas física distinta de la validación')
    return events, report


def reservations(instance):
    """Solo bloqueos que consumen un ambiente; receso no es una reserva."""
    result = []
    for b in instance.raw['bloqueos']:
        if b['alcance'] != 'AMBIENTE':
            continue
        rooms = [r for r in instance.rooms.values() if b['ambiente'] in (r['id'], r['nombre'])]
        if len(rooms) != 1:
            raise ValueError('Reserva sin ambiente inequívoco: '+b['id'])
        result.append({'id':'RESERVA_'+b['id'], 'ref':'R_'+b['id'], 'curso':b.get('actividad', b['id']),
            'tipo':'RESERVA', 'origen':'RESERVA_INSTITUCIONAL', 'dia':b['dia'],
            'inicio':b['inicio'], 'fin':b['fin'], 'duracion_h':b['fin']-b['inicio'],
            'ambiente_id':rooms[0]['id'], 'ambiente':rooms[0]['nombre'],
            'docente':'No indicado', 'ciclo':'', 'seccion':'', 'subgrupo':''})
    return sorted(result, key=lambda e:e['id'])


def slots(event):
    return {(event['dia'], h) for h in range(event['inicio'],event['fin'])}


def utilization(instance, events, reserved):
    cal = instance.raw['calendario']
    recess = cal['receso']
    base = {(d,b['inicio']) for d in cal['dias'] for b in cal['bloques_hora_base']
            if not(b['inicio'] < recess['fin'] and b['fin'] > recess['inicio'])}
    # Cierres globales reducen el calendario; no se cuentan dos veces como reserva.
    closed = set()
    for b in instance.raw['bloqueos']:
        if b['alcance'] in ('GLOBAL','GLOBAL_CONDICIONAL'):
            closed |= slots(b)
    available = base - closed
    rows = []
    for rid, room in sorted(instance.rooms.items()):
        teaching = [e for e in events if e['ambiente_id']==rid]
        bookings = [e for e in reserved if e['ambiente_id']==rid]
        used = set().union(*(slots(e) for e in teaching)) if teaching else set()
        booked = set().union(*(slots(e) for e in bookings)) if bookings else set()
        occupied = used | booked
        model_inside = used & available
        reserve_inside = booked & available
        rows.append({'ambiente_id':rid, 'ambiente':room['nombre'], 'tipo':room['tipo'],
            'capacidad':room['capacidad'], 'horas_disponibles':len(available),
            'horas_modelo_union':len(model_inside), 'horas_reservas_union':len(reserve_inside),
            'horas_ocupadas_union':len(occupied & available),
            'horas_libres':len(available-occupied),
            'ocupacion':len(occupied & available)/len(available) if available else None,
            'horas_sesiones_sumadas':sum(e['duracion_h'] for e in teaching),
            'solapes_sesiones_h':sum(e['duracion_h'] for e in teaching)-len(used),
            'horas_fuera_calendario':len(occupied-available),
            'denominador':'Unión de bloques base × días del calendario, sin receso ni cierres globales. '
                'Reservas consumen este calendario. Sábados por ciclo restringen demanda, no acreditan apertura física.'})
    return rows


def overlaps(events):
    return [{'sesion_1':a['id'],'sesion_2':b['id'],'ambiente_id':a['ambiente_id'],
             'dia':a['dia'],'inicio':max(a['inicio'],b['inicio']),'fin':min(a['fin'],b['fin'])}
            for a,b in combinations(events,2) if a['ambiente_id'] and a['ambiente_id']==b['ambiente_id']
            and a['dia']==b['dia'] and a['inicio']<b['fin'] and b['inicio']<a['fin']]


def validated_metrics(instance,outcome,spec):
    if 'valor_objetivo' not in outcome:
        return None
    if spec is None:
        raise ValueError('SOFT requiere especificación para recalcular objetivo y métricas')
    from cpsat_soft.specification import prepare
    from cpsat_soft.metrics import evaluate
    if spec != prepare(instance,spec['dias_laboratorio'],lab_source=spec['fuente_dias_laboratorio']):
        raise ValueError('Especificación SOFT alterada')
    measured = evaluate(instance,outcome,spec)
    if (measured['violaciones_evaluacion'] or measured['penalizaciones']!=outcome['penalizaciones_solver']
            or measured['objetivo_entero_recalculado']!=outcome['valor_objetivo']):
        raise ValueError('Métricas u objetivo SOFT distintos del horario')
    return measured
