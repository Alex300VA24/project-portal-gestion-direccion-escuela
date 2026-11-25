"""Contraste independiente de sesiones físicas fijas y horario regular."""
import copy
from collections import Counter
from itertools import combinations
from .data import from_records


def validate_fixed(i,outcome,scenario):
    from .validation import validate
    aids={a['id'] for a in i.activities.values() if a['es_repeticion']}
    raw=copy.deepcopy(i.raw)
    raw.pop('excepciones_periodo',None)
    raw.pop('politica_repeticion_fija',None);raw.pop('repeticiones_fijas_m0',None)
    raw['actividades']=[a for a in raw['actividades'] if a['id'] not in aids]
    secs={a['seccion_id'] for a in raw['actividades']};courses={a['curso_id'] for a in raw['actividades']}
    raw['secciones']=[s for s in raw['secciones'] if s['id'] in secs]
    raw['cursos']=[c for c in raw['cursos'] if c['id'] in courses]
    raw['patrones']=[p for p in raw['patrones'] if p['actividad_id'] not in aids]
    raw['sesiones_alternativas']=[s for s in raw['sesiones_alternativas'] if s['actividad_id'] not in aids]
    raw['conflictos_estudiantes']=[p for p in raw.get('conflictos_estudiantes',[]) if not(set(p)&aids)]
    if not raw['actividades']:
        # La llamada normal admite conjuntos vacíos; no hay clases regulares.
        raw['cursos']=[];raw['secciones']=[]
    regular=from_records(raw,[c for c in i.placements if c.actividad_id not in aids],i.hashes)
    report=validate(regular,outcome,scenario)
    checks=report['reglas']
    policy=i.raw.get('excepciones_periodo')
    # Contraste independiente: solo el requerimiento de la oferta repetida
    # identificada puede diferir del currículo conservado en la fuente.
    applied=[]
    def fail(rule,detail):
        checks[rule]['estado']='FAIL';checks[rule]['violaciones'].append(detail)
    expected={}
    for source in i.raw['horario_m0']:
        aa=[a for a in i.activities.values() if a['es_repeticion'] and a['curso_id']==source['curso_id']]
        if aa and source['docente_id'] in {a['docente_id'] for a in aa} and all(a.get('grupo_repeticion')==source['ciclo_origen']+'-'+source['seccion_origen'] for a in aa):
            expected[source['id']]=source
    events=outcome.get('sesiones_fijas_repeticion',[])
    counts=Counter(e.get('m0_id') for e in events)
    for sid in expected:
        if counts[sid]!=1: fail('H01',f'{sid}: sesión fija omitida o duplicada')
    totals=Counter();typed=Counter();physical=[]
    for e in events:
        sid=e.get('m0_id');s=expected.get(sid)
        if not s:
            fail('H01',f'Sesión fija ajena a M0 {sid}');continue
        for key in ('curso_id','dia','inicio','fin','docente_id','tipo'):
            if e.get(key)!=s[key]: fail('H07' if key=='docente_id' else 'H10',f'{sid}: se alteró {key}')
        rid=e.get('ambiente_id')
        if rid not in i.rooms or i.rooms[rid]['nombre']!=s['ambiente_nombre']:
            fail('H03',f'{sid}: ambiente fijo alterado')
        if type(e.get('inicio')) is not int or type(e.get('fin')) is not int or e['fin']<=e['inicio']:
            fail('H10',f'{sid}: intervalo inválido');continue
        duration=e['fin']-e['inicio'];course=e['curso_id']
        totals[course]+=duration
        if e['tipo']=='T/P_MIXTA_FUENTE':
            composition=e.get('composicion_curricular_modelada',{})
            if set(composition)!={'T','P'} or any(type(v) is not int or v<0 for v in composition.values()) or sum(composition.values())!=duration:
                fail('H09',f'{sid}: composición mixta inválida')
            else:
                for typ,n in composition.items(): typed[(course,typ)]+=n
        else: typed[(course,e['tipo'])]+=duration
        cal=i.raw['calendario']
        if e['dia'] not in cal['dias'] or not(cal['hora_inicial']<=e['inicio']<e['fin']<=cal['hora_final']): fail('H20',sid)
        if e['inicio']<cal['receso']['fin'] and e['fin']>cal['receso']['inicio']: fail('H19',sid)
        aa=[a for a in i.activities.values() if a['es_repeticion'] and a['curso_id']==course]
        if rid in i.rooms:
            section=i.sections[aa[0]['seccion_id']]
            if i.rooms[rid]['capacidad']<section['estudiantes']: fail('H12',sid)
            if i.rooms[rid]['tipo']!=('LAB' if e['tipo']=='LAB' else 'AULA'): fail('H11',sid)
        for b in i.raw['bloqueos']:
            if b['dia']!=e['dia'] or b['inicio']>=e['fin'] or b['fin']<=e['inicio']: continue
            scope=b['alcance'];blocked=scope in ('GLOBAL','GLOBAL_CONDICIONAL')
            if scope=='AMBIENTE': blocked=rid in i.rooms and b['ambiente'] in (rid,i.rooms[rid]['nombre'])
            if scope=='DOCENTE': blocked=b['docente_id']==e['docente_id']
            if scope=='SECCION': blocked=b['seccion_id'] in {a['seccion_id'] for a in aa}
            if scope=='COHORTE' and b.get('incluir_repeticiones',False):
                numerals=['I','II','III','IV','V','VI','VII','VIII','IX','X']
                for a in aa:
                    parts=a.get('grupo_repeticion','').split('-',1)
                    cycle=numerals.index(parts[0])+1 if parts[0] in numerals else None
                    label=parts[1].strip().upper() if len(parts)==2 else ''
                    blocked |= cycle==b['ciclo'] and label in b['secciones_horario']
            if blocked: fail('H22' if scope=='AMBIENTE' else 'H21',f"{sid}: {b['id']}")
        physical.append((e,{a['id'] for a in aa}))
    for course in {a['curso_id'] for a in i.activities.values() if a['es_repeticion']}:
        for typ,key in (('T','ht'),('P','hp'),('LAB','hl')):
            expected_hours=i.courses[course][key]
            if policy and course==policy['H09']['curso_id']:
                expected_hours=policy['H09']['requerimiento_periodo'][typ]
            if typed[(course,typ)]!=expected_hours:
                fail('H09',f'{course} {typ}: M0={typed[(course,typ)]}, currículo={i.courses[course][key]}')
        if policy and course==policy['H09']['curso_id']:
            applied.append({'regla':'H09',**policy['H09']})
    physical += [(c,{c['actividad_id']}) for c in outcome.get('sesiones_elegidas',[]) if c.get('actividad_id') in i.activities]
    pairs={frozenset(p) for p in i.raw.get('conflictos_estudiantes',[])}
    def roster(ids): return {s for aid in ids for s in i.activities[aid].get('estudiantes_ids',[])}
    for (a,ai),(b,bi) in combinations(physical,2):
        # Los pares regular/regular ya fueron comprobados por el validador base.
        if not(ai&aids or bi&aids): continue
        if a['dia']!=b['dia'] or a['fin']<=b['inicio'] or b['fin']<=a['inicio']: continue
        label=f"{a.get('m0_id',a.get('id'))} / {b.get('m0_id',b.get('id'))}"
        if a['docente_id']==b['docente_id']: fail('H02',label)
        if a['ambiente_id'] and a['ambiente_id']==b['ambiente_id']:
            ex=policy['H03'] if policy else {}
            allowed=(set((a.get('m0_id'),b.get('m0_id')))==set(ex.get('sesiones',[]))
                     and a['ambiente_id']==ex.get('ambiente_id') and a['dia']==ex.get('dia')
                     and max(a['inicio'],b['inicio'])>=ex.get('inicio',0)
                     and min(a['fin'],b['fin'])<=ex.get('fin',0))
            if allowed: applied.append({'regla':'H03',**ex})
            else: fail('H03',label)
        if roster(ai)&roster(bi) or any(frozenset((x,y)) in pairs for x in ai for y in bi if x!=y): fail('H04',label)
    if any('estudiantes_ids' not in i.activities[aid] for aid in aids):
        lim={'regla':'H04','detalle':'Repeticiones: no se infiere matrícula común III-R; faltan padrones/pares completos para certificar todos los cruces reales.'}
        report['limitaciones'].append(lim)
        if checks['H04']['estado']=='PASS': checks['H04']['estado']='PARCIAL_DATOS'
    ok=not report['otras_violaciones'] and all(r['estado']!='FAIL' for r in checks.values())
    from .coverage import assess
    coverage=assess(i,checks)
    report['cobertura_institucional']=coverage
    report.update(estado='VALIDO_CON_EXCEPCIONES_DE_POLITICA' if ok and policy else 'VALIDO_EN_ALCANCE_EXPERIMENTAL' if ok and report['limitaciones'] else 'VALIDO_HARD' if ok else 'INVALIDO',
        excepciones_aplicadas=applied,excepciones_periodo=policy,cumple_HARD_sin_excepciones=ok and not policy,
        cumple_reglas_con_datos_disponibles=ok,certificacion_completa_H01_H22=ok and not report['limitaciones'] and not policy and coverage['acreditacion_institucional_completa'],
        n_actividades=report['n_actividades']+len(aids),n_sesiones=report['n_sesiones']+len(events),
        horas_programadas=report['horas_programadas']+sum(totals.values()),
        politica_repeticion_fija=i.raw['politica_repeticion_fija'],sesiones_fijas_comprobadas=len(events))
    return report
