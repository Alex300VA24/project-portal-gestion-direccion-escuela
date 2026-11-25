"""Traducción del contrato de responsabilidades por tipo y grupo de laboratorio."""


def responsibility(course, typ, group=0):
    rows = course.get('responsabilidades', [])
    return next((r for r in rows if r['tipo'] == typ and r['grupo'] == group), None) or next(
        (r for r in rows if r['tipo'] == typ and r['grupo'] == 0), None) or {
            'docente_id': course.get('docentes', {}).get(typ), 'ambiente_id': None, 'franjas': None}


def room_allowed(room, activity):
    assigned = activity.get('ambiente_asignado_id')
    return (not assigned or room['id'] == assigned) and (not room.get('es_externo', False) or room['id'] == assigned)


def compatible_rooms(rooms, typ, rule):
    return [r for r in rooms if r['activo'] and r['tipo'] == ('LAB' if typ == 'LAB' else 'AULA')
            and room_allowed(r, {'ambiente_asignado_id': rule.get('ambiente_id')})]
