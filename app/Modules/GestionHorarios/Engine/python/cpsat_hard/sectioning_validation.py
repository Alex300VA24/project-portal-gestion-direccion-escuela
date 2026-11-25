"""Verificación de la demanda original, independiente de la generación de alternativas."""
from collections import Counter


def validate_sectioning(original, projected):
    errors = []
    offer = original['oferta_conjunta']
    courses = {str(c['id']): c for c in offer['cursos']}
    totals = Counter()
    labels = set()
    rosters = {}
    for sec in projected['secciones']:
        course = courses.get(str(sec['curso_id']))
        if not course:
            errors.append('Curso ajeno a la oferta')
        elif not sec.get('repeticion'):
            expected = {'T':course['horas_teoria'],'P':course['horas_practica'],'LAB':course['horas_laboratorio']}
            if sec['horas'] != expected or sec['ciclo'] != course['ciclo']:
                errors.append('Horas o ciclo distintos de la oferta')
        key = (str(sec['curso_id']), sec['etiqueta'])
        if key in labels:
            errors.append('Sección de curso duplicada')
        labels.add(key)
        size = sec.get('poblacion_estimada')
        size = len(sec['estudiantes']) if size is None else size
        if type(size) is not int or size < 1 or size > sec['capacidad']:
            errors.append('Población de sección inválida')
        totals[str(sec['curso_id'])] += size
        if offer.get('modo_demanda') == 'INDIVIDUAL':
            cid = str(sec['curso_id'])
            known = rosters.setdefault(cid, set())
            ids = [s['estudiante_id'] for s in sec['estudiantes']]
            if len(ids) != len(set(ids)) or known.intersection(ids):
                errors.append('Estudiante duplicado en curso '+cid)
            known.update(ids)
    for cid, course in courses.items():
        if course.get('tipo_oferta', 'REGULAR') != 'REGULAR':
            continue
        demand = course.get('demanda_estimada', 0)
        if offer.get('modo_demanda') == 'INDIVIDUAL':
            demand = sum(str(s['curso_id']) == cid and s['estado'] == 'VALIDA' for s in offer.get('solicitudes', []))
        if totals[cid] != demand:
            errors.append('Demanda no conservada del curso '+cid)
        if offer.get('modo_demanda') == 'INDIVIDUAL':
            expected = {s['estudiante_id'] for s in offer['solicitudes'] if str(s['curso_id']) == cid and s['estado'] == 'VALIDA'}
            if rosters.get(cid,set()) != expected:
                errors.append('Padrón no conservado del curso '+cid)
    docs = {str(d['id']): d for d in original['docentes']}
    load = Counter()
    previous = {a['id']: a for a in original['actividades']}
    sections = {s['id']: s for s in projected['secciones']}
    for a in projected['actividades']:
        did = str(a['docente_id'])
        load[did] += a['horas']
        if did not in docs or docs[did].get('estado', 'ACTIVO') != 'ACTIVO':
            errors.append('Docente inexistente o inactivo')
        old = previous.get(a['id'])
        if not old or str(old['docente_id']) != did:
            cid = str(sections[a['seccion_id']]['curso_id'])
            course = courses.get(cid,{})
            group = int(a['subgrupo'].rsplit(':',1)[-1]) if a['tipo']=='LAB' else 0
            rules = course.get('responsabilidades',[])
            rule = next((r for r in rules if r['tipo']==a['tipo'] and r['grupo']==group),None) or next((r for r in rules if r['tipo']==a['tipo'] and r['grupo']==0),None)
            expected = rule.get('docente_id') if rule else course.get('docentes',{}).get(a['tipo'])
            if str(expected or '') != did:
                errors.append('Asignación de curso no autorizada: '+cid)
            if a.get('ambiente_asignado_id') != (rule.get('ambiente_id') if rule else None) or a.get('franjas_origen') != (rule.get('franjas') if rule else None):
                errors.append('Ambiente o disponibilidad del curso alterados: '+cid)
            if a.get('requiere_ambiente', True) != courses.get(cid, {}).get('requiere_ambiente', True):
                errors.append('Requisito de ambiente alterado: '+cid)
    for did, hours in load.items():
        maximum = docs.get(did, {}).get('max_horas_semanales')
        if maximum is not None and hours > maximum:
            errors.append('Máximo semanal docente excedido: '+did)
    for fixed in (s for s in original['sesiones'] if s.get('fijada')):
        if fixed['actividad_id'] not in {a['id'] for a in projected['actividades']}:
            errors.append('Actividad fijada retirada')
    return {'valida': not errors, 'errores': sorted(set(errors)), 'demanda_conservada': not any('Demanda' in e for e in errors)}
