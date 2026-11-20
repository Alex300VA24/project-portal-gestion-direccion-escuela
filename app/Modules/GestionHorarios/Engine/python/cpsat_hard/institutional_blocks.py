"""Overlay privado de H21 por cohorte; separado de reservas físicas H22."""
import copy
import hashlib
import json
from pathlib import Path
from .data import InputError

SCHEMA = 'INSTITUTIONAL_BLOCKS_V1'


def attach(raw, path):
    path = Path(path)
    source = json.loads(path.read_text(encoding='utf-8'))
    if source.get('version') != SCHEMA or not source.get('periodo') or not source.get('fuente'):
        raise InputError('Bloqueos institucionales: versión, periodo y fuente obligatorios')
    if raw.get('metadata',{}).get('periodo') not in (None,source['periodo']):
        raise InputError('Bloqueos institucionales de otro periodo académico')
    rows = source.get('bloqueos')
    if not isinstance(rows,list) or not rows:
        raise InputError('Bloqueos institucionales: se requiere una lista no vacía')
    ids = {b['id'] for b in raw['bloqueos']}
    result = copy.deepcopy(raw)
    for row in rows:
        if not isinstance(row,dict) or not isinstance(row.get('id'),str) or not row['id'].strip() or row['id'] in ids:
            raise InputError('Bloqueo institucional duplicado o sin identificador')
        ids.add(row['id'])
        b = copy.deepcopy(row)
        b.setdefault('fuente',source['fuente'])
        if b.get('alcance') == 'COHORTE':
            labels = b.get('secciones_horario',[])
            if not isinstance(labels,list) or any(not isinstance(label,str) for label in labels):
                raise InputError('Bloqueo académico: secciones_horario debe ser una lista de etiquetas')
            for label in labels:
                if not any(not a['es_repeticion'] and a['ciclo_curricular']==b.get('ciclo')
                           and next(s for s in raw['secciones'] if s['id']==a['seccion_id'])['etiqueta'].strip().upper()==label
                           for a in raw['actividades']):
                    raise InputError('Bloqueo académico: ciclo/sección de horario inexistente')
        result['bloqueos'].append(b)
    result['bloqueos_institucionales'] = {
        'version':SCHEMA,'periodo':source['periodo'],'fuente':source['fuente'],
        'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'ids':[r['id'] for r in rows]}
    return result


def targets(i, block, activity):
    """Solo constructor/precheck; el validador implementa su propio contraste."""
    if activity['es_repeticion']:
        if not block.get('incluir_repeticiones',False):
            return False
        parts = activity.get('grupo_repeticion','').split('-',1)
        romans = ['I','II','III','IV','V','VI','VII','VIII','IX','X']
        cycle = romans.index(parts[0])+1 if parts[0] in romans else None
        label = parts[1] if len(parts)==2 else ''
    else:
        cycle = activity['ciclo_curricular']
        label = i.sections[activity['seccion_id']]['etiqueta']
    return cycle == block['ciclo'] and label.strip().upper() in block['secciones_horario']


def inspect(i, scenario='B'):
    from .domains import violations
    blocks = [b for b in i.raw['bloqueos'] if b['alcance']=='COHORTE']
    rows=[]
    for b in blocks:
        activities = [a for a in i.activities.values() if targets(i,b,a)]
        ids = {a['id'] for a in activities}
        rejects = [c.id for c in i.placements if c.actividad_id in ids and
                   any(e['regla']=='H21' and e['detalle']==b['id'] for e in violations(i,c,scenario))]
        fixed = [e['m0_id'] for e in i.raw.get('repeticiones_fijas_m0',[]) if
                 set(e['actividad_ids']) & ids and e['dia']==b['dia'] and e['inicio']<b['fin'] and b['inicio']<e['fin']]
        rows.append({'bloqueo':b,'actividades_afectadas':sorted(ids),
            'seccion_ids':sorted({a['seccion_id'] for a in activities}),
            'candidatas_rechazadas':rejects,'sesiones_fijas_incompatibles':fixed})
    return {'procedencia':i.raw.get('bloqueos_institucionales'),'bloqueos_academicos':rows}
