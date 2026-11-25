"""Selección conjunta acotada de estructura de secciones y colocaciones temporales.

Las alternativas son configuraciones equilibradas por ciclo, no horarios previamente
resueltos. CP-SAT activa exactamente una y ubica todas sus clases en el mismo modelo.
La formulación histórica de secciones obligatorias conserva su comportamiento.
"""
from collections import defaultdict
from copy import deepcopy
from dataclasses import asdict
from math import ceil
from time import monotonic
from uuid import uuid5, NAMESPACE_URL
import hashlib
import json

from .data import InputError
from .course_assignments import responsibility, compatible_rooms

MODEL_VERSION = 'UNT_SECCIONES_HORARIO_V2'


def uid(text):
    return str(uuid5(NAMESPACE_URL, 'unt-joint:'+text))


def prepare_alternatives(e):
    offer = e.get('oferta_conjunta', {})
    mode = offer.get('modo_demanda')
    if mode not in ('AGREGADA', 'INDIVIDUAL') or not offer.get('cursos'):
        raise InputError('Complete la oferta y la demanda del periodo antes de generar.')
    options = e.get('opciones_conjuntas', {})
    extra = options.get('secciones_adicionales', 2)
    limit = options.get('max_secciones_por_ciclo', 12)
    if type(extra) is not int or not 0 <= extra <= 6 or type(limit) is not int or not 1 <= limit <= 32:
        raise InputError('Límites de secciones fuera del dominio permitido.')
    courses = deepcopy(offer['cursos'])
    identity = hashlib.sha256(json.dumps(e, sort_keys=True, ensure_ascii=True).encode()).hexdigest()
    if len({str(c['id']) for c in courses}) != len(courses):
        raise InputError('Oferta duplicada')
    for c in courses:
        if mode == 'INDIVIDUAL':
            c['demanda_estimada'] = sum(str(s['curso_id']) == str(c['id']) and s['estado'] == 'VALIDA' for s in offer.get('solicitudes', []))
        if type(c.get('demanda_estimada')) is not int or not 0 <= c['demanda_estimada'] <= 100000 or type(c.get('ciclo')) is not int or not 1 <= c['ciclo'] <= 10:
            raise InputError('Demanda o ciclo inválidos')
        repetitions = [s for s in e['secciones'] if s.get('repeticion') and str(s['curso_id']) == str(c['id'])]
        c['_repetidores'] = {x['estudiante_id'] for s in repetitions for x in s['estudiantes']}
        c['demanda_estimada'] -= sum(s.get('poblacion_estimada') if s.get('poblacion_estimada') is not None else len(s['estudiantes']) for s in repetitions)
        if c['demanda_estimada'] < 0:
            raise InputError('Las repeticiones superan la demanda del curso '+str(c['id']))
    old_sections = {s['id']: s for s in e['secciones']}
    old_acts = {a['id']: a for a in e['actividades']}
    frozen = {old_sections[old_acts[s['actividad_id']]['seccion_id']]['ciclo'] for s in e['sesiones'] if s['fijada'] and not old_acts[s['actividad_id']]['repeticion']}
    # Las restricciones que nombran identidades y las declaraciones particulares no
    # pueden trasladarse a grupos nuevos sin una decisión académica explícita.
    for b in e.get('bloqueos', []):
        x = b['datos']
        ids = [x.get('seccion_id')] if b['alcance'] == 'SECCION' else [a['seccion_id'] for a in old_acts.values() if
            (b['alcance'] == 'ACTIVIDAD' and a['id'] == x.get('actividad_id')) or
            (b['alcance'] == 'GRUPO' and a.get('grupo_id') == x.get('grupo_id'))]
        frozen.update(old_sections[s]['ciclo'] for s in ids if s in old_sections)
    frozen.update(old_sections[a['seccion_id']]['ciclo'] for a in old_acts.values() if
        (a.get('regla_disponibilidad') or '').startswith('DECLARACION_POR_ACTIVIDAD') and a.get('regla_disponibilidad') != 'DECLARACION_POR_ACTIVIDAD_CURSO')
    targets = [c for c in courses if c.get('tipo_oferta', 'REGULAR') == 'REGULAR' and c['ciclo'] not in frozen]
    target_ids = {str(c['id']) for c in targets}
    kept_secs = [s for s in e['secciones'] if s.get('repeticion') or str(s['curso_id']) not in target_ids]
    kept_ids = {s['id'] for s in kept_secs}
    kept_acts = [a for a in e['actividades'] if a['seccion_id'] in kept_ids]
    groups = defaultdict(list)
    for c in targets:
        if c['demanda_estimada'] > 0:
            groups[c['ciclo']].append(c)
    rooms = [r for r in e['ambientes'] if r['activo']]
    lab_cap = max((r['capacidad'] for r in rooms if r['tipo'] == 'LAB' and not r.get('es_externo')), default=0)
    academic_cap = e['configuracion'].get('capacidad_subgrupo_autorizada')
    if academic_cap: lab_cap = min(lab_cap, academic_cap)
    profiles = []
    docs = {str(d['id']): d for d in e['docentes']}
    for cycle, cc in sorted(groups.items()):
        for c in cc:
            for typ, field in [('T','horas_teoria'),('P','horas_practica'),('LAB','horas_laboratorio')]:
                if type(c.get(field)) is not int or not 0 <= c[field] <= 30:
                    raise InputError('Horas incompletas del curso '+str(c['id']))
                rules = [r for r in c.get('responsabilidades',[]) if r['tipo'] == typ] or [responsibility(c,typ)]
                if c[field] and (not any(str(r.get('docente_id','')) in docs for r in rules) or any(r.get('docente_id') and (str(r['docente_id']) not in docs or docs[str(r['docente_id'])].get('estado','ACTIVO') != 'ACTIVO') for r in rules)):
                    raise InputError('Asigne un docente activo a '+c['nombre']+' ('+typ+').')
                if c[field] and c.get('requiere_ambiente',True):
                    capacities = [max((r['capacidad'] for r in compatible_rooms(rooms,typ,rule)),default=0) for rule in rules]
                    if not min(capacities): raise InputError('Seleccione un ambiente habilitado de '+typ+' para '+c['nombre'])
                    c['_cap_'+typ] = min(capacities)
            if not sum(c[k] for k in ('horas_teoria','horas_practica','horas_laboratorio')):
                raise InputError('Curso sin horas: '+c['nombre'])
            c['_labcap'] = min(c.get('_cap_LAB',lab_cap), academic_cap) if academic_cap else c.get('_cap_LAB',lab_cap)
            c['_minlabs'] = max((r['grupo'] for r in c.get('responsabilidades',[]) if r['tipo']=='LAB'),default=0)
        roster = {s['estudiante_id'] for c in cc for s in offer.get('solicitudes', []) if s['estado'] == 'VALIDA' and str(s['curso_id']) == str(c['id']) and s['estudiante_id'] not in c['_repetidores']}
        demand = len(roster) if mode == 'INDIVIDUAL' else max(c['demanda_estimada'] for c in cc)
        physical = any(c.get('requiere_ambiente', True) and (c['horas_teoria'] or c['horas_practica']) for c in cc)
        cap = min(c['_cap_'+typ] for c in cc for typ,field in [('T','horas_teoria'),('P','horas_practica')] if c.get('requiere_ambiente',True) and c[field]) if physical else demand
        if not cap: raise InputError('Registre un aula habilitada antes de generar.')
        minimum = ceil(demand / cap)
        maximum = min(demand, minimum + extra, limit)
        if minimum > maximum: raise InputError(f'El ciclo {cycle} necesita al menos {minimum} secciones; revise el límite de búsqueda.')
        for n in range(minimum, maximum+1):
            valid_profile = True
            gid = f'{cycle}:{n}'
            sizes = [demand//n+(k < demand % n) for k in range(n)]
            ordered = sorted(roster)
            cohorts, offset = [], 0
            for size in sizes:
                cohorts.append(set(ordered[offset:offset+size])); offset += size
            secs, acts = [], []
            for c in cc:
                remaining = c['demanda_estimada']
                enroll = {s['estudiante_id']: s for s in offer.get('solicitudes', []) if str(s['curso_id']) == str(c['id']) and s['estado'] == 'VALIDA' and s['estudiante_id'] not in c['_repetidores']}
                for k, size in enumerate(sizes):
                    students = [deepcopy(enroll[sid]) for sid in sorted(cohorts[k] & set(enroll))] if mode == 'INDIVIDUAL' else []
                    count = len(students) if mode == 'INDIVIDUAL' else min(size, remaining)
                    remaining -= count
                    if not count: continue
                    label = chr(65+k) if k < 26 else 'A'+chr(65+k-26)
                    sid = uid(f'{e.get("propuesta_id", e["periodo"])}:{identity}:{gid}:{c["id"]}:{label}')
                    sec = {'id':sid,'curso_id':str(c['id']),'codigo':c['codigo'],'nombre':c['nombre'],'ciclo':cycle,'etiqueta':label,'capacidad':max(cap,count),
                           'poblacion_estimada':count if mode=='AGREGADA' else None,'estudiantes':students,'estado':'ACTIVA','repeticion':False,
                           'horas':{'T':c['horas_teoria'],'P':c['horas_practica'],'LAB':c['horas_laboratorio']}, 'generada_conjunta':True}
                    course_lab_cap = c['_labcap'] or count
                    lab_count = max(ceil(count/course_lab_cap), c['_minlabs']) if c['horas_laboratorio'] else 0
                    if lab_count > count:
                        valid_profile = False
                        continue
                    sec.update(capacidad_laboratorio=course_lab_cap if lab_count else None,cantidad_grupos_laboratorio=lab_count or None)
                    secs.append(sec)
                    for typ, hours in sec['horas'].items():
                        if not hours: continue
                        batches = [count] if typ != 'LAB' else [count//lab_count+(j<count%lab_count) for j in range(lab_count)]
                        offset_lab = 0
                        for g, population in enumerate(batches):
                            rule = responsibility(c,typ,g+1 if typ=='LAB' else 0)
                            if str(rule.get('docente_id','')) not in docs:
                                valid_profile = False
                                continue
                            aid = uid(sid+':'+typ+':'+str(g))
                            ids = [s['estudiante_id'] for s in students]
                            ids = ids[offset_lab:offset_lab+population] if typ=='LAB' else ids
                            offset_lab += population
                            durations = [2]*(hours//2)+([1] if hours%2 else [])
                            acts.append({'id':aid,'seccion_id':sid,'docente_id':str(rule['docente_id']),'tipo':typ,'horas':hours,'duraciones':durations,
                                         'estudiantes':ids,'poblacion_estimada':population if mode=='AGREGADA' else None,'subgrupo':sid+':LAB:'+str(g+1) if typ=='LAB' else None,
                                         'grupo_id':uid(aid+':grupo') if typ=='LAB' else None,'repeticion':False,'requiere_ambiente':c.get('requiere_ambiente',True),
                                         'fuente_asignacion':'ASIGNACION_CURSO_PERIODO','estado_asignacion':'ASIGNADO','ambiente_asignado_id':rule.get('ambiente_id'),
                                         'franjas_origen':rule.get('franjas'),'regla_disponibilidad':'DECLARACION_POR_ACTIVIDAD_CURSO' if rule.get('franjas') is not None else 'SIN_DECLARAR_CAMBIO_DOCENTE'})
            if valid_profile: profiles.append({'id':gid,'ciclo':cycle,'cantidad':n,'secciones':secs,'actividades':acts})
        if not any(p['ciclo']==cycle for p in profiles):
            raise InputError(f'Complete los docentes de cada grupo de laboratorio del ciclo {cycle}; las cantidades probadas requieren grupos sin responsable.')
    return profiles, kept_secs, kept_acts, sorted(frozen)


def ejecutar_conjunto(e):
    from ortools.sat.python import cp_model
    import ortools
    from scripts.motor_aplicacion import normalizar, validar_operacion
    from .model import build
    from .validation import validate
    from .sectioning_validation import validate_sectioning
    started = monotonic()
    profiles, kept_secs, kept_acts, frozen = prepare_alternatives(e)
    union = deepcopy(e)
    union['configuracion'] = {**union['configuracion'], 'politica_conflictos':union['configuracion'].get('politica_conflictos', 'CICLO_SECCION_V2'),'politica_laboratorios':'PARTICION_EXPLICITA_V1'}
    lab_cap = max((r['capacidad'] for r in e['ambientes'] if r['activo'] and r['tipo'] == 'LAB' and not r.get('es_externo')), default=0)
    if lab_cap:
        previous = union['configuracion'].get('capacidad_subgrupo_autorizada')
        union['configuracion']['capacidad_subgrupo_autorizada'] = min(lab_cap, previous) if previous else lab_cap
    union['secciones'] = kept_secs+[s for p in profiles for s in p['secciones']]
    union['actividades'] = kept_acts+[a for p in profiles for a in p['actividades']]
    if not union['actividades']: raise InputError('Complete la demanda y las asignaciones del periodo.')
    # Las cargas de todas las alternativas no son simultáneas. Se imponen de forma reificada abajo.
    union['docentes'] = [{k:v for k,v in d.items() if k!='max_horas_semanales'} for d in e['docentes']]
    i = normalizar(union)
    built = build(i, optional_groups={p['id']:[a['id'] for a in p['actividades']] for p in profiles})
    by_cycle = defaultdict(list)
    for p in profiles: by_cycle[p['ciclo']].append(built.optional_presence[p['id']])
    for vs in by_cycle.values(): built.model.add_exactly_one(vs)
    for d in e['docentes']:
        if d.get('max_horas_semanales') is not None:
            fixed_load = sum(a['horas'] for a in kept_acts if str(a['docente_id'])==str(d['id']))
            built.model.add(fixed_load+sum(sum(a['horas'] for a in p['actividades'] if str(a['docente_id'])==str(d['id']))*built.optional_presence[p['id']] for p in profiles)<=d['max_horas_semanales'])
    for s in e['sesiones']:
        if not s['fijada']: continue
        matches = [j for j,c in enumerate(i.placements) if c.session_id==s['sesion_motor'] and (c.dia,c.inicio,c.fin,c.ambiente_id)==(s['dia'],s['inicio'],s['fin'],s['ambiente_id'] or '')]
        if len(matches)!=1: raise InputError('Una clase fijada es incompatible con los recursos o datos vigentes.')
        built.model.add(built.placements[matches[0]]==1)
    session_bounds = defaultdict(int)
    for c in i.placements: session_bounds[c.session_id]=max(session_bounds[c.session_id],c.inicio)
    schedule_bound=sum(session_bounds.values())
    hours_bound=sum(a['horas'] for a in union['actividades'])
    ws=(hours_bound+1)*(schedule_bound+1)
    objective=sum(p['cantidad']*built.optional_presence[p['id']] for p in profiles)*ws
    objective+=sum(sum(a['horas'] for a in p['actividades'])*built.optional_presence[p['id']] for p in profiles)*(schedule_bound+1)
    objective+=sum(c.inicio*built.placements[j] for j,c in enumerate(i.placements))
    if sum(p['cantidad'] for p in profiles)*ws+(hours_bound+1)*(schedule_bound+1)>=2**53:
        raise InputError('Dominio de búsqueda demasiado grande para el objetivo trazable.')
    built.model.minimize(objective)
    solver=cp_model.CpSolver()
    budget=max(1,min(300,e.get('tiempo_limite',30)))
    solver.parameters.max_time_in_seconds=max(1,budget*.7)
    solver.parameters.num_search_workers=1
    solver.parameters.random_seed=1
    status=solver.solve(built.model)
    result={'contrato':'HORARIOS_RESULTADO_V1','estado':solver.status_name(status),'version_motor':MODEL_VERSION,'operacion':'GENERAR_PROPUESTA_INTEGRADA',
            'objetivo':'LEX_SECCIONES_HORAS_INICIO_V1','dominio_secciones':{str(c):[p['cantidad'] for p in profiles if p['ciclo']==c] for c in by_cycle},
            'ciclos_conservados_por_fijacion':frozen,'sesiones':[],'versiones':{'ortools':ortools.__version__},'diagnosticos':[],
            'hash_entrada':hashlib.sha256(json.dumps(e,sort_keys=True,ensure_ascii=True).encode()).hexdigest()}
    if status not in (cp_model.FEASIBLE,cp_model.OPTIMAL):
        result['diagnosticos']=[{'categoria':'FACTIBILIDAD','detalle':'No se encontró un horario en el dominio declarado. Revise docentes, demanda, disponibilidad, recursos o amplíe la búsqueda; no se ha eliminado demanda.'}]
        return result
    chosen=[p for p in profiles if solver.value(built.optional_presence[p['id']])]
    projected={**e,'configuracion':union['configuracion'],'secciones':kept_secs+[s for p in chosen for s in p['secciones']], 'actividades':kept_acts+[a for p in chosen for a in p['actividades']]}
    rows=[{'sesion_motor':c.session_id,'actividad_id':c.actividad_id,'dia':c.dia,'inicio':c.inicio,'fin':c.fin,'ambiente_id':c.ambiente_id or None,
           'fijada':next((s['fijada'] for s in e['sesiones'] if s['sesion_motor']==c.session_id),False),'origen':'CP_SAT_CONJUNTO'} for j,c in enumerate(i.placements) if solver.value(built.placements[j])]
    for row in rows:
        previous=next((s for s in e['sesiones'] if s['sesion_motor']==row['sesion_motor'] and s['fijada']),None)
        if previous: row['origen']=previous.get('origen','MANUAL')
    selected=normalizar(projected)
    outcome={'patrones_elegidos':list(selected.patterns),'sesiones_elegidas':[]}
    for row in rows:
        match=next(c for c in selected.placements if c.session_id==row['sesion_motor'] and (c.dia,c.inicio,c.fin,c.ambiente_id)==(row['dia'],row['inicio'],row['fin'],row['ambiente_id'] or ''))
        outcome['sesiones_elegidas'].append(asdict(match))
    result.update(secciones=projected['secciones'],actividades=projected['actividades'],sesiones=rows, configuracion_resultante=projected['configuracion'],
                  seleccion_secciones=[{'ciclo':p['ciclo'],'cantidad':p['cantidad']} for p in chosen],
                  validacion_demanda=validate_sectioning(e,projected),validacion_operativa=validar_operacion(projected,rows,completa=True),
                  validacion_historica=validate(selected,outcome),optimalidad_estructura=solver.status_name(status),
                  optimalidad_calidad_entre_estructuras=False,mejor_cota_estructura=solver.best_objective_bound,valor_objetivo_estructura=solver.objective_value)
    if not result['validacion_demanda']['valida'] or not result['validacion_operativa']['valida'] or not result['validacion_historica']['cumple_reglas_con_datos_disponibles']:
        result['estado']='RECHAZADA_VALIDACION'; return result
    from cpsat_soft.specification import prepare
    from cpsat_soft.metrics import evaluate
    cal = selected.raw['calendario']
    official_calendar = len(cal['bloques_hora_base']) == 12 and cal['receso'] == {'inicio':13,'fin':14}
    if official_calendar:
        from .calendar_policy import planning_days
        spec=prepare(selected,planning_days(selected),lab_source='CONFIGURACION_PROPUESTA')
        result['metricas_oficiales']=evaluate(selected,outcome,spec)
    else:
        result['limitacion_metricas']='Las cotas oficiales S01–S06 requieren el calendario de tesis de 12 bloques y receso 13–14.'
    remaining=budget-(monotonic()-started)
    if official_calendar and remaining>=2 and e.get('opciones_conjuntas',{}).get('refinar_calidad',True):
        from scripts.motor_aplicacion import ejecutar
        refined=ejecutar({**projected,'operacion':'GENERAR_HORARIO','alcance':[],'tiempo_limite':remaining})
        result['estado_refinamiento']=refined['estado']
        if refined['estado'] in ('FEASIBLE','OPTIMAL') and refined['validacion_operativa']['valida']:
            for key in ('sesiones','metricas_oficiales','validacion_operativa','validacion_historica'):
                result[key]=refined[key]
    result['tiempo_s']=monotonic()-started
    return result


def validate_result(original, result):
    """Revalidación antes de aplicar, sin invocar al optimizador ni confiar en sus flags."""
    from scripts.motor_aplicacion import normalizar, validar_operacion
    from .sectioning_validation import validate_sectioning
    from .validation import validate
    projected = {**original, 'secciones': result['secciones'], 'actividades': result['actividades'],
                 'configuracion': {**original['configuracion'], 'politica_conflictos':original['configuracion'].get('politica_conflictos', 'CICLO_SECCION_V2'), 'politica_laboratorios':'PARTICION_EXPLICITA_V1'}}
    lab_cap = max((r['capacidad'] for r in original['ambientes'] if r['activo'] and r['tipo'] == 'LAB' and not r.get('es_externo')), default=0)
    if lab_cap:
        previous = projected['configuracion'].get('capacidad_subgrupo_autorizada')
        projected['configuracion']['capacidad_subgrupo_autorizada'] = min(lab_cap, previous) if previous else lab_cap
    demand = validate_sectioning(original, projected)
    operation = validar_operacion(projected, result['sesiones'], completa=True)
    normalized = normalizar(projected)
    outcome = {'patrones_elegidos': list(normalized.patterns), 'sesiones_elegidas': []}
    for row in result['sesiones']:
        matches = [c for c in normalized.placements if c.session_id == row['sesion_motor'] and
                   (c.dia,c.inicio,c.fin,c.ambiente_id) == (row['dia'],row['inicio'],row['fin'],row['ambiente_id'] or '')]
        if len(matches) != 1:
            return {'contrato':'HORARIOS_RESULTADO_V1', 'valida':False, 'estado':'RECHAZADA_VALIDACION'}
        outcome['sesiones_elegidas'].append(asdict(matches[0]))
    history = validate(normalized,outcome)
    valid = demand['valida'] and operation['valida'] and history['cumple_reglas_con_datos_disponibles']
    return {'contrato':'HORARIOS_RESULTADO_V1','valida':valid,'estado':'VALIDADA' if valid else 'RECHAZADA_VALIDACION',
            'validacion_demanda':demand,'validacion_operativa':operation,'validacion_historica':history}
