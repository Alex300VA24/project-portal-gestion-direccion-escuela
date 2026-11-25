"""Puente privado Laravel -> motor existente. No modifica experimentos históricos.

Lee JSON por stdin y entrega JSON por stdout. Las identidades ya son seudónimos.
El esquema operativo y el objetivo ampliado tienen versiones propias.
"""
import hashlib
import json
from itertools import combinations
import platform
from pathlib import Path
import sys
import time

from cpsat_hard.data import InputError, from_records
from cpsat_soft.official import DEFAULT_VARIANT, MODEL_VERSION
from cpsat_hard.course_assignments import room_allowed


def poblacion(registro):
    n = registro.get('poblacion_estimada')
    return n if n is not None else len(registro['estudiantes'])


def asesoria_sin_poblacion(seccion):
    return seccion.get('es_grupo_asesoria') is True and seccion.get('poblacion_estimada') is None and not seccion['estudiantes']


def unidad_academica(seccion):
    return ('ASESORIA', seccion['id']) if seccion.get('es_grupo_asesoria') else ('SECCION', seccion['ciclo'], seccion['etiqueta'])


def ventanas_actividad(e, a):
    """La declaración operativa sustituye explícitamente las ventanas de origen del docente."""
    d = next((d for d in e['disponibilidades'] if str(d['docente_id']) == str(a['docente_id'])), None)
    if (a.get('regla_disponibilidad') or '').startswith('DECLARACION_POR_ACTIVIDAD'):
        particulares = a.get('franjas_origen')
        generales = None if not d or d['estado'] == 'SIN_DECLARAR' else d['franjas']
        if particulares is None: return None if generales is None else unir_franjas(generales)
        if generales is None: return unir_franjas(particulares)
        return unir_franjas([{'dia': x['dia'], 'inicio': max(x['inicio'], y['inicio']), 'fin': min(x['fin'], y['fin'])}
                            for x in particulares for y in generales if x['dia'] == y['dia'] and max(x['inicio'], y['inicio']) < min(x['fin'], y['fin'])])
    if d is not None:
        return None if d['estado'] == 'SIN_DECLARAR' else unir_franjas(d['franjas'])
    if a.get('regla_disponibilidad') in (None, 'SIN_DECLARAR_CAMBIO_DOCENTE') and a.get('franjas_origen') is None:
        sec = next(s for s in e['secciones'] if s['id'] == a['seccion_id'])
        roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'][sec['ciclo']-1]
        alcances = ('GENERAL', f"CURSO_{sec['curso_id']}", roman+'-'+sec['etiqueta'])
        propias = [w for w in e.get('ventanas_origen', []) if str(w['docente_origen']) == str(a['docente_id']) and w['alcance'] in alcances]
        if propias:
            return unir_franjas(propias)
    return None if a.get('franjas_origen') is None else unir_franjas(a['franjas_origen'])


def bloqueo_aplica(b, a, sec, ambiente):
    x = b['datos']; alcance = b['alcance']
    return (alcance in ('GLOBAL', 'GLOBAL_CONDICIONAL') or
            alcance == 'AMBIENTE' and x.get('ambiente_id') == ambiente or
            alcance == 'DOCENTE' and str(x.get('docente_id')) == str(a['docente_id']) or
            alcance == 'COHORTE' and not a['repeticion'] and x.get('ciclo') == sec['ciclo'] and sec['etiqueta'] in x.get('secciones', []) or
            alcance == 'CICLO' and x.get('ciclo') == sec['ciclo'] or
            alcance == 'SECCION' and x.get('seccion_id') == sec['id'] or
            alcance == 'CURSO' and str(x.get('curso_id')) == str(sec['curso_id']) or
            alcance == 'ACTIVIDAD' and x.get('actividad_id') == a['id'] or
            alcance == 'GRUPO' and x.get('grupo_id') == a.get('grupo_id'))


def sabado_permitido(cal, sec, a):
    ciclo = a.get('ciclo_asistente') if a.get('repeticion') else sec['ciclo']
    if not cal['sabados_por_ciclo'].get(str(ciclo), False):
        return False
    if cal.get('sabados') == 'SELECCIONADOS':
        return any(x['ciclo'] == ciclo and (not x['secciones'] or sec['etiqueta'] in x['secciones']) for x in cal.get('sabados_alcances', []))
    return True


def dias_actividad(e, a):
    from cpsat_hard.calendar_policy import activity_days, POLICY
    return activity_days(e['configuracion']['calendario'], repetition=a.get('repeticion', False), windows=ventanas_actividad(e, a), policy=POLICY)


def dia_permitido(e, a, sec, dia):
    from cpsat_hard.calendar_policy import allowed_day, POLICY
    if dia == 'SABADO' and not a.get('repeticion') and 'secciones_participantes' in a:
        secciones = {s['id']: s for s in e['secciones']}
        participantes = a['secciones_participantes']
        if not participantes or any(sid not in secciones for sid in participantes):
            return False
        return all(allowed_day(e['configuracion']['calendario'], dia, repetition=False,
                               windows=ventanas_actividad(e, a), cycle=secciones[sid]['ciclo'],
                               label=secciones[sid]['etiqueta'], policy=POLICY) for sid in participantes)
    return allowed_day(e['configuracion']['calendario'], dia, repetition=a.get('repeticion', False),
                       windows=ventanas_actividad(e, a), cycle=a.get('ciclo_asistente') if a.get('repeticion') else sec['ciclo'], label=sec['etiqueta'], policy=POLICY)


def comprobar_preparacion(e):
    """Detecta impedimentos verificables sin construir ni ejecutar CP-SAT."""
    cal = e['configuracion']['calendario']
    docentes = {str(d['id']): d for d in e.get('docentes', [])}
    acts = {a['id']: a for a in e['actividades']}
    seleccion = set(e.get('alcance', []))
    secciones = {s['id']: s for s in e['secciones']}
    objetivos = {acts[x]['seccion_id'] for x in seleccion if x in acts} if seleccion else set(secciones)
    alcances = {unidad_academica(secciones[x]) for x in objetivos}
    objetivos = {s['id'] for s in e['secciones'] if unidad_academica(s) in alcances}
    filas = []
    for sec in e['secciones']:
        if sec['id'] not in objetivos:
            continue
        problemas, avisos = [], []
        propias = [a for a in acts.values() if a['seccion_id'] == sec['id']]
        def problema(texto, destino='Asignaciones docentes', actividad=None):
            problemas.append({'mensaje': texto, 'destino': destino, 'actividad_id': actividad, 'seccion_id': sec['id']})
        if sec.get('estado') not in ('PROPUESTA', 'AUTORIZADA', 'ACTIVA'):
            problema(f"{sec['nombre']} — sección {sec['etiqueta']}: revise la preparación de la sección.", 'Secciones')
        if not propias:
            problema(f"{sec['nombre']} no tiene actividades preparadas.")
        for tipo, horas in sec['horas'].items():
            coincidencias = [a for a in propias if a['tipo'] == tipo]
            if horas and not coincidencias:
                problema(f"{sec['nombre']} no tiene { {'T': 'teoría', 'P': 'práctica', 'LAB': 'laboratorio'}[tipo]} preparado.")
            if tipo == 'LAB':
                grupos_horas = {}
                grupos_actividades = {}
                for a in coincidencias:
                    clave = a.get('grupo_id') or a.get('subgrupo') or a['id']
                    grupos_horas[clave] = grupos_horas.get(clave, 0) + a['horas']
                    grupos_actividades.setdefault(clave, []).append(a)
                incoherente = any(total != horas for total in grupos_horas.values())
                if horas and sec.get('cantidad_grupos_laboratorio') is not None:
                    if len(grupos_actividades) != sec['cantidad_grupos_laboratorio']:
                        problema(f"{sec['nombre']}: falta asignar docentes a todos los grupos de laboratorio de esta sección.")
                    if sum(poblacion(aa[0]) for aa in grupos_actividades.values()) != poblacion(sec):
                        problema(f"{sec['nombre']}: los grupos de laboratorio asignados no cubren a todos los estudiantes de la sección.")
            else:
                incoherente = bool(coincidencias) and sum(a['horas'] for a in coincidencias) != horas
            if incoherente:
                problema(f"{sec['nombre']}: las horas semanales no coinciden con la oferta.", 'Secciones')
        for a in propias:
            if a.get('repeticion') and not (type(a.get('ciclo_asistente')) is int and 1 <= a['ciclo_asistente'] <= 10):
                problema(f"{sec['nombre']}: indica el ciclo de los estudiantes de repetición antes de generar.", 'Secciones', a['id'])
            doc = docentes.get(str(a['docente_id']))
            if not doc or doc.get('estado', 'ACTIVO') != 'ACTIVO' or a.get('estado_asignacion') == 'SOLICITADO':
                problema(f"{sec['nombre']} no tiene docente asignado.", actividad=a['id'])
                continue
            if sum(a['duraciones']) != a['horas'] or not a['duraciones'] or any(d < 1 for d in a['duraciones']):
                problema(f"{sec['nombre']}: revise la distribución de horas.", actividad=a['id'])
                continue
            limite = e['configuracion'].get('capacidad_subgrupo_autorizada')
            if a['tipo'] == 'LAB' and (not a.get('subgrupo') or limite is not None and poblacion(a) > limite):
                problema(f"{sec['nombre']}: revise el subgrupo y el límite académico registrado.", 'Secciones', a['id'])
            rooms = [r for r in e['ambientes'] if r['activo'] and room_allowed(r,a) and r['tipo'] == ('LAB' if a['tipo'] == 'LAB' else 'AULA') and r['capacidad'] >= poblacion(a)] if a.get('requiere_ambiente', True) else [{'id': None}]
            if not rooms:
                problema(f"{sec['nombre']} no tiene un ambiente compatible con capacidad suficiente.", 'Aulas y laboratorios', a['id'])
                continue
            ventanas = ventanas_actividad(e, a)
            if ventanas is None:
                avisos.append({'mensaje': f"{doc['nombre']}: se aplica la disponibilidad asumida durante la jornada.", 'destino': 'Disponibilidad del periodo'})
            if ventanas == []:
                problema(f"{doc['nombre']} declaró una disponibilidad vacía.", 'Disponibilidad del periodo', a['id'])
                continue
            for dur in a['duraciones']:
                posible = False
                for dia in dias_actividad(e, a):
                    if not dia_permitido(e, a, sec, dia): continue
                    for h in range(cal['hora_inicial'], cal['hora_final'] - dur + 1):
                        if h < cal['receso']['fin'] and cal['receso']['inicio'] < h + dur: continue
                        permitidas = {n for w in ventanas or [] if w['dia'] == dia for n in range(w['inicio'], w['fin'])}
                        if ventanas is not None and not set(range(h, h + dur)) <= permitidas: continue
                        for room in rooms:
                            if not any(bloqueo_aplica(b, a, sec, room['id']) and b['datos']['dia'] == dia and h < b['datos']['fin'] and b['datos']['inicio'] < h + dur for b in e['bloqueos']):
                                posible = True
                                break
                        if posible: break
                    if posible: break
                if not posible:
                    problema(f"{sec['nombre']}: no existe una franja compatible de {dur} horas para {doc['nombre']}.", 'Disponibilidad del periodo', a['id'])
                    break
            carga = sum(x['horas'] for x in acts.values() if str(x['docente_id']) == str(a['docente_id']))
            if doc.get('max_horas_semanales') is not None and carga > doc['max_horas_semanales']:
                problema(f"{doc['nombre']} supera su máximo de horas registrado.", actividad=a['id'])
        if sec.get('poblacion_estimada') is not None and not sec.get('es_grupo_asesoria'):
            avisos.append({'mensaje': 'La demanda agregada permite programar; los cruces personales adicionales requieren matrícula individual.', 'destino': 'Secciones'})
        filas.append({'seccion_id': sec['id'], 'curso': sec['nombre'], 'ciclo': sec['ciclo'], 'seccion': sec['etiqueta'], 'estado': 'BLOQUEADA' if problemas else ('ATENCION' if avisos else 'LISTA'), 'problemas': problemas, 'advertencias': avisos, 'actividades': [a['id'] for a in propias]})
    # Una sección académica se genera como unidad: no omitir un curso bloqueado del mismo ciclo y etiqueta.
    bloqueadas = {unidad_academica(secciones[s['seccion_id']]) for s in filas if s['estado'] == 'BLOQUEADA'}
    for fila in filas:
        if unidad_academica(secciones[fila['seccion_id']]) in bloqueadas and fila['estado'] != 'BLOQUEADA':
            fila['estado'] = 'BLOQUEADA'
            fila['problemas'].append({'mensaje': 'Otro curso de esta sección requiere atención antes de generar.', 'destino': 'Asignaciones docentes', 'seccion_id': fila['seccion_id']})
    # La generación puede corregir el orden de clases movibles de un horario
    # anterior. Las fijaciones contradictorias sí necesitan atención explícita.
    objetivo = e.get('objetivo_generacion', 'comprobar')
    reparable = objetivo in ('generar', 'reorganizar')
    conservadas = [s for s in e['sesiones'] if not reparable or s['fijada'] or seleccion and s['actividad_id'] not in seleccion]
    actuales = validar_operacion(e, conservadas, comprobar_orden=objetivo != 'comprobar')
    errores_fijas = errores_orden_actividades(e, [s for s in e['sesiones'] if s['fijada']])
    errores_actuales = actuales['errores'] + errores_fijas
    if errores_actuales:
        for fila in filas:
            fila['estado'] = 'BLOQUEADA'
            fila['problemas'].append({'mensaje': errores_fijas[0] + ' Revise o libere las sesiones fijadas.' if errores_fijas else 'Hay conflictos en sesiones guardadas. Revise el horario antes de generar.', 'destino': 'Conflictos', 'seccion_id': fila['seccion_id']})
    elegibles = [a for fila in filas if fila['estado'] != 'BLOQUEADA' for a in fila['actividades']]
    grupos = {unidad_academica(secciones[f['seccion_id']]) for f in filas}
    advertidas = {unidad_academica(secciones[f['seccion_id']]) for f in filas if f['estado'] == 'ATENCION'} - bloqueadas
    return {'contrato': 'HORARIOS_RESULTADO_V1', 'estado': 'NO_SE_PUEDE_GENERAR' if not elegibles else ('REQUIERE_ATENCION' if any(f['estado'] != 'LISTA' for f in filas) else 'LISTO'), 'secciones': filas, 'problemas_globales': list(dict.fromkeys(errores_actuales)), 'alcance_elegible': elegibles, 'cursos': len({secciones[f['seccion_id']]['curso_id'] for f in filas}), 'listas': len(grupos - bloqueadas - advertidas) if not errores_actuales else 0, 'advertencias': len(advertidas) if not errores_actuales else 0, 'bloqueadas': len(bloqueadas) if not errores_actuales else len(grupos)}


def errores_orden_actividades(e, sesiones):
    """Verificación por intervalos; no consulta variables ni predicados CP-SAT."""
    if e['configuracion'].get('orden_actividades') not in ('T_P_LAB_SEMANAL_V1', 'T_P_DURO_LAB_BLANDO_V2'):
        return []
    days = {d: n for n, d in enumerate(('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'))}
    activities = {a['id']: a for a in e['actividades']}
    sections = {s['id']: s for s in e['secciones']}
    bounds = {}
    for row in sesiones:
        a = activities.get(row.get('actividad_id'))
        if a and row.get('dia') in days:
            key = (a['seccion_id'], a['tipo'])
            start, end = days[row['dia']] * 24 + row['inicio'], days[row['dia']] * 24 + row['fin']
            old = bounds.get(key, (start, end))
            bounds[key] = (min(old[0], start), max(old[1], end))
    errors = []
    names = {'T': 'teoría', 'P': 'práctica', 'LAB': 'laboratorio'}
    for sid, sec in sections.items():
        if str(sec['curso_id']) in {str(c) for c in e['configuracion'].get('excepciones_orden_actividades', [])}:
            continue
        sequence = ('T', 'P', 'LAB') if e['configuracion']['orden_actividades'] == 'T_P_LAB_SEMANAL_V1' else ('T', 'P')
        present = [kind for kind in sequence if (sid, kind) in bounds]
        for previous, following in zip(present, present[1:]):
            if bounds[sid, previous][1] > bounds[sid, following][0]:
                errors.append(f"Orden obligatorio · {sec['nombre']} · sección {sec['etiqueta']}: debe terminar {names[previous]} antes de iniciar {names[following]}.")
    return errors


def validar_operacion(e, sesiones, completa=False, *, comprobar_orden=True):
    """Validador operativo independiente del constructor de variables y candidatas."""
    errores = []
    actividades = {a['id']: a for a in e['actividades']}
    secciones = {s['id']: s for s in e['secciones']}
    ambientes = {r['id']: r for r in e['ambientes']}
    disponibilidad = {str(d['docente_id']): d for d in e['disponibilidades']}
    calendario = e['configuracion']['calendario']
    alternativa_labs = e['configuracion'].get('politica_conflictos') == 'SECCION_Y_COMPARTIDOS_V4' and e['configuracion'].get('politica_grupos_laboratorio') == 'ALTERNATIVAS_SIN_ASIGNACION_V1'
    grupos_lab = {}
    for a in actividades.values():
        group = a.get('subgrupo') or a.get('grupo_id')
        if a['tipo'] == 'LAB' and not a['repeticion'] and a.get('tipo_grupo') != 'TESIS' and group:
            grupos_lab.setdefault(a['seccion_id'],set()).add(group)
    def es_alternativa(a):
        return alternativa_labs and a['tipo'] == 'LAB' and not a['repeticion'] and a.get('tipo_grupo') != 'TESIS' and bool(a.get('subgrupo') or a.get('grupo_id')) and len(grupos_lab.get(a['seccion_id'],set())) > 1
    aperturas = {}
    for sec in e['secciones']:
        if sec.get('repeticion') or sec.get('estado') not in ('PROPUESTA', 'AUTORIZADA', 'ACTIVA'): continue
        etiqueta = 'ASESORIA' if sec.get('es_grupo_asesoria') else sec['etiqueta'].strip().upper()
        aperturas.setdefault((sec['ciclo'], str(sec['curso_id'])), set()).add(etiqueta)
    esperadas = {f"{a['id']}:{n}": (a, dur) for a in actividades.values()
                 for n, dur in enumerate(a['duraciones'], 1)}
    vistas = set()
    for s in sesiones:
        sid = s['sesion_motor']
        if sid not in esperadas or sid in vistas:
            errores.append('Sesión desconocida o duplicada: '+sid)
            continue
        vistas.add(sid)
        a, dur = esperadas[sid]
        sec = secciones[a['seccion_id']]
        if 'sesion_fisica_id' in a and (s.get('sesion_fisica_id') != a['sesion_fisica_id'] or
                s.get('secciones_participantes') != a['secciones_participantes']):
            errores.append('Identidad o participación física incorrecta: '+sid)
        if 'sesion_fisica_id' not in a and ('sesion_fisica_id' in s or 'secciones_participantes' in s):
            errores.append('Participación física no declarada: '+sid)
        r = ambientes.get(s['ambiente_id'])
        ini, fin = s['inicio'], s['fin']
        if (s['actividad_id'] != a['id'] or fin-ini != dur or not dia_permitido(e, a, sec, s['dia'])
                or ini < calendario['hora_inicial'] or fin > calendario['hora_final']
                or (ini < calendario['receso']['fin'] and calendario['receso']['inicio'] < fin)):
            errores.append('Intervalo/actividad incompatible: '+sid)
        if a.get('requiere_ambiente', True) and (not r or not r['activo'] or r['tipo'] != ('LAB' if a['tipo'] == 'LAB' else 'AULA') or r['capacidad'] < poblacion(a)):
            errores.append('Ambiente incompatible: '+sid)
        # Comprobación directa, independiente de la construcción de candidatas.
        if r and (a.get('ambiente_asignado_id') and r['id'] != a['ambiente_asignado_id'] or r.get('es_externo') and r['id'] != a.get('ambiente_asignado_id')):
            errores.append('Ambiente no asignado al curso: '+sid)
        if not a.get('requiere_ambiente', True) and s['ambiente_id']:
            errores.append('La actividad no utiliza aula compartida: '+sid)
        ciclo = a.get('ciclo_asistente') if a['repeticion'] else sec['ciclo']
        if s['dia'] == 'SABADO' and not dia_permitido(e, a, sec, s['dia']):
            errores.append('Sábado no autorizado: '+sid)
        ventanas = ventanas_actividad(e, a)
        permitidas = {h for w in ventanas or [] if w['dia'] == s['dia'] for h in range(w['inicio'], w['fin'])}
        if ventanas is not None and not set(range(ini, fin)) <= permitidas:
            errores.append('Disponibilidad docente: '+sid)
        for b in e['bloqueos']:
            x = b['datos']
            aplica = bloqueo_aplica(b, a, sec, s['ambiente_id'])
            if aplica and x['dia'] == s['dia'] and ini < x['fin'] and x['inicio'] < fin:
                errores.append('Bloqueo institucional: '+b['id'])
    for x, y in combinations(sesiones, 2):
        if x['dia'] != y['dia'] or x['inicio'] >= y['fin'] or y['inicio'] >= x['fin']:
            continue
        if x['actividad_id'] not in actividades or y['actividad_id'] not in actividades:
            continue
        a, b = actividades[x['actividad_id']], actividades[y['actividad_id']]
        sa, sb = secciones[a['seccion_id']], secciones[b['seccion_id']]
        cohorte = (not a['repeticion'] and not b['repeticion'] and sa.get('cohorte_acreditada') and
                   sa.get('cohorte_acreditada') == sb.get('cohorte_acreditada') and sa['curso_id'] != sb['curso_id'])
        if e['configuracion'].get('politica_conflictos') in ('CICLO_SECCION_V2', 'CICLO_COMPLETO_V3'):
            cohorte = (not a['repeticion'] and not b['repeticion'] and sa['ciclo'] == sb['ciclo'] and
                       (e['configuracion']['politica_conflictos'] == 'CICLO_COMPLETO_V3' or sa['etiqueta'] == sb['etiqueta']) and sa['curso_id'] != sb['curso_id'])
        if e['configuracion'].get('politica_conflictos') == 'SECCION_Y_COMPARTIDOS_V4':
            la = 'ASESORIA' if sa.get('es_grupo_asesoria') else sa['etiqueta'].strip().upper()
            lb = 'ASESORIA' if sb.get('es_grupo_asesoria') else sb['etiqueta'].strip().upper()
            cohorte = (not a['repeticion'] and not b['repeticion'] and sa['ciclo'] == sb['ciclo'] and str(sa['curso_id']) != str(sb['curso_id']) and
                (la == lb or len(aperturas.get((sa['ciclo'], str(sa['curso_id'])), set())) == 1 or len(aperturas.get((sb['ciclo'], str(sb['curso_id'])), set())) == 1))
            if es_alternativa(a) and es_alternativa(b): cohorte = False
            if 'sesion_fisica_id' in a or 'sesion_fisica_id' in b:
                cohorte = False
                for aid in a.get('secciones_participantes', [a['seccion_id']]):
                    for bid in b.get('secciones_participantes', [b['seccion_id']]):
                        ea, eb = secciones[aid], secciones[bid]
                        laa = 'ASESORIA' if ea.get('es_grupo_asesoria') else ea['etiqueta'].strip().upper()
                        lbb = 'ASESORIA' if eb.get('es_grupo_asesoria') else eb['etiqueta'].strip().upper()
                        cohorte |= (not a['repeticion'] and not b['repeticion'] and ea['ciclo'] == eb['ciclo'] and
                            str(ea['curso_id']) != str(eb['curso_id']) and
                            (laa == lbb or len(aperturas.get((ea['ciclo'], str(ea['curso_id'])), set())) == 1 or
                             len(aperturas.get((eb['ciclo'], str(eb['curso_id'])), set())) == 1))
        if e['configuracion'].get('politica_conflictos') not in ('CICLO_COMPLETO_V3', 'SECCION_Y_COMPARTIDOS_V4') and (a.get('tipo_grupo') == 'TESIS' or b.get('tipo_grupo') == 'TESIS'):
            # El no cruce entre grupos de asesoría es preferencia, no cohorte inferida.
            cohorte = False
        misma_seccion_fisica = bool(set(a.get('secciones_participantes', [a['seccion_id']])) &
                                    set(b.get('secciones_participantes', [b['seccion_id']])))
        grupo_seccion = (misma_seccion_fisica and
                         (a['tipo'] != 'LAB' or b['tipo'] != 'LAB' or
                          a.get('grupo_id') and a.get('grupo_id') == b.get('grupo_id') or
                          a.get('subgrupo') and a.get('subgrupo') == b.get('subgrupo')))
        if e['configuracion'].get('politica_laboratorios') == 'PARTICION_EXPLICITA_V1' and a['seccion_id'] == b['seccion_id'] and a['tipo'] == b['tipo'] == 'LAB':
            padrones = a.get('poblacion_estimada') is None and b.get('poblacion_estimada') is None and a['estudiantes'] and b['estudiantes'] and not set(a['estudiantes']) & set(b['estudiantes'])
            particion = a.get('particion_acreditada') and a.get('particion_acreditada') == b.get('particion_acreditada') and a.get('grupo_id') != b.get('grupo_id')
            grupos_distintos = (a.get('subgrupo') or a.get('grupo_id')) != (b.get('subgrupo') or b.get('grupo_id'))
            if not (padrones or particion or es_alternativa(a) and es_alternativa(b) and grupos_distintos): grupo_seccion = True
        if (x['ambiente_id'] and x['ambiente_id'] == y['ambiente_id'] or str(a['docente_id']) == str(b['docente_id']) or
                set(a['estudiantes']) & set(b['estudiantes']) or cohorte or grupo_seccion):
            if cohorte and e['configuracion'].get('politica_conflictos') in ('CICLO_COMPLETO_V3', 'SECCION_Y_COMPARTIDOS_V4'):
                errores.append(f"Cruce entre cursos del ciclo {sa['ciclo']}: {sa['nombre']} · sección {sa['etiqueta']} ({a['tipo']}) / {sb['nombre']} · sección {sb['etiqueta']} ({b['tipo']}) · {x['dia']} {max(x['inicio'],y['inicio']):02}:00–{min(x['fin'],y['fin']):02}:00.")
            else:
                errores.append('Conflicto global: '+x['sesion_motor']+' / '+y['sesion_motor'])
    for fija in e['sesiones']:
        if fija['fijada']:
            actual = next((s for s in sesiones if s['sesion_motor'] == fija['sesion_motor']), None)
            if not actual or any(actual[k] != fija[k] for k in ('actividad_id', 'dia', 'inicio', 'fin', 'ambiente_id')):
                errores.append('Sesión fijada alterada: '+fija['sesion_motor'])
    if completa and vistas != set(esperadas):
        errores.append('Cobertura incompleta del alcance autorizado.')
    if comprobar_orden:
        errores.extend(errores_orden_actividades(e, sesiones))
    return {'valida': not errores, 'errores': errores, 'completa': vistas == set(esperadas),
            'sesiones_esperadas': len(esperadas), 'sesiones_programadas': len(vistas),
            'acreditacion': 'PROPUESTA_DEMANDA_ESTIMADA_NO_MATRICULA_DEFINITIVA'}


def normalizar(e):
    if e.get('contrato') != 'HORARIOS_MOTOR_V1':
        raise InputError('Contrato operativo desconocido')
    config = e['configuracion']
    cal = config['calendario']
    raw = {'schema': 'UNT_CP_SAT_INSTANCE_V1', 'metadata': {'periodo': e['periodo'],
           'calidad': 'OPERACION_V1_DEMANDA_ESTIMADA', 'version': 'ADAPTADOR_V2', **(e.get('metadatos') or {})},
           'calendario': cal, 'cursos': [], 'secciones': [], 'actividades': [], 'patrones': [],
           'sesiones_alternativas': [], 'docentes': [], 'ambientes': [], 'ventanas_disponibilidad': [], 'bloqueos': []}
    raw['politica_conflictos_operativa'] = 'PADRON_Y_COHORTE_ACREDITADA_V1'
    from cpsat_hard.calendar_policy import POLICY
    raw['politica_sabado_repeticion'] = POLICY
    if config.get('politica_grupos_laboratorio') is not None:
        if config['politica_grupos_laboratorio'] != 'ALTERNATIVAS_SIN_ASIGNACION_V1' or config.get('politica_conflictos') != 'SECCION_Y_COMPARTIDOS_V4':
            raise InputError('Política de grupos de laboratorio desconocida o incompatible')
        raw['politica_grupos_laboratorio'] = config['politica_grupos_laboratorio']
    if config.get('politica_conflictos') not in (None, 'PADRON_Y_COHORTE_ACREDITADA_V1', 'CICLO_SECCION_V2', 'CICLO_COMPLETO_V3', 'SECCION_Y_COMPARTIDOS_V4'):
        raise InputError('Política de conflictos desconocida')
    if config.get('orden_actividades') is not None:
        if config['orden_actividades'] not in ('T_P_LAB_SEMANAL_V1', 'T_P_DURO_LAB_BLANDO_V2'):
            raise InputError('Política de orden de actividades desconocida')
        raw['orden_actividades'] = config['orden_actividades']
    if config.get('preferencias_operativas') is not None:
        if config['preferencias_operativas'] not in ('CONTINUIDAD_Y_LAB_V1', 'CONTINUIDAD_TURNOS_Y_SECCIONES_V2'):
            raise InputError('Política de preferencias operativas desconocida')
        raw['preferencias_operativas'] = config['preferencias_operativas']
        if config['preferencias_operativas'] == 'CONTINUIDAD_TURNOS_Y_SECCIONES_V2':
            raw['politica_turnos'] = 'ALTERNANCIA_CICLOS_V2'
    if config.get('politica_conflictos') in ('CICLO_SECCION_V2', 'CICLO_COMPLETO_V3', 'SECCION_Y_COMPARTIDOS_V4'):
        raw['politica_conflictos_operativa'] = config['politica_conflictos']
    secciones = {s['id']: s for s in e['secciones']}
    cursos = {}
    for s in secciones.values():
        if s['estado'] not in ('PROPUESTA', 'AUTORIZADA', 'ACTIVA'):
            raise InputError('La sección está retirada o tiene un estado inválido')
        if not asesoria_sin_poblacion(s) and (poblacion(s) <= 0 or s['capacidad'] is None or poblacion(s) > s['capacidad']):
            raise InputError('Sección vacía o sobre capacidad')
        if str(s['curso_id']) not in cursos:
            cursos[str(s['curso_id'])] = len(cursos)+1
            raw['cursos'].append({'id': len(cursos), 'nombre': s['nombre'], 'tipo': 'REGULAR',
                'ciclo_curricular': s['ciclo'], 'ht': s['horas']['T'], 'hp': s['horas']['P'],
                'hl': s['horas']['LAB'], 'demanda_experimental': 0, 'n_secciones': 0})
        c = raw['cursos'][cursos[str(s['curso_id'])]-1]
        c['demanda_experimental'] += poblacion(s)
        c['n_secciones'] += 1
        raw['secciones'].append({'id': s['id'], 'curso_id': c['id'], 'estudiantes': poblacion(s), 'etiqueta': s['etiqueta'],
                                'es_repeticion': bool(s.get('repeticion')),
                                'tipo_grupo': 'TESIS' if s.get('es_grupo_asesoria') else None, 'poblacion_no_declarada': asesoria_sin_poblacion(s),
                                'capacidad_laboratorio':s.get('capacidad_laboratorio'), 'cantidad_grupos_laboratorio':s.get('cantidad_grupos_laboratorio')})
    excepciones = config.get('excepciones_orden_actividades', [])
    if not isinstance(excepciones, list) or any(type(c) not in (str, int) for c in excepciones):
        raise InputError('Las excepciones de orden deben ser una lista de identificadores de curso')
    raw['excepciones_orden_actividades'] = sorted({cursos[str(c)] for c in excepciones if str(c) in cursos})
    raw['docentes'] = [{'id': str(x), 'nombre': str(x)} for x in sorted({a['docente_id'] for a in e['actividades']})]
    for docente in e.get('docentes', []):
        carga = sum(a['horas'] for a in e['actividades'] if str(a['docente_id']) == str(docente['id']))
        if docente.get('max_horas_semanales') is not None and carga > docente['max_horas_semanales']:
            raise InputError('La carga programable supera el máximo semanal del docente '+str(docente['id']))
        if docente.get('estado') == 'INACTIVO' and carga:
            raise InputError('Hay actividades asignadas a un docente inactivo: '+str(docente['id']))
    raw['ambientes'] = [{'id': r['id'], 'nombre': r['codigo'], 'capacidad': r['capacidad'], 'tipo': r['tipo']}
                       for r in e['ambientes'] if r['activo']]
    disp = {str(d['docente_id']): d for d in e['disponibilidades']}
    candidatas = []
    ids_fisicos = set()
    for a in e['actividades']:
        s = secciones[a['seccion_id']]
        participantes = a.get('secciones_participantes', [a['seccion_id']])
        compartida = 'sesion_fisica_id' in a
        if compartida:
            fisica = a['sesion_fisica_id']
            padrones = a.get('estudiantes_por_seccion')
            if (not isinstance(fisica, str) or not fisica or fisica in ids_fisicos or
                    a['tipo'] not in ('T', 'P') or a['repeticion'] or
                    not isinstance(participantes, list) or len(participantes) < 2 or
                    len(participantes) != len(set(participantes)) or participantes[0] != a['seccion_id'] or
                    not isinstance(padrones, dict) or set(padrones) != set(participantes) or
                    len(a['duraciones']) != 1 or not a.get('ambiente_asignado_id')):
                raise InputError('Sesión física compartida sin identidad o participantes válidos')
            base = (s['curso_id'], s['ciclo'], s.get('plan_id'), s.get('curso_plan_id'))
            ambito = e.get('ambito_ficticio')
            if not isinstance(ambito, dict) or not ambito.get('escuela_id'):
                raise InputError('Falta ámbito ficticio para sesión física')
            for sid in participantes:
                if sid not in secciones or (secciones[sid]['curso_id'], secciones[sid]['ciclo'],
                    secciones[sid].get('plan_id'), secciones[sid].get('curso_plan_id')) != base:
                    raise InputError('Participante físico de otro curso, ciclo o plan')
                if secciones[sid].get('periodo') != e['periodo'] or secciones[sid].get('escuela_id') != ambito['escuela_id']:
                    raise InputError('Participante físico de otro periodo o escuela')
                roster = {x['estudiante_id'] for x in secciones[sid]['estudiantes']}
                ids = padrones[sid]
                if not isinstance(ids, list) or not ids or len(ids) != len(set(ids)) or not set(ids) <= roster:
                    raise InputError('Padrón de sección participante inválido')
            if set(a['estudiantes']) != set().union(*(set(ids) for ids in padrones.values())) or len(a['estudiantes']) != len(set(a['estudiantes'])):
                raise InputError('Población física conjunta incompatible')
            ids_fisicos.add(fisica)
        elif 'secciones_participantes' in a or 'estudiantes_por_seccion' in a:
            raise InputError('Identidad física compartida incompleta')
        if a['repeticion'] and not (type(a.get('ciclo_asistente')) is int and 1 <= a['ciclo_asistente'] <= 10):
            raise InputError('Repetición requiere ciclo asistente autorizado, independiente del currículo')
        if a['tipo'] == 'LAB':
            limite = config.get('capacidad_subgrupo_autorizada')
            if limite is None:
                limite = max((r['capacidad'] for r in e['ambientes'] if r['activo'] and r['tipo'] == 'LAB' and not r.get('es_externo')), default=0)
                limite = limite or s.get('capacidad_laboratorio') or max((r['capacidad'] for r in e['ambientes'] if r['activo'] and r['id'] == a.get('ambiente_asignado_id') and r['tipo']=='LAB'), default=0)
            if type(limite) is not int or limite < 1:
                raise InputError('No hay laboratorios habilitados con capacidad suficiente.')
            raw['politica_operativa'] = {'version': 'HORARIOS_OPERATIVOS_V1', 'capacidad_subgrupo_autorizada': limite}
        if not a['fuente_asignacion'] or sum(a['duraciones']) != a['horas'] or not a['duraciones'] or any(type(d) is not int or d < 1 for d in a['duraciones']):
            raise InputError('Patrón o procedencia de asignación inválidos')
        ids = a['estudiantes']
        roster = {x['estudiante_id'] for x in s['estudiantes']}
        sin_poblacion = asesoria_sin_poblacion(s) and a.get('tipo_grupo') == 'TESIS' and a.get('poblacion_estimada') is None and not ids
        if a.get('poblacion_estimada') is None and not sin_poblacion and (not ids or len(set(ids)) != len(ids) or (not compartida and not set(ids) <= roster)):
            raise InputError('Padrón de actividad inválido')
        ambientes = [r['id'] for r in e['ambientes'] if r['activo'] and room_allowed(r,a) and r['tipo'] == ('LAB' if a['tipo'] == 'LAB' else 'AULA') and r['capacidad'] >= poblacion(a)] if a.get('requiere_ambiente', True) else ['']
        if compartida:
            if a['ambiente_asignado_id'] not in ambientes:
                raise InputError('Ambiente físico incompatible con la población conjunta')
            ambientes = [a['ambiente_asignado_id']]
        if not ambientes:
            raise InputError('Capacidad de ambientes insuficiente para actividad '+a['id'])
        act = {'id': a['id'], 'curso_id': cursos[str(s['curso_id'])], 'curso': s['nombre'],
               'seccion_id': s['id'], 'docente_id': str(a['docente_id']), 'tipo': a['tipo'],
               'horas_semanales': a['horas'], 'estudiantes': poblacion(a), 'poblacion_no_declarada': sin_poblacion,
               **({'estudiantes_ids': ids} if a.get('poblacion_estimada') is None and not sin_poblacion else {}),
               'ciclo_curricular': s['ciclo'], 'ciclo_politica_sabado': a['ciclo_asistente'] if a['repeticion'] else s['ciclo'], 'es_repeticion': a['repeticion'],
               'grupo_repeticion': (['I','II','III','IV','V','VI','VII','VIII','IX','X'][a['ciclo_asistente']-1]+'-REP_'+s['id']) if a['repeticion'] else None,
               'ambientes_candidatos': ambientes if a.get('requiere_ambiente', True) else [], 'requiere_ambiente': a.get('requiere_ambiente', True),
               'tipo_ambiente': 'LAB' if a['tipo'] == 'LAB' else 'AULA',
               'regla_disponibilidad': 'REPETICION_SOLO_M0_RESTRINGIDA' if a['repeticion'] else 'DECLARADA_ESTRICTA', 'cohorte_acreditada': s.get('cohorte_acreditada')}
        if compartida:
            act['sesion_fisica_id'] = a['sesion_fisica_id']
            act['secciones_participantes'] = participantes
            act['estudiantes_por_seccion'] = a['estudiantes_por_seccion']
        if a.get('subgrupo'):
            act['subgrupo_id'] = a['subgrupo']
        elif a['tipo'] == 'LAB' and a.get('grupo_id'):
            act['subgrupo_id'] = a['grupo_id']
        raw['actividades'].append(act)
        raw['disponibilidad_actividad_v2'] = True
        act['ventanas_operativas'] = ventanas_actividad(e, a)
        act['grupo_id'] = a.get('grupo_id')
        act['tipo_grupo'] = a.get('tipo_grupo')
        pid = a['id']+':patron'
        raw['patrones'].append({'id': pid, 'actividad_id': a['id'], 'duraciones': a['duraciones'], 'horas_totales': a['horas'], 'n_sesiones': len(a['duraciones'])})
        for n, dur in enumerate(a['duraciones'], 1):
            sid = a['id']+':'+str(n)
            raw['sesiones_alternativas'].append({'id': sid, 'actividad_id': a['id'], 'patron_id': pid, 'duracion': dur, 'numero': n})
            for dia in dias_actividad(e, a):
                if not dia_permitido(e, a, s, dia): continue
                for h in range(cal['hora_inicial'], cal['hora_final']-dur+1):
                    for r in ambientes:
                        candidatas.append({'id': 'C'+str(len(candidatas)), 'session_id': sid, 'patron_id': pid,
                            'actividad_id': a['id'], 'docente_id': str(a['docente_id']), 'seccion_id': s['id'],
                            'tipo': a['tipo'], 'dia': dia, 'inicio': h, 'fin': h+dur, 'duracion_h': dur, 'ambiente_id': r, 'ciclo': act['ciclo_politica_sabado']})
    for b in e['bloqueos']:
        x = b['datos']
        record = {'id': b['id'], 'alcance': b['alcance'], 'dia': x['dia'], 'inicio': x['inicio'], 'fin': x['fin'], 'fuente': b['fuente']}
        if b['alcance'] == 'COHORTE':
            record.update(ciclo=x['ciclo'], secciones_horario=x['secciones'], incluir_repeticiones=False)
        elif b['alcance'] == 'AMBIENTE':
            record['ambiente'] = x['ambiente_id']
        elif b['alcance'] == 'DOCENTE':
            record['docente_id'] = str(x['docente_id'])
        elif b['alcance'] in ('SECCION', 'CURSO', 'ACTIVIDAD', 'GRUPO', 'CICLO'):
            record.update(x)
            if b['alcance'] == 'CURSO':
                if str(x['curso_id']) not in cursos: continue
                record['curso_id'] = cursos[str(x['curso_id'])]
            if b['alcance'] == 'SECCION' and x['seccion_id'] not in secciones: continue
            if b['alcance'] == 'ACTIVIDAD' and x['actividad_id'] not in {a['id'] for a in e['actividades']}: continue
            if b['alcance'] == 'GRUPO' and x['grupo_id'] not in {a.get('grupo_id') for a in e['actividades']}: continue
        if record['alcance'] == 'GLOBAL_CONDICIONAL': record['alcance'] = 'GLOBAL'
        raw['bloqueos'].append(record)
    if config.get('politica_laboratorios') == 'PARTICION_EXPLICITA_V1':
        labs = [a for a in e['actividades'] if a['tipo'] == 'LAB']
        for a, b in combinations(labs, 2):
            if a['seccion_id'] != b['seccion_id']: continue
            padrones = a.get('poblacion_estimada') is None and b.get('poblacion_estimada') is None and a['estudiantes'] and b['estudiantes'] and not set(a['estudiantes']) & set(b['estudiantes'])
            particion = a.get('particion_acreditada') and a.get('particion_acreditada') == b.get('particion_acreditada') and a.get('grupo_id') != b.get('grupo_id')
            alternativas = raw.get('politica_grupos_laboratorio') == 'ALTERNATIVAS_SIN_ASIGNACION_V1' and (a.get('subgrupo') or a.get('grupo_id')) and (b.get('subgrupo') or b.get('grupo_id')) and (a.get('subgrupo') or a.get('grupo_id')) != (b.get('subgrupo') or b.get('grupo_id'))
            if not (padrones or particion or alternativas): raw.setdefault('conflictos_estudiantes', []).append([a['id'], b['id']])
    return from_records(raw, candidatas)


def unir_franjas(franjas):
    """La selección por celdas representa la unión de intervalos contiguos."""
    resultado = []
    for f in sorted(franjas, key=lambda x: (x['dia'], x['inicio'])):
        if resultado and resultado[-1]['dia'] == f['dia'] and f['inicio'] <= resultado[-1]['fin']:
            resultado[-1]['fin'] = max(resultado[-1]['fin'], f['fin'])
        else:
            resultado.append(dict(f))
    return resultado


def ejecutar(e, progreso=None):
    informar = progreso or (lambda fase: None)
    informar('PREPARANDO')
    aliases = {'validar': 'VALIDAR_HORARIO', 'revisar': 'REVISAR_PROPUESTA'}
    op = aliases.get(e.get('operacion'), e.get('operacion', 'GENERAR_HORARIO'))
    if op == 'GENERAR_PROPUESTA_INTEGRADA':
        from cpsat_hard.sectioning import ejecutar_conjunto
        return ejecutar_conjunto(e)
    if op == 'VALIDAR_PROPUESTA_INTEGRADA':
        from cpsat_hard.sectioning import validate_result
        return validate_result(e, e['resultado_conjunto'])
    if op == 'COMPROBAR_PREPARACION':
        return comprobar_preparacion(e)
    if op == 'CARGA_DOCENTE':
        conflictos = {}
        for d in e.get('docentes', []):
            ids = {a['id'] for a in e['actividades'] if str(a['docente_id']) == str(d['id'])}
            sub = {**e, 'sesiones': [s for s in e['sesiones'] if s['actividad_id'] in ids]}
            conflictos[str(d['id'])] = validar_operacion(sub, sub['sesiones'])['errores']
        return {'contrato': 'HORARIOS_RESULTADO_V1', 'conflictos': conflictos}
    if op == 'MEDIR_CALIDAD':
        from cpsat_soft.quality import measure
        return {'contrato':'HORARIOS_RESULTADO_V1','calidad_academica':measure(e,e.get('sesiones_candidatas',e['sesiones']))}
    if op not in {'VALIDAR_ENTRADA', 'VALIDAR_HORARIO', 'GENERAR_HORARIO', 'COMPLETAR_HORARIO',
                  'REORGANIZAR_HORARIO', 'REVISAR_PROPUESTA'}:
        raise InputError('Operación desconocida')
    if op == 'VALIDAR_HORARIO':
        return {'contrato': 'HORARIOS_RESULTADO_V1', 'validacion_operativa': validar_operacion(e, e.get('sesiones_candidatas', e['sesiones']), e.get('completa', False))}
    inicio_motor = time.monotonic()
    entrada_modelo = e
    if op in ('GENERAR_HORARIO', 'COMPLETAR_HORARIO', 'REORGANIZAR_HORARIO') and e.get('alcance'):
        seleccionadas = set(e['alcance'])
        conocidas = {a['id'] for a in e['actividades']}
        if not seleccionadas <= conocidas:
            raise InputError('Alcance desconocido')
        conservar = {s['actividad_id'] for s in e['sesiones']}
        incluidas = seleccionadas | conservar
        acts = [a for a in e['actividades'] if a['id'] in incluidas]
        secs = {a['seccion_id'] for a in acts}
        entrada_modelo = {**e, 'actividades': acts, 'secciones': [s for s in e['secciones'] if s['id'] in secs],
                          'alcance_parcial': incluidas != conocidas}
    i = normalizar(entrada_modelo)
    if entrada_modelo.get('alcance_parcial'):
        i.raw['alcance_parcial'] = True
    if op == 'VALIDAR_ENTRADA':
        return {'contrato': 'HORARIOS_RESULTADO_V1', 'estado': 'ENTRADA_VALIDA',
                'actividades': len(i.activities), 'candidatas': len(i.placements),
                'advertencias': ['La normalización no acredita factibilidad ni autorización institucional.']}
    from cpsat_hard.validation import validate
    if op == 'REVISAR_PROPUESTA':
        outcome = {'patrones_elegidos': list(i.patterns), 'sesiones_elegidas': []}
        for s in e['sesiones']:
            matching = [c for c in i.placements if c.session_id == s['sesion_motor'] and
                        (c.dia, c.inicio, c.fin, c.ambiente_id) == (s['dia'], s['inicio'], s['fin'], s['ambiente_id'] or '')]
            if len(matching) != 1:
                raise InputError('Sesión registrada fuera de las candidatas vigentes')
            from dataclasses import asdict
            outcome['sesiones_elegidas'].append(asdict(matching[0]))
        return {'contrato': 'HORARIOS_RESULTADO_V1', 'estado': 'VALIDACION',
                'validacion_historica': validate(i, outcome),
                'validacion_operativa': validar_operacion(e, e['sesiones'], completa=True)}
    import ortools
    from ortools.sat.python import cp_model
    from cpsat_hard.model import build
    from cpsat_hard.exporting import schedule
    from cpsat_soft.model import add as add_soft
    from cpsat_soft.specification import prepare
    from cpsat_soft.metrics import evaluate
    built = build(i)
    seleccion = set(e.get('alcance', [])) or set(i.activities)
    if not seleccion <= set(i.activities):
        raise InputError('Alcance desconocido')
    for s in e['sesiones']:
        if op == 'COMPLETAR_HORARIO' or s['fijada'] or s['actividad_id'] not in seleccion:
            matches = [j for j, c in enumerate(i.placements) if c.session_id == s['sesion_motor'] and
                       (c.dia, c.inicio, c.fin, c.ambiente_id) == (s['dia'], s['inicio'], s['fin'], s['ambiente_id'] or '')]
            if len(matches) != 1:
                raise InputError('Sesión conservada fuera de las candidatas vigentes')
            built.model.add(built.placements[matches[0]] == 1)
    presupuesto = max(1, min(1800, e.get('tiempo_limite', 30)))
    tiempo_arranque = 0.0
    arranque_estado = None
    pistas_arranque = 0
    nuevo_itinerario = i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4'
    perfil=e.get('perfil_calidad','PONDERADA_V2')
    if perfil not in ('PONDERADA_V2','CALIDAD_JERARQUICA_V1','CALIDAD_PARETO_PERSONAL_V1','CALIDAD_VECINDADES_V1','CALIDAD_PRIORIDADES_V2'):
        raise InputError('Perfil de calidad desconocido')
    usar_calidad=perfil!='PONDERADA_V2' and nuevo_itinerario and i.raw.get('preferencias_operativas') in ('CONTINUIDAD_Y_LAB_V1','CONTINUIDAD_TURNOS_Y_SECCIONES_V2') and not entrada_modelo.get('alcance_parcial') and validar_operacion(e,e['sesiones'],completa=True)['valida']
    from cpsat_soft.teacher_priority import participants as priority_participants
    docentes_prioritarios=priority_participants(entrada_modelo)
    priority_run=None
    quality_run=None
    trabajadores = e['configuracion'].get('trabajadores_solver', 1) if nuevo_itinerario else 1
    if type(trabajadores) is not int or not 1 <= trabajadores <= 8:
        raise InputError('trabajadores_solver debe ser un entero entre 1 y 8')
    if (e.get('priorizar_factibilidad') and not usar_calidad) or docentes_prioritarios:
        informar('FACTIBILIDAD')
        arranque = cp_model.CpSolver()
        arranque.parameters.max_time_in_seconds = max(0.1, min(60, presupuesto / 2))
        arranque.parameters.num_search_workers = 1
        arranque.parameters.random_seed = 1
        modelo_arranque = built.model
        if nuevo_itinerario:
            from cpsat_hard.search_support import seed_model
            modelo_arranque, pistas_arranque = seed_model(i, built, e['sesiones'])
            arranque.parameters.max_time_in_seconds = max(0.1, min(180, presupuesto * 0.65))
            arranque.parameters.repair_hint = True
            arranque.parameters.num_search_workers = trabajadores
        arranque_status = arranque.solve(modelo_arranque)
        tiempo_arranque = arranque.wall_time
        arranque_estado = arranque.status_name(arranque_status)
        if arranque_status in (cp_model.FEASIBLE, cp_model.OPTIMAL):
            # Punto inicial compatible con todas las restricciones HARD y clases fijadas.
            # No fija las decisiones: la búsqueda posterior conserva el objetivo SOFT completo.
            for variable in [*built.patterns.values(), *built.placements.values()]:
                built.model.add_hint(variable, arranque.value(variable))
            for indice in built.labels:
                built.model.add_hint(built.model.get_bool_var_from_proto_index(indice), 1)
    from cpsat_hard.calendar_policy import planning_days
    spec = prepare(i, planning_days(i), lab_source='CONFIGURACION_PROPUESTA')
    penalties = add_soft(i, built, spec, variant=DEFAULT_VARIANT)
    base = sum(spec['normalizacion']['coeficientes'][k]*v for k, v in penalties.items())
    preferencias = {s['id']: {x['estudiante_id']: x['preferencia'] for x in s['estudiantes']} for s in e['secciones']}
    estudiantil = 0
    cota = 0
    for j, c in enumerate(i.placements):
        a = i.activities[c.actividad_id]
        costo = sum(sum(1 for h in range(c.inicio, c.fin) if (pref == 'MANANA' and h >= 14) or (pref == 'TARDE' and h < 13))
                    for sid, pref in preferencias[c.seccion_id].items() if sid in a.get('estudiantes_ids', []))
        if not e['configuracion'].get('preferencias_estudiantiles', False):
            costo = 0
        preferencia_actividad = next((x.get('preferencia', 'SIN_PREFERENCIA') for x in e['actividades'] if x['id'] == a['id']), 'SIN_PREFERENCIA')
        costo += sum(1 for h in range(c.inicio, c.fin) if (preferencia_actividad == 'MANANA' and h >= 14) or (preferencia_actividad == 'TARDE' and h < 13)) * a['estudiantes']
        estudiantil += costo*built.placements[j]
    for a in i.activities.values():
        cota += a['horas_semanales']*a['estudiantes']*(2 if any(x.get('preferencia', 'SIN_PREFERENCIA') != 'SIN_PREFERENCIA' for x in e['actividades']) else 1)
    preferencias_docentes = {str(d['docente_id']): d.get('preferencia', 'SIN_PREFERENCIA') for d in e['disponibilidades']}
    docente_soft = sum(sum(1 for h in range(c.inicio, c.fin)
                      if (preferencias_docentes.get(str(c.docente_id)) == 'MANANA' and h >= 14) or
                         (preferencias_docentes.get(str(c.docente_id)) == 'TARDE' and h < 13)) * built.placements[j]
                       for j, c in enumerate(i.placements))
    cota_docente = sum(a['horas_semanales'] for a in i.activities.values())
    estudiantil = estudiantil * (cota_docente+1) + docente_soft
    cota = cota * (cota_docente+1) + cota_docente
    ampliada = bool(e['configuracion'].get('preferencias_estudiantiles', False) or any(v != 'SIN_PREFERENCIA' for v in preferencias_docentes.values()) or any(a.get('preferencia', 'SIN_PREFERENCIA') != 'SIN_PREFERENCIA' for a in e['actividades']))
    objetivo_base = base*(cota+1)+estudiantil if ampliada else base
    # Preferencia adicional y acotada: ningún cruce de asesorías vuelve infactible
    # una entrada que respeta docentes, padrones, ambientes y calendario.
    grupos_tesis = {}
    for j, c in enumerate(i.placements):
        a = i.activities[c.actividad_id]
        if a.get('tipo_grupo') != 'TESIS' or not a.get('grupo_id'):
            continue
        for h in range(c.inicio, c.fin):
            grupos_tesis.setdefault((a['ciclo_curricular'], c.dia, h), {}).setdefault(a['grupo_id'], []).append(built.placements[j])
    cruces_tesis = []
    for grupos in grupos_tesis.values():
        ocupaciones = []
        for candidatas in grupos.values():
            ocupada = built.model.new_bool_var(f'tesis_ocupada_{len(ocupaciones)}_{len(cruces_tesis)}')
            built.model.add_max_equality(ocupada, candidatas)
            ocupaciones.append(ocupada)
        for izquierda, derecha in combinations(ocupaciones, 2):
            cruce = built.model.new_bool_var(f'cruce_tesis_{len(cruces_tesis)}')
            built.model.add(cruce <= izquierda)
            built.model.add(cruce <= derecha)
            built.model.add(cruce >= izquierda+derecha-1)
            cruces_tesis.append(cruce)
    operativo = None
    if i.raw.get('preferencias_operativas') in ('CONTINUIDAD_Y_LAB_V1', 'CONTINUIDAD_TURNOS_Y_SECCIONES_V2'):
        from cpsat_soft.operational import add as add_operational
        objective, operativo, operational_terms = add_operational(i, built, penalties, spec, estudiantil, cota if ampliada else 0, cruces_tesis,
            reference={s['sesion_motor']: s['ambiente_id'] for s in e['sesiones']})
        built.model.minimize(objective)
    else:
        built.model.minimize(objetivo_base*(len(cruces_tesis)+1)+sum(cruces_tesis))
        objective = objetivo_base*(len(cruces_tesis)+1)+sum(cruces_tesis)
    lab_cost = None
    if i.raw.get('politica_grupos_laboratorio') == 'ALTERNATIVAS_SIN_ASIGNACION_V1':
        from cpsat_soft.lab_alternatives import add as add_lab_compatibility
        lab_cost = add_lab_compatibility(i,built)
    if docentes_prioritarios:
        informar('OPTIMIZANDO')
        from cpsat_soft.teacher_priority import optimize as optimize_priority
        semilla=arranque if arranque_estado in ('FEASIBLE','OPTIMAL') else None
        referencia_valida=bool(entrada_modelo['sesiones']) and validar_operacion(entrada_modelo,entrada_modelo['sesiones'],completa=True)['valida']
        solver,status,priority_run=optimize_priority(entrada_modelo,i,built,objective,max(.01,presupuesto-tiempo_arranque),trabajadores,semilla,reference_valid=referencia_valida)
        priority_run['alcance_generacion']='ACTIVIDADES_INCLUIDAS' if entrada_modelo.get('alcance_parcial') else 'PERIODO_COMPLETO'
        tiempo_optimizacion=priority_run['tiempo_s']
        cota_global=None
        rescate=pulido=ajuste_horas=ajuste_labs=None
    elif usar_calidad:
        informar('OPTIMIZANDO')
        from cpsat_soft.hierarchy import optimize,ReferenceTimeout
        try:
            solver,status,quality_run=optimize(e,i,built,objective,operational_terms,lab_cost,presupuesto,trabajadores)
        except ReferenceTimeout as error:
            return {'contrato':'HORARIOS_RESULTADO_V1','estado':'UNKNOWN','sesiones':[],
                'tiempo_limite_s': presupuesto, 'tiempo_busqueda_s': error.tiempo_s,
                'tiempo_preparacion_s': max(0.0, time.monotonic() - inicio_motor - error.tiempo_s),
                'tiempo_validacion_diagnostico_s': 0.0, 'tiempo_total_motor_s': time.monotonic() - inicio_motor,
                'diagnosticos':[{'categoria':'BUSQUEDA','detalle':str(error)}]}
        tiempo_optimizacion=quality_run['tiempo_s']
        arranque_estado='REFERENCIA_VALIDADA'
        cota_global=None
        rescate=pulido=ajuste_horas=ajuste_labs=None
    else:
        solver = cp_model.CpSolver()
        reserva_aulas = min(15, presupuesto / 8) if operativo is not None and presupuesto >= 20 else 0
        reserva_horas = min(5, presupuesto / 24) if operativo is not None and presupuesto >= 20 else 0
        reserva_rescate = min(8, presupuesto / 12) if nuevo_itinerario and arranque_estado in ('FEASIBLE','OPTIMAL') else 0
        reserva_labs = min(10, presupuesto / 10) if lab_cost is not None and presupuesto >= 20 else 0
        solver.parameters.max_time_in_seconds = max(0.1, presupuesto - tiempo_arranque - reserva_aulas - reserva_horas - reserva_rescate - reserva_labs)
        solver.parameters.num_search_workers = trabajadores
        solver.parameters.random_seed = 1
        if arranque_estado == 'INFEASIBLE':
            # Las preferencias añadidas no pueden hacer factible un modelo HARD
            # que ya se demostró incompatible. Conservar la prueba y su diagnóstico.
            solver = arranque
            status = cp_model.INFEASIBLE
            tiempo_optimizacion = 0.0
        else:
            informar('OPTIMIZANDO')
            modelo_busqueda = built.model
            if trabajadores > 1:
                from cpsat_hard.search_support import fixed_assumptions_copy
                modelo_busqueda = fixed_assumptions_copy(built.model)
            status = solver.solve(modelo_busqueda)
            tiempo_optimizacion = solver.wall_time
        cota_global = solver.best_objective_bound
        rescate = None
        if status == cp_model.UNKNOWN and reserva_rescate:
            from cpsat_hard.search_support import recover_seed
            recuperado, estado_recuperado = recover_seed(built, arranque, reserva_rescate)
            tiempo_optimizacion += recuperado.wall_time
            rescate = {'estado': recuperado.status_name(estado_recuperado), 'tiempo_s': recuperado.wall_time,
                'alcance': 'Solución HARD inicial comprobada en el modelo SOFT; no certifica óptimo global.'}
            if estado_recuperado in (cp_model.FEASIBLE,cp_model.OPTIMAL):
                solver, status = recuperado, cp_model.FEASIBLE
        pulido = None
        ajuste_horas = None
        if status == cp_model.FEASIBLE and reserva_aulas:
            informar('AULAS')
            from cpsat_soft.operational import improve_rooms
            solver, pulido = improve_rooms(i, built, solver, reserva_aulas)
            tiempo_optimizacion += pulido['tiempo_s']
        if status == cp_model.FEASIBLE and reserva_horas:
            informar('HORAS_SUELTAS')
            from cpsat_soft.operational import improve_single_hours
            solver, ajuste_horas = improve_single_hours(i, built, solver, reserva_horas)
            tiempo_optimizacion += ajuste_horas['tiempo_s']
        ajuste_labs = None
        if status in (cp_model.FEASIBLE,cp_model.OPTIMAL) and reserva_labs:
            informar('OPTIMIZANDO')
            from cpsat_soft.lab_alternatives import improve as improve_labs
            solver,ajuste_labs=improve_labs(built,solver,objective,lab_cost,reserva_labs,trabajadores)
            tiempo_optimizacion += ajuste_labs['tiempo_s']
            status = cp_model.FEASIBLE
    fin_busqueda = time.monotonic()
    tiempo_solver_total = tiempo_arranque + tiempo_optimizacion
    tiempo_preparacion = max(0.0, fin_busqueda - inicio_motor - tiempo_solver_total)
    salida = {'contrato': 'HORARIOS_RESULTADO_V1', 'estado': solver.status_name(status),
              'version_adaptador': 'HORARIOS_MOTOR_V1', 'politica_operativa': i.raw.get('politica_operativa'),
              'version_motor': MODEL_VERSION, 'variante_oficial': DEFAULT_VARIANT,
              'orden_actividades': i.raw.get('orden_actividades'),
              'excepciones_orden_actividades': e['configuracion'].get('excepciones_orden_actividades', []),
              'preferencias_operativas': operativo,
              'tiempo_limite_s': presupuesto, 'tiempo_busqueda_s': tiempo_solver_total,
              'tiempo_preparacion_s': tiempo_preparacion, 'tiempo_validacion_diagnostico_s': 0.0,
              'parametros_busqueda': {'trabajadores': trabajadores, 'semilla': 1},
              'pulido_aulas': pulido,
              'ajuste_horas_sueltas': ajuste_horas,
              'ajuste_compatibilidad_laboratorios': ajuste_labs,
              'politica_grupos_laboratorio': i.raw.get('politica_grupos_laboratorio'),
              'arranque_factibilidad': {'estado': arranque_estado, 'tiempo_s': tiempo_arranque, 'pistas_del_horario_guardado': pistas_arranque},
              'rescate_solucion_inicial': rescate,
              'optimizacion_calidad': quality_run,
              'prioridad_docente': priority_run,
              'objetivo': ('LEX_SOFT_OFICIAL_PREFERENCIAS_V2' if any(x.get('preferencia', 'SIN_PREFERENCIA') != 'SIN_PREFERENCIA' for x in e['actividades']) else 'LEX_SOFT_OFICIAL_PREFERENCIAS_V1') if ampliada else 'SOFT_OFICIAL_V1',
              'versiones': {'python': platform.python_version(), 'ortools': ortools.__version__},
              'sha256_puente': hashlib.sha256(open(__file__, 'rb').read()).hexdigest(),
              'codigo_sha256': {str(p.relative_to(Path(__file__).resolve().parent.parent)): hashlib.sha256(p.read_bytes()).hexdigest()
                               for carpeta in ('cpsat_hard', 'cpsat_soft') for p in sorted((Path(__file__).resolve().parent.parent/carpeta).glob('*.py'))},
              'sesiones': [], 'diagnosticos': []}
    if status not in (cp_model.FEASIBLE, cp_model.OPTIMAL):
        if status == cp_model.UNKNOWN:
            salida['diagnosticos'] = [{'categoria': 'BUSQUEDA', 'detalle': f'No se encontró una solución dentro del límite de {presupuesto:g} segundos. Este resultado no demuestra que el horario sea imposible.'}]
            salida['tiempo_validacion_diagnostico_s'] = time.monotonic() - fin_busqueda
            salida['tiempo_total_motor_s'] = time.monotonic() - inicio_motor
            return salida
        salida['diagnosticos'] = [{'categoria': 'FACTIBILIDAD', 'detalle': 'Revisar secciones, padrones, docentes, ambientes, bloqueos y sesiones fijadas; no se eliminó demanda.'},
                                  {'categoria': 'DOMINIOS', 'candidatas_rechazadas': len(built.rejected)}]
        if status == cp_model.INFEASIBLE:
            from cpsat_hard.diagnostics import diagnose
            docentes = {str(d['id']): d['nombre'] for d in e.get('docentes', [])}
            for deficit in diagnose(i, built, solver, 'INFEASIBLE')['cotas_docentes_insuficientes']:
                nombre = docentes.get(str(deficit['docente_id']), str(deficit['docente_id']))
                salida['diagnosticos'].append({**deficit, 'categoria': 'CAPACIDAD_DOCENTE',
                    'detalle': f"{nombre}: tiene {deficit['carga']} horas asignadas y solo {deficit['horas_unicas_en_dominio']} horas compatibles con su disponibilidad y las restricciones. Faltan {deficit['deficit']} horas. Revise la carga o registre las franjas reales que falten."})
            from cpsat_hard.diagnostics import activity_order_shortages
            names = {'T': 'teoría', 'P': 'práctica', 'LAB': 'laboratorio'}
            for shortage in activity_order_shortages(i, built):
                sec = i.sections[shortage['seccion_id']]
                course = i.courses[shortage['curso_id']]
                salida['diagnosticos'].append({**shortage, 'categoria': 'ORDEN_ACTIVIDADES',
                    'detalle': f"{course['nombre']} · sección {sec['etiqueta']}: después de terminar {names[shortage['despues_de']]} quedan como máximo {shortage['horas_compatibles']} horas compatibles para {names[shortage['tipo']]}, pero necesita {shortage['horas_requeridas']}. Revise la disponibilidad docente y las restricciones."})
            for cota in built.cycle_bounds:
                if cota['deficit'] <= 0:
                    continue
                cursos = ', '.join(i.courses[c['curso_id']]['nombre'] for c in cota['cursos'])
                salida['diagnosticos'].append({
                    'categoria': 'CAPACIDAD_HORARIA', 'ciclo': cota['ciclo'], 'alcance': cota['seccion_horario'],
                    'detalle': f"Ciclo {cota['ciclo']} · {'todas las secciones' if cota['seccion_horario'] == 'TODAS_SECCIONES' else cota['seccion_horario']} — {cursos}: las alternativas de sus cursos necesitan al menos {cota['minimo_horas_cursos_sin_cruce']} horas semanales de ocupación sin cruces, pero las franjas compatibles permiten {cota['horas_unicas_en_dominio']}. Faltan {cota['deficit']} horas. Revise las secciones, asignaciones, disponibilidad docente y restricciones.",
                    'horas_requeridas': cota['minimo_horas_cursos_sin_cruce'],
                    'horas_compatibles': cota['horas_unicas_en_dominio'], 'deficit': cota['deficit'],
                })
            if nuevo_itinerario and not any(d['categoria'] in ('CAPACIDAD_DOCENTE','CAPACIDAD_HORARIA','ORDEN_ACTIVIDADES') for d in salida['diagnosticos']):
                from cpsat_hard.diagnostics import itinerary_infeasibility
                proof = itinerary_infeasibility(e)
                if proof: salida['diagnosticos'].append(proof)
            for diagnostic in salida['diagnosticos']:
                if diagnostic['categoria'] in ('CAPACIDAD_DOCENTE','CAPACIDAD_HORARIA','ORDEN_ACTIVIDADES'):
                    diagnostic['certeza'] = 'COMPROBADA'
                elif diagnostic['categoria'] == 'FACTIBILIDAD':
                    diagnostic['certeza'] = 'ORIENTACION'
        salida['tiempo_validacion_diagnostico_s'] = time.monotonic() - fin_busqueda
        salida['tiempo_total_motor_s'] = time.monotonic() - inicio_motor
        return salida
    if cruces_tesis:
        salida['objetivo'] = 'LEX_SOFT_Y_PREFERENCIA_GRUPOS_TESIS_V1'
        salida['preferencia_grupos_tesis'] = {'tipo': 'BLANDA', 'horas_de_cruce': solver.value(sum(cruces_tesis))}
    if operativo is not None:
        salida['objetivo'] = ('SOFT_OPERATIVO_TURNOS_Y_SECCIONES_V2' if i.raw.get('preferencias_operativas') == 'CONTINUIDAD_TURNOS_Y_SECCIONES_V2' else 'SOFT_OPERATIVO_CONTINUIDAD_V1')
    if quality_run is not None:salida['objetivo']=quality_run['perfil']
    if priority_run is not None:
        salida['objetivo']='SOFT_P01_LEXICOGRAFICA_V1'
        salida['prioridad_docente']=priority_run
    informar('VALIDANDO')
    outcome = schedule(i, built, solver)
    hard = validate(i, outcome)
    actuales = {s['sesion_motor']: s for s in e['sesiones']}
    for s in outcome['sesiones_elegidas']:
        if s['actividad_id'] in seleccion or s['session_id'] in actuales:
            previa = actuales.get(s['session_id'], {})
            conservada = previa and (previa.get('fijada') or op == 'COMPLETAR_HORARIO' or s['actividad_id'] not in seleccion)
            actividad_fisica = next(a for a in entrada_modelo['actividades'] if a['id'] == s['actividad_id'])
            salida['sesiones'].append({'sesion_motor': s['session_id'], 'actividad_id': s['actividad_id'], 'dia': s['dia'],
                'inicio': s['inicio'], 'fin': s['fin'], 'ambiente_id': s['ambiente_id'] or None,
                'fijada': previa.get('fijada', False), 'origen': previa.get('origen', 'CP_SAT') if conservada else 'CP_SAT',
                **({'sesion_fisica_id': actividad_fisica['sesion_fisica_id'],
                    'secciones_participantes': actividad_fisica['secciones_participantes']}
                   if 'sesion_fisica_id' in actividad_fisica else {})})
    salida['validacion_historica'] = hard
    salida['validacion_operativa'] = validar_operacion(e, salida['sesiones'])
    salida['metricas_oficiales'] = evaluate(i, outcome, spec)
    if operativo is not None:
        from cpsat_soft.operational import evaluate as evaluate_operational
        salida['preferencias_operativas']['evaluacion'] = evaluate_operational(i, outcome['sesiones_elegidas'],
            reference={s['sesion_motor']: s['ambiente_id'] for s in e['sesiones']})
        salida['preferencias_operativas']['valores_modelo'] = {key: solver.value(value) for key,value in operational_terms.items()}
    salida['valor_objetivo'] = solver.objective_value
    if priority_run is not None:salida['valor_objetivo']=solver.value(objective)
    if quality_run is not None:salida['valor_objetivo']=solver.value(objective)
    if lab_cost is not None:
        from cpsat_soft.lab_alternatives import evaluate as evaluate_lab_compatibility
        salida['compatibilidad_laboratorios'] = evaluate_lab_compatibility(i,outcome['sesiones_elegidas'])
        salida['compatibilidad_laboratorios']['valor_modelo'] = solver.value(lab_cost)
        salida['valor_objetivo'] = solver.value(objective)
    salida['mejor_cota'] = cota_global
    salida['horas_estudiante_fuera_preferencia'] = solver.value(estudiantil)
    salida['tiempo_s'] = tiempo_arranque + tiempo_optimizacion
    from cpsat_soft.quality import measure
    salida['calidad_academica']=measure(e,salida['sesiones'])
    if priority_run is not None:
        independientes={d['docente_id']:d for d in salida['calidad_academica']['docentes']}
        campos={'huecos':'huecos','dias':'dias','preferencias':'horas_fuera_preferencia','cortas':'jornadas_cortas'}
        for docente,valores in priority_run['calidad_final'].items():
            if any(independientes[docente][campos[k]]!=v for k,v in valores.items()):
                raise InputError('Indicadores de prioridad docente incoherentes con las sesiones')
        priority_run['indicadores_verificados']=True
    salida['tiempo_validacion_diagnostico_s'] = time.monotonic() - fin_busqueda
    salida['tiempo_total_motor_s'] = time.monotonic() - inicio_motor
    return salida


def main():
    try:
        e = json.load(sys.stdin)
        def progreso(fase):
            print('HORARIOS_PROGRESO '+json.dumps({'fase': fase}), file=sys.stderr, flush=True)
        salida = ejecutar(e, progreso=progreso)
    except (InputError, ValueError, KeyError, TypeError) as exc:
        salida = {'contrato': 'HORARIOS_RESULTADO_V1', 'estado': 'INPUT_INVALID', 'sesiones': [],
                  'diagnosticos': [{'categoria': 'ENTRADA', 'detalle': str(exc)}]}
    json.dump(salida, sys.stdout, ensure_ascii=True)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
