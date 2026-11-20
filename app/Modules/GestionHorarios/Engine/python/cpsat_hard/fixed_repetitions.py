"""Sesiones físicas M0 inmutables; sin redistribuirlas en patrones regulares."""
import copy
from collections import defaultdict
from .data import InputError,from_records
from .exceptions import target


def prepare(i):
    """Añade un overlay trazable en memoria. No modifica ninguna fuente."""
    if i.raw.get('politica_repeticion_fija'):
        return i
    raw=copy.deepcopy(i.raw)
    reps=[a for a in i.activities.values() if a['es_repeticion']]
    if not reps: return i
    if 'horario_m0' not in raw:
        raise InputError('Repeticiones fijas requieren sesiones M0 identificadas; no inferir de ventanas')
    groups=defaultdict(list)
    for a in reps: groups[a['curso_id']].append(a)
    names={r['nombre']:r['id'] for r in i.rooms.values()}
    events=[];excluded=[];issues=[]
    for course,aa in groups.items():
        sections={a['seccion_id'] for a in aa}
        if len(sections)!=1:
            raise InputError(f'Repetición {course}: falta correspondencia explícita M0/sección')
        teachers={a['docente_id'] for a in aa}
        rows=[r for r in raw['horario_m0'] if r['curso_id']==course]
        for row in rows:
            if row['docente_id'] not in teachers:
                excluded.append({'m0_id':row['id'],'motivo':'Docente ausente o ajeno a la repetición identificada; no se fusionan ofertas distintas'})
                continue
            # Se exige la identificación histórica del grupo asistente, no el
            # ciclo curricular del curso (Introducción I cursada por III-R).
            if any(a.get('grupo_repeticion')!=row['ciclo_origen']+'-'+row['seccion_origen'] for a in aa):
                excluded.append({'m0_id':row['id'],'motivo':'Oferta/grupo asistente distinto'})
                continue
            if row['ambiente_nombre'] not in names:
                raise InputError(f"{row['id']}: ambiente M0 no identificado dentro de los recursos; no sustituirlo")
            if row['fin']<=row['inicio'] or row['fin']-row['inicio']!=row['duracion']:
                raise InputError(f"{row['id']}: intervalo histórico inválido")
            event={'id':'FIJO_'+row['id'],'m0_id':row['id'],'curso_id':course,
                   'seccion_id':next(iter(sections)),'docente_id':row['docente_id'],
                   'ambiente_id':names[row['ambiente_nombre']],'ambiente':row['ambiente_nombre'],
                   'dia':row['dia'],'inicio':row['inicio'],'fin':row['fin'],'duracion_h':row['duracion'],
                   'tipo':row['tipo'],'actividad_ids':[a['id'] for a in aa],
                   'fuente':row['fuente'],'banderas_fuente':row['banderas'],
                   'datos_faltantes_fuente':row['datos_faltantes'],'fijo':True}
            if row['tipo'] not in ('T','P','LAB','T/P_MIXTA_FUENTE'):
                raise InputError(f"{row['id']}: tipo pendiente no permite comprobar cobertura curricular")
            events.append(event)
            if row['datos_faltantes']:
                issues.append({'m0_id':row['id'],'detalle':row['datos_faltantes'],'tratamiento':'Se conserva literalmente M0; discrepancia sin conciliar'})
        if not any(e['curso_id']==course for e in events):
            raise InputError(f'Repetición {course}: no hay sesiones M0 inequívocamente identificadas')
    raw['repeticiones_fijas_m0']=events
    raw['politica_repeticion_fija']={'version':4,'fuente':'NUEVA_POLITICA_USUARIO_Y_RESPUESTAS_EXPRESAS',
        'dias_horas_docentes_ambientes_tipos':'CONSERVAR_M0','conflictos_estudiantes':'SOLO_MATRICULA_O_PARES_CONFIRMADOS',
        'distribucion_uniforme':False,'prohibido_ampliar_o_redistribuir':True}
    raw['auditoria_repeticiones_fijas']={'registros_excluidos':excluded,'incidencias_fuente':issues,
        'nota':'Overlay de sesiones físicas, no reemplaza el currículo ni modifica fuentes originales'}
    return from_records(raw,i.placements,i.hashes)


def analyze(i):
    """Contrasta fuentes físicas con currículo y conflictos inamovibles."""
    events=i.raw.get('repeticiones_fijas_m0',[])
    problems=[]
    for n,a in enumerate(events):
        for b in events[n+1:]:
            if a['dia']!=b['dia'] or a['fin']<=b['inicio'] or b['fin']<=a['inicio']: continue
            for rule,key in (('H02','docente_id'),('H03','ambiente_id')):
                if a[key]==b[key]:
                    problems.append({'regla':rule,'sesiones':[a['m0_id'],b['m0_id']],
                        'cursos':[a['curso_id'],b['curso_id']],'recurso':a[key],
                        'dia':a['dia'],'inicio':max(a['inicio'],b['inicio']),'fin':min(a['fin'],b['fin']),
                        'detalle':'Conflicto entre sesiones fijas; el motor no puede moverlas'})
    for course in sorted({e['curso_id'] for e in events}):
        rows=[e for e in events if e['curso_id']==course]
        curriculum=i.courses[course]
        typed={typ:sum(e['duracion_h'] for e in rows if e['tipo']==typ) for typ in ('T','P','LAB')}
        mixed=sum(e['duracion_h'] for e in rows if e['tipo']=='T/P_MIXTA_FUENTE')
        target=dict(zip(('T','P','LAB'),(curriculum['ht'],curriculum['hp'],curriculum['hl'])))
        deficits={k:target[k]-typed[k] for k in target}
        consistent=(deficits['LAB']==0 and deficits['T']>=0 and deficits['P']>=0
                    and deficits['T']+deficits['P']==mixed)
        if not consistent:
            problems.append({'regla':'H09','curso_id':course,'sesiones':[e['m0_id'] for e in rows],
                'horas_M0_por_tipo':typed,'horas_mixtas':mixed,'horas_curriculares':target,
                'detalle':'M0 y currículo no coinciden por componente; se preservan ambos y se declara incompatibilidad'})
    for problem in problems:
        policy=i.raw.get('excepciones_periodo')
        problem['exceptuada_por_politica']=bool(policy and (
            problem['regla']=='H09' and problem.get('curso_id')==policy['H09']['curso_id'] or
            problem['regla']=='H03' and set(problem.get('sesiones',[]))==set(policy['H03']['sesiones'])))
    from .institutional_blocks import inspect
    for row in inspect(i)['bloqueos_academicos']:
        if row['sesiones_fijas_incompatibles']:
            problems.append({'regla':'H21','bloqueo':row['bloqueo'],
                'sesiones':row['sesiones_fijas_incompatibles'],'exceptuada_por_politica':False,
                'detalle':'Repeticiones inamovibles incompatibles con bloqueo institucional; sin excepción automática'})
    return {'politica':i.raw.get('politica_repeticion_fija'), 'excepciones_periodo':i.raw.get('excepciones_periodo'), 'sesiones_fijas':events,
            'incidencias':problems,'traza_fuentes':i.raw.get('auditoria_repeticiones_fijas',{}),
            'propuestas_anteriores_de_disponibilidad':'HISTORICAS_NO_APLICABLES_A_LA_NUEVA_POLITICA'}


def add_constraints(i,model,cons):
    events=i.raw.get('repeticiones_fijas_m0',[])
    fixed={e['id']:model.new_bool_var('m0_'+e['m0_id']) for e in events}
    mixed={}
    for e in events:
        v=fixed[e['id']]
        cons.add(v==1,'H01',f"SESION_FIJA_M0 {e['m0_id']}")
        cal=i.raw['calendario']
        cons.add(cal['hora_inicial']<=e['inicio']<e['fin']<=cal['hora_final'] and e['dia'] in cal['dias'],'H20',e['m0_id'])
        cons.add(not(e['inicio']<cal['receso']['fin'] and e['fin']>cal['receso']['inicio']),'H19',e['m0_id'])
        section=i.sections[e['seccion_id']]
        room=i.rooms[e['ambiente_id']]
        cons.add(room['capacidad']>=section['estudiantes'],'H12',e['m0_id'])
        if e['tipo'] in ('T','P','LAB','T/P_MIXTA_FUENTE'):
            expected='LAB' if e['tipo']=='LAB' else 'AULA'
            cons.add(room['tipo']==expected,'H11',e['m0_id'])
        for b in i.raw['bloqueos']:
            if b['dia']!=e['dia'] or b['fin']<=e['inicio'] or e['fin']<=b['inicio']: continue
            scope=b['alcance']; blocked=scope in ('GLOBAL','GLOBAL_CONDICIONAL')
            if scope=='AMBIENTE': blocked=b['ambiente'] in (room['id'],room['nombre'])
            if scope=='DOCENTE': blocked=b['docente_id']==e['docente_id']
            if scope=='SECCION': blocked=b['seccion_id']==e['seccion_id']
            if scope=='COHORTE':
                from .institutional_blocks import targets
                blocked=any(targets(i,b,i.activities[aid]) for aid in e['actividad_ids'])
            if blocked: cons.add(v==0,'H22' if scope=='AMBIENTE' else 'H21',f"{e['m0_id']} {b['id']}")
        if e['tipo']=='T/P_MIXTA_FUENTE':
            # Solo contabilidad curricular del bloque mixto completo. No se
            # inventa el orden temporal T/P ni se parte la sesión física.
            for typ in ('T','P'):
                mixed[(e['id'],typ)]=model.new_int_var(0,e['duracion_h'],e['id']+'_'+typ)
            cons.add(mixed[(e['id'],'T')]+mixed[(e['id'],'P')]==e['duracion_h'],'H09',e['m0_id']+' mixto')
    for course in {e['curso_id'] for e in events}:
        rows=[e for e in events if e['curso_id']==course]
        for typ,key in (('T','ht'),('P','hp'),('LAB','hl')):
            terms=[e['duracion_h']*fixed[e['id']] for e in rows if e['tipo']==typ]
            terms += [mixed[(e['id'],typ)] for e in rows if (e['id'],typ) in mixed]
            cons.add(sum(terms)==target(i,course,typ,i.courses[course][key]),'H09',f'CURRICULO_M0_FIJO {course} {typ}')
    return fixed,mixed


def real_student_conflicts(i,cons,x,fixed):
    """Comparar personas/pares explícitos, sin suponer matrícula común III-R."""
    pairs={frozenset(p) for p in i.raw.get('conflictos_estudiantes',[])}
    events=i.raw.get('repeticiones_fijas_m0',[])
    def students(ids):
        return {student for aid in ids for student in i.activities[aid].get('estudiantes_ids',[])}
    def shared(left,right):
        return bool(students(left)&students(right)) or any(frozenset((a,b)) in pairs for a in left for b in right if a!=b)
    slots=defaultdict(list)
    for j,c in enumerate(i.placements):
        if j not in x: continue
        for h in range(c.inicio,c.fin): slots[(c.actividad_id,c.dia,h)].append(x[j])
    for n,e in enumerate(events):
        for a in i.activities.values():
            if a['es_repeticion'] or not shared(e['actividad_ids'],[a['id']]): continue
            for h in range(e['inicio'],e['fin']):
                vs=slots[(a['id'],e['dia'],h)]
                if vs: cons.add(fixed[e['id']]+sum(vs)<=1,'H04',f"MATRICULA_FIJA {e['m0_id']} / {a['id']} {h}")
        for other in events[n+1:]:
            if e['dia']==other['dia'] and e['inicio']<other['fin'] and other['inicio']<e['fin'] and shared(e['actividad_ids'],other['actividad_ids']):
                cons.add(fixed[e['id']]+fixed[other['id']]<=1,'H04',f"MATRICULA_FIJA {e['m0_id']} / {other['m0_id']}")
