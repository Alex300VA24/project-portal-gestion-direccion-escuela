"""Excepciones explícitas del usuario, limitadas al escenario 2026-II."""
import copy
import hashlib
import json
from .data import InputError

# Compromiso de integridad; el contenido autorizado permanece en el sidecar privado.
AUTHORIZED_POLICY_SHA256 = '44f88346922bd94854e012909d2e4d80b7ffc4a425448f4fabb52f867854515b'


def attach(i, policy):
    # No aceptar ampliaciones implícitas ni configuraciones de otro periodo.
    encoded=json.dumps(policy,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()
    if hashlib.sha256(encoded).hexdigest()!=AUTHORIZED_POLICY_SHA256 or i.raw.get('metadata', {}).get('periodo')!=policy.get('periodo'):
        raise InputError('Excepción no autorizada o periodo distinto de la política')
    events={e['m0_id']: e for e in i.raw.get('repeticiones_fijas_m0', [])}
    h3=policy['H03']; h9=policy['H09']
    for sid in h3['sesiones']:
        e=events.get(sid, {})
        if any(e.get(k)!=h3[k] for k in ('ambiente_id','dia','inicio','fin')):
            raise InputError('La excepción de ambiente no coincide con las sesiones fuente')
    c=i.courses.get(h9['curso_id'], {})
    if dict(zip(('T','P','LAB'),(c.get('ht'),c.get('hp'),c.get('hl'))))!=h9['curriculo_original']:
        raise InputError('Currículo original distinto del autorizado')
    rows=[e for e in events.values() if e['curso_id']==h9['curso_id']]
    if sorted(e['m0_id'] for e in rows)!=sorted(h9['sesiones']) or any(e['tipo']!='T' for e in rows) or sum(e['duracion_h'] for e in rows)!=sum(h9['requerimiento_periodo'].values()):
        raise InputError('M0 del curso exceptuado distinto del autorizado')
    i.raw['excepciones_periodo']=copy.deepcopy(policy)
    return i


def target(i, course, typ, original):
    p=i.raw.get('excepciones_periodo')
    return p['H09']['requerimiento_periodo'][typ] if p and course==p['H09']['curso_id'] else original


def room_pair_allowed(i, a, b, day, hour, room):
    p=i.raw.get('excepciones_periodo')
    if not p: return False
    e=p['H03']
    return ({a,b}==set(e['sesiones']) and day==e['dia'] and room==e['ambiente_id']
            and e['inicio']<=hour<e['fin'])
