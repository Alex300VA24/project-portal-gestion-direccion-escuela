"""Indicadores de calidad, sin solver, matrícula ni alumnos inferidos."""
from collections import defaultdict
from itertools import combinations

VERSION = 'CALIDAD_ACADEMICA_V1'
DAYS = ('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO')


def section_scopes(entry):
    sections={s['id']:s for s in entry['secciones']}
    groups=defaultdict(set);opened=defaultdict(set);labels=defaultdict(set)
    for s in sections.values():
        if s.get('repeticion') or s.get('es_grupo_asesoria'): continue
        opened[s['ciclo'],str(s['curso_id'])].add(s['etiqueta'])
        labels[s['ciclo']].add(s['etiqueta'])
    for a in entry['actividades']:
        group=a.get('subgrupo') or a.get('grupo_id')
        if a['tipo']=='LAB' and not a['repeticion'] and a.get('tipo_grupo')!='TESIS' and group:
            groups[a['seccion_id']].add(group)
    alternative=set();scopes={}
    enabled=entry['configuracion'].get('politica_grupos_laboratorio')=='ALTERNATIVAS_SIN_ASIGNACION_V1'
    for a in entry['actividades']:
        s=sections[a['seccion_id']]
        if s.get('es_grupo_asesoria') or a.get('tipo_grupo')=='TESIS': scopes[a['id']]=[];continue
        if a['repeticion']: scopes[a['id']]=[(s['ciclo'],'Repetición '+str(s['etiqueta'])+' · '+str(s['curso_id']))];continue
        scopes[a['id']]=[(s['ciclo'],l) for l in sorted(labels[s['ciclo']]) if l==s['etiqueta'] or len(opened[s['ciclo'],str(s['curso_id'])])==1]
        if enabled and a['tipo']=='LAB' and (a.get('subgrupo') or a.get('grupo_id')) and len(groups[a['seccion_id']])>1: alternative.add(a['id'])
    return scopes,alternative


def usable_hours(entry):
    cal=entry['configuracion']['calendario']
    return [h for h in range(cal['hora_inicial'],cal['hora_final']) if not cal['receso']['inicio']<=h<cal['receso']['fin']]


def journeys(rows,hours,alternative_rows=()):
    by_day=defaultdict(list);labs=defaultdict(set)
    for r in rows: by_day[r['dia']].append(r)
    for r in alternative_rows: labs[r['dia']].update(range(r['inicio'],r['fin']))
    result=[]
    for day in sorted(by_day,key=DAYS.index):
        own=by_day[day];occupied={h for r in own for h in range(r['inicio'],r['fin'])}
        if not occupied:continue
        gaps={h for h in hours if min(occupied)<h<max(occupied) and h not in occupied}
        result.append({'dia':day,'inicio':min(occupied),'fin':max(occupied)+1,'horas':len(occupied),
            'sesiones':len(own),'huecos':len(gaps),'huecos_con_oferta_lab':len(gaps & labs[day]),
            'huecos_sin_oferta_lab':len(gaps-labs[day]),'jornada_corta':len(occupied)<=2,
            'intervalos_libres':[{'inicio':h,'fin':h+1,'oferta_lab':h in labs[day]} for h in sorted(gaps)]})
    return {'dias':len(result),'huecos':sum(d['huecos'] for d in result),
        'huecos_sin_oferta_lab':sum(d['huecos_sin_oferta_lab'] for d in result),
        'jornadas_cortas':sum(d['jornada_corta'] for d in result),'horas':sum(d['horas'] for d in result),
        'carga_maxima_dia':max((d['horas'] for d in result),default=0),'jornadas':result}


def measure(entry,sessions=None):
    rows=sessions if sessions is not None else entry['sesiones']
    acts={a['id']:a for a in entry['actividades']};secs={s['id']:s for s in entry['secciones']}
    scopes,alternative=section_scopes(entry);hours=usable_hours(entry)
    common=defaultdict(list);labs=defaultdict(list);teachers=defaultdict(list);rooms=defaultdict(list)
    lab_groups=defaultdict(list)
    for r in rows:
        a=acts[r['actividad_id']]
        teachers[str(a['docente_id'])].append(r)
        if r.get('ambiente_id'):rooms[r['ambiente_id']].append(r)
        for scope in scopes[a['id']]: (labs if a['id'] in alternative else common)[scope].append(r)
        if a['id'] in alternative:lab_groups[a['seccion_id'],a.get('subgrupo') or a.get('grupo_id')].append(r)
    sections=[]
    for scope in sorted(common.keys()|labs.keys()):
        sections.append({'ciclo':scope[0],'seccion':scope[1],**journeys(common[scope],hours,labs[scope]),
            'oferta_lab':{'sesiones':len(labs[scope]),'horas_programadas':sum(r['fin']-r['inicio'] for r in labs[scope]),
                'dias':sorted({r['dia'] for r in labs[scope]},key=DAYS.index)}})
    names={str(d['id']):d['nombre'] for d in entry.get('docentes',[])}
    preferences={str(d['docente_id']):d.get('preferencia','SIN_PREFERENCIA') for d in entry.get('disponibilidades',[])}
    teacher_rows=[]
    for tid,own in sorted(teachers.items()):
        preference=preferences.get(tid,'SIN_PREFERENCIA');outside=0
        if preference in ('MANANA','TARDE'):
            outside=sum(sum((h>=14 if preference=='MANANA' else h<13) for h in range(r['inicio'],r['fin'])) for r in own)
        transitions=0
        for day in DAYS:
            ordered=sorted((r for r in own if r['dia']==day),key=lambda r:(r['inicio'],r['fin']))
            transitions+=sum(bool(a.get('ambiente_id') and b.get('ambiente_id') and a['ambiente_id']!=b['ambiente_id']) for a,b in zip(ordered,ordered[1:]))
        j=journeys(own,hours)
        teacher_rows.append({'docente_id':tid,'docente':names.get(tid,tid),**j,'preferencia':preference,
            'horas_fuera_preferencia':outside,'horas_con_preferencia':j['horas'] if preference in ('MANANA','TARDE') else 0,
            'cambios_ambiente':transitions})
    room_data={r['id']:r for r in entry.get('ambientes',[])};room_rows=[]
    for rid,room in sorted(room_data.items()):
        if not room.get('activo',True) and rid not in rooms:continue
        own=rooms[rid];available={(d,h) for d in entry['configuracion']['calendario']['dias'] for h in hours}
        for b in entry.get('bloqueos',[]):
            if b.get('activo',True) and (b['alcance']=='GLOBAL' or b['alcance']=='AMBIENTE' and b['datos'].get('ambiente_id')==rid):
                x=b['datos'];available-={(x['dia'],h) for h in range(x['inicio'],x['fin'])}
        occupied={(r['dia'],h) for r in own for h in range(r['inicio'],r['fin'])}
        capacity_hours=sum(room['capacidad']*(r['fin']-r['inicio']) for r in own)
        population_hours=sum((acts[r['actividad_id']].get('poblacion_estimada') if acts[r['actividad_id']].get('poblacion_estimada') is not None else len(acts[r['actividad_id']].get('estudiantes',[])))*(r['fin']-r['inicio']) for r in own)
        room_rows.append({'ambiente_id':rid,'ambiente':room.get('nombre',rid),'tipo':room['tipo'],
            'horas_programadas':len(occupied),'horas_habilitadas':len(available),
            'horas_fuera_calendario_regular':sum(d not in entry['configuracion']['calendario']['dias'] for d,h in occupied),
            'ocupacion_programada_porcentaje':100*len(occupied & available)/len(available) if available else None,
            'plazas_hora_programadas':capacity_hours,'poblacion_hora_prevista':population_hours})
    # Cruces A/B adicionales: ocupaciones unidas por curso/letra, igual escala V2.
    opens=defaultdict(set);occupancy=defaultdict(set)
    for s in secs.values():
        if not s.get('repeticion') and not s.get('es_grupo_asesoria'):opens[s['ciclo'],str(s['curso_id'])].add(s['etiqueta'])
    for r in rows:
        a=acts[r['actividad_id']];s=secs[a['seccion_id']]
        if a['repeticion'] or a.get('tipo_grupo')=='TESIS':continue
        occupancy[s['ciclo'],str(s['curso_id']),s['etiqueta']].update((r['dia'],h) for h in range(r['inicio'],r['fin']))
    crossings=[]
    for l,r in combinations(sorted(occupancy),2):
        if l[0]!=r[0] or l[1]==r[1] or l[2]==r[2] or len(opens[l[:2]])==1 or len(opens[r[:2]])==1:continue
        n=len(occupancy[l]&occupancy[r])
        if n:crossings.append({'ciclo':l[0],'curso_1':l[1],'seccion_1':l[2],'curso_2':r[1],'seccion_2':r[2],'horas':n})
    lab_details=[]
    for (sl,gl),(sr,gr) in combinations(sorted(lab_groups),2):
        l,r=secs[sl],secs[sr]
        if l['ciclo']!=r['ciclo'] or l['curso_id']==r['curso_id'] or not(l['etiqueta']==r['etiqueta'] or len(opens[l['ciclo'],str(l['curso_id'])])==1 or len(opens[r['ciclo'],str(r['curso_id'])])==1):continue
        left={(x['dia'],h) for x in lab_groups[sl,gl] for h in range(x['inicio'],x['fin'])}
        right={(x['dia'],h) for x in lab_groups[sr,gr] for h in range(x['inicio'],x['fin'])}
        lab_details.append({'ciclo':l['ciclo'],'compatible':not bool(left & right)})
    totals={'huecos_comunes':sum(s['huecos'] for s in sections),'huecos_sin_oferta_lab':sum(s['huecos_sin_oferta_lab'] for s in sections),
        'jornadas_cortas_seccion':sum(s['jornadas_cortas'] for s in sections),'dias_seccion':sum(s['dias'] for s in sections),
        'huecos_docentes':sum(t['huecos'] for t in teacher_rows),'jornadas_cortas_docentes':sum(t['jornadas_cortas'] for t in teacher_rows),
        'dias_docentes':sum(t['dias'] for t in teacher_rows),'horas_fuera_preferencia_docente':sum(t['horas_fuera_preferencia'] for t in teacher_rows),
        'horas_con_preferencia_docente':sum(t['horas_con_preferencia'] for t in teacher_rows),'cambios_ambiente_docente':sum(t['cambios_ambiente'] for t in teacher_rows),
        'carga_maxima_docente_sumada':sum(t['carga_maxima_dia'] for t in teacher_rows),
        'cruces_secciones':sum(c['horas'] for c in crossings),'pares_lab_compatibles':sum(p['compatible'] for p in lab_details),
        'pares_lab_incompatibles':sum(not p['compatible'] for p in lab_details)}
    cycles=[]
    for cycle in sorted({s['ciclo'] for s in sections}):
        ss=[s for s in sections if s['ciclo']==cycle]
        cycles.append({'ciclo':cycle,'huecos_comunes':sum(s['huecos'] for s in ss),'huecos_sin_oferta_lab':sum(s['huecos_sin_oferta_lab'] for s in ss),
            'jornadas_cortas':sum(s['jornadas_cortas'] for s in ss),'dias_seccion':sum(s['dias'] for s in ss),
            'cruces_secciones':sum(c['horas'] for c in crossings if c['ciclo']==cycle)})
    return {'version':VERSION,'resumen':totals,'ciclos':cycles,'secciones':sections,'docentes':teacher_rows,'ambientes':room_rows,
        'alcance':'Horas entre clases comunes, no itinerarios individuales. La oferta LAB no equivale a asistencia. Recesos excluidos; cursos compartidos cuentan en cada itinerario. Compatibilidad LAB por pares, sin asignar estudiantes ni vacantes. Cambios de ambiente no miden distancia.'}
