"""Lectura, normalización de tipos y verificación de referencias de entrada."""
from dataclasses import dataclass
import csv
import hashlib
import json
from pathlib import Path


class InputError(ValueError):
    pass


def poblacion_asesoria_no_declarada(registro):
    return registro.get('tipo_grupo') == 'TESIS' and registro.get('poblacion_no_declarada') is True and registro.get('estudiantes') == 0


def integer(value, label):
    if isinstance(value, bool) or str(value).strip() != str(int(value)):
        raise InputError(f'{label}: se requiere un entero, recibido {value!r}')
    return int(value)


@dataclass(frozen=True)
class Placement:
    id: str
    session_id: str
    patron_id: str
    actividad_id: str
    docente_id: str
    seccion_id: str
    tipo: str
    dia: str
    inicio: int
    fin: int
    duracion_h: int
    ambiente_id: str
    ciclo: int

    @classmethod
    def parse(cls, row):
        values = {k: row[k] for k in cls.__dataclass_fields__}
        for k in ('inicio', 'fin', 'duracion_h', 'ciclo'):
            values[k] = integer(values[k], f'{row.get("id")}.{k}')
        return cls(**values)


@dataclass
class Instance:
    raw: dict
    placements: list[Placement]
    hashes: dict
    activities: dict
    patterns: dict
    sessions: dict
    sections: dict
    courses: dict
    teachers: dict
    rooms: dict


def unique(rows, label):
    result = {}
    for row in rows:
        ident = row['id']
        if ident in result:
            raise InputError(f'{label}: identificador duplicado {ident}')
        result[ident] = row
    return result


def from_records(raw, placements, hashes=None):
    """También permite construir microinstancias en pruebas, sin archivos."""
    try:
        names = ('actividades', 'patrones', 'sesiones_alternativas', 'secciones',
                 'cursos', 'docentes', 'ambientes')
        maps = [unique(raw[k], k) for k in names]
        obj = Instance(raw, [p if isinstance(p, Placement) else Placement.parse(p)
                             for p in placements], hashes or {}, *maps)
        unique([{'id': p.id} for p in obj.placements], 'colocaciones')
        validate_references(obj)
        if 'horario_m0' in raw and not raw.get('politica_repeticion_fija') and any(a['es_repeticion'] for a in obj.activities.values()):
            from .fixed_repetitions import prepare
            return prepare(obj)
        return obj
    except (KeyError, TypeError, ValueError, OverflowError) as exc:
        if isinstance(exc, InputError):
            raise
        raise InputError(f'Esquema o valor inválido: {exc}') from exc


def validate_references(i):
    if i.raw.get('politica_sabado_repeticion') not in (None, 'REPETICION_SABADO_DECLARADO_V1'):
        raise InputError('Política de sábado de repetición desconocida')
    exceptions = i.raw.get('excepciones_orden_actividades', [])
    if not isinstance(exceptions, list) or any(type(c) is not int or c not in i.courses for c in exceptions):
        raise InputError('Excepción de orden con curso inexistente o identificador inválido')
    operative = i.raw.get('politica_operativa')
    if operative is not None and (not isinstance(operative, dict) or operative.get('version') != 'HORARIOS_OPERATIVOS_V1' or
            type(operative.get('capacidad_subgrupo_autorizada')) is not int or operative['capacidad_subgrupo_autorizada'] < 1):
        raise InputError('Política operativa de subgrupos inválida')
    for r in i.rooms.values():
        if integer(r['capacidad'],r['id'])<=0 or r['tipo'] not in ('AULA','LAB'):
            raise InputError(f"{r['id']}: capacidad/tipo inválido")
    for c in i.courses.values():
        for key in ('demanda_experimental','n_secciones','ht','hp','hl'):
            if integer(c[key],f"{c['id']}.{key}")<0:
                raise InputError(f"{c['id']}.{key}: no puede ser negativo")
    for s in i.sections.values():
        if integer(s['estudiantes'],s['id'])<=0 and not poblacion_asesoria_no_declarada(s):
            raise InputError(f"{s['id']}: población no positiva")
        if s['curso_id'] not in i.courses:
            raise InputError(f"Sección {s['id']}: curso inexistente")
    for a in i.activities.values():
        aid = a['id']
        if (a['seccion_id'] not in i.sections or a['curso_id'] not in i.courses
                or a['docente_id'] not in i.teachers):
            raise InputError(f'{aid}: sección, curso o docente inexistente')
        if i.sections[a['seccion_id']]['curso_id'] != a['curso_id']:
            raise InputError(f'{aid}: sección de otro curso')
        if a['tipo'] not in ('T', 'P', 'LAB'):
            raise InputError(f'{aid}: tipo desconocido')
        for k in ('horas_semanales', 'estudiantes', 'ciclo_curricular', 'ciclo_politica_sabado'):
            if integer(a[k], f'{aid}.{k}') <= 0 and not (k == 'estudiantes' and poblacion_asesoria_no_declarada(a) and poblacion_asesoria_no_declarada(i.sections[a['seccion_id']]) and a.get('requiere_ambiente') is False):
                raise InputError(f'{aid}.{k}: debe ser positivo')
        if not isinstance(a['requiere_ambiente'], bool):
            raise InputError(f'{aid}: requiere_ambiente debe ser booleano')
        if len(a['ambientes_candidatos']) != len(set(a['ambientes_candidatos'])):
            raise InputError(f'{aid}: ambientes candidatos duplicados')
        if any(r not in i.rooms for r in a['ambientes_candidatos']):
            raise InputError(f'{aid}: ambiente candidato inexistente')
        if bool(a['ambientes_candidatos']) != a['requiere_ambiente']:
            raise InputError(f'{aid}: contradicción de recurso requerido')
        if 'sesion_fisica_id' in a:
            participantes = a.get('secciones_participantes')
            padrones = a.get('estudiantes_por_seccion')
            if (a['es_repeticion'] or a['tipo'] not in ('T', 'P') or not isinstance(participantes, list) or
                    len(participantes) < 2 or len(participantes) != len(set(participantes)) or
                    participantes[0] != a['seccion_id'] or not isinstance(padrones, dict) or
                    set(participantes) != set(padrones) or not all(sid in i.sections and
                    i.sections[sid]['curso_id'] == a['curso_id'] for sid in participantes)):
                raise InputError(f'{aid}: contrato de sesión física incompatible')
            if any(not isinstance(ids, list) or not ids or len(ids) != len(set(ids)) for ids in padrones.values()):
                raise InputError(f'{aid}: padrón participante inválido')
            union = set().union(*(set(ids) for ids in padrones.values()))
            if set(a.get('estudiantes_ids', [])) != union or len(union) != a['estudiantes']:
                raise InputError(f'{aid}: población física conjunta inválida')
        elif 'secciones_participantes' in a or 'estudiantes_por_seccion' in a:
            raise InputError(f'{aid}: falta identidad física')
        if 'estudiantes_ids' in a:
            ids = a['estudiantes_ids']
            if len(ids) != len(set(ids)) or len(ids) != a['estudiantes']:
                raise InputError(f'{aid}: padrón no coincide con número de estudiantes')
    for p in i.patterns.values():
        if p['actividad_id'] not in i.activities:
            raise InputError(f"{p['id']}: actividad inexistente")
    for s in i.sessions.values():
        if s['patron_id'] not in i.patterns or s['actividad_id'] not in i.activities:
            raise InputError(f"{s['id']}: referencias inexistentes")
        if i.patterns[s['patron_id']]['actividad_id'] != s['actividad_id']:
            raise InputError(f"{s['id']}: patrón de otra actividad")
        if integer(s['duracion'], s['id']) <= 0:
            raise InputError(f"{s['id']}: duración no positiva")
    signatures = set()
    for c in i.placements:
        if c.session_id not in i.sessions or c.actividad_id not in i.activities:
            raise InputError(f'{c.id}: sesión o actividad inexistente')
        s = i.sessions[c.session_id]
        a = i.activities[c.actividad_id]
        if (c.patron_id != s['patron_id'] or c.actividad_id != s['actividad_id']
                or c.docente_id != a['docente_id'] or c.seccion_id != a['seccion_id']
                or c.tipo != a['tipo'] or c.ciclo != a['ciclo_politica_sabado']):
            raise InputError(f'{c.id}: claves redundantes CSV/JSON inconsistentes (H07)')
        if c.fin <= c.inicio or c.fin-c.inicio != c.duracion_h or c.duracion_h != s['duracion']:
            raise InputError(f'{c.id}: duración inconsistente (H10)')
        if c.ambiente_id and c.ambiente_id not in i.rooms:
            raise InputError(f'{c.id}: ambiente inexistente')
        signature = (c.session_id, c.dia, c.inicio, c.fin, c.ambiente_id)
        if signature in signatures:
            raise InputError(f'{c.id}: colocación semánticamente duplicada')
        signatures.add(signature)
    for w in i.raw['ventanas_disponibilidad']:
        # El catálogo puede conservar ventanas de docentes sin actividad (p.ej. D001).
        if w['dia'] not in (['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'] if i.raw.get('politica_sabado_repeticion') == 'REPETICION_SABADO_DECLARADO_V1' else i.raw['calendario']['dias']) or w['fin'] <= w['inicio']:
            raise InputError(f"Ventana inválida: {w['id']}")
        if w['alcance'] not in ('GENERAL','GENERAL_EXPERIMENTAL') and not (w['alcance'].startswith('CURSO_') or '-' in w['alcance']):
            raise InputError(f"Alcance de ventana desconocido: {w['id']}")
    room_names = {r['nombre'] for r in i.rooms.values()}
    for b in i.raw['bloqueos']:
        if type(b['inicio']) is not int or type(b['fin']) is not int:
            raise InputError(f"Bloqueo requiere horas enteras: {b['id']}")
        if b['alcance'] not in ('AMBIENTE', 'GLOBAL', 'GLOBAL_CONDICIONAL', 'DOCENTE', 'SECCION', 'COHORTE', 'ACTIVIDAD', 'CURSO', 'GRUPO', 'CICLO'):
            raise InputError(f"Bloqueo de alcance no soportado: {b['id']}")
        if b['fin'] <= b['inicio'] or b['dia'] not in (['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'] if i.raw.get('politica_sabado_repeticion') == 'REPETICION_SABADO_DECLARADO_V1' else i.raw['calendario']['dias']):
            raise InputError(f"Bloqueo inválido: {b['id']}")
        if b['alcance'] == 'AMBIENTE' and b['ambiente'] not in room_names | set(i.rooms):
            raise InputError(f"Bloqueo de ambiente desconocido: {b['id']}")
        if b['alcance']=='DOCENTE' and b['docente_id'] not in i.teachers:
            raise InputError(f"Bloqueo de docente desconocido: {b['id']}")
        if b['alcance']=='SECCION' and b['seccion_id'] not in i.sections:
            raise InputError(f"Bloqueo de sección desconocida: {b['id']}")
        if b['alcance']=='ACTIVIDAD' and b.get('actividad_id') not in i.activities:
            raise InputError(f"Bloqueo de actividad desconocida: {b['id']}")
        if b['alcance']=='CURSO' and b.get('curso_id') not in i.courses:
            raise InputError(f"Bloqueo de curso desconocido: {b['id']}")
        if b['alcance']=='GRUPO' and b.get('grupo_id') not in {a.get('grupo_id') for a in i.activities.values() if a.get('grupo_id')}:
            raise InputError(f"Bloqueo de grupo desconocido: {b['id']}")
        if b['alcance']=='CICLO' and (type(b.get('ciclo')) is not int or not 1 <= b['ciclo'] <= 10):
            raise InputError(f"Bloqueo de ciclo inválido: {b['id']}")
        if b['alcance']=='COHORTE':
            labels=b.get('secciones_horario')
            if (type(b.get('ciclo')) is not int or not 1<=b['ciclo']<=10 or
                not isinstance(labels,list) or not labels or len(labels)!=len(set(labels)) or
                any(not isinstance(s,str) or not s or s!=s.strip().upper() for s in labels) or
                type(b.get('incluir_repeticiones',False)) is not bool or not b.get('fuente')):
                raise InputError(f"Bloqueo de cohorte inválido: {b['id']}")
    calendar = i.raw['calendario']
    if i.raw.get('parametros_solver',{}).get('precision_minutos',60) != 60:
        raise InputError('Este motor usa la malla horaria V1 de 60 minutos')
    if calendar['hora_inicial'] >= calendar['hora_final']:
        raise InputError('Jornada inválida')
    if any(type(v) is not bool for v in calendar['sabados_por_ciclo'].values()):
        raise InputError('Política de sábados debe usar booleanos')
    for pair in i.raw.get('conflictos_estudiantes', []):
        if len(pair) != 2 or pair[0] not in i.activities or pair[1] not in i.activities or pair[0] == pair[1]:
            raise InputError(f'Par de conflictos inválido: {pair}')
    coverage = i.raw.get('cobertura_conflictos', {})
    if coverage.get('completa') and not coverage.get('fuente'):
        raise InputError('La declaración de cobertura completa requiere fuente')


def load(instance_path, placements_path, conflicts_path=None, policy_path=None, institutional_blocks_path=None):
    sources = [Path(instance_path), Path(placements_path)]
    raw = json.loads(sources[0].read_text(encoding='utf-8'))
    if institutional_blocks_path:
        from .institutional_blocks import attach
        raw=attach(raw,institutional_blocks_path)
        sources.append(Path(institutional_blocks_path))
    with sources[1].open(encoding='utf-8-sig', newline='') as f:
        placements = list(csv.DictReader(f))
    if conflicts_path:
        path = Path(conflicts_path)
        extra = json.loads(path.read_text(encoding='utf-8'))
        for k in ('conflictos_estudiantes', 'cobertura_conflictos'):
            if k in raw:
                raise InputError(f'La instancia ya contiene {k}; no sobrescribir')
            raw[k] = extra[k]
        sources.append(path)
    instance=from_records(raw, placements, {str(p.resolve()): hashlib.sha256(p.read_bytes()).hexdigest() for p in sources})
    if any(a['es_repeticion'] for a in instance.activities.values()):
        from .fixed_repetitions import prepare
        instance=prepare(instance)
    if policy_path:
        from .exceptions import attach
        path=Path(policy_path)
        instance=attach(instance,json.loads(path.read_text(encoding='utf-8')))
        instance.hashes[str(path.resolve())]=hashlib.sha256(path.read_bytes()).hexdigest()
    return instance
