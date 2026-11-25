"""Indicadores SOFT comunes/docentes; no añade restricciones académicas."""
from collections import defaultdict
from .compact import Booleans
from .quality import section_scopes, usable_hours
from cpsat_hard.calendar_policy import planning_days


def add(entry,i,built):
    model=built.model;logic=Booleans(model);scopes,alternative=section_scopes(entry)
    hours=usable_hours(entry);slots=defaultdict(list);lab_slots=defaultdict(list)
    original={a['id']:a for a in entry['actividades']}
    preferences={str(d['docente_id']):d.get('preferencia','SIN_PREFERENCIA') for d in entry['disponibilidades']}
    pref=[];teacher_preferences=defaultdict(list);blocked={r['id'] for r in built.rejected}
    for j,c in enumerate(i.placements):
        if j not in built.placements or c.id in blocked:continue
        a=original[c.actividad_id];v=built.placements[j]
        entities=[('DOCENTE',str(a['docente_id']))]
        if a['id'] not in alternative:entities += [('SECCION',*scope) for scope in scopes[a['id']]]
        else:
            for scope in scopes[a['id']]:
                for h in range(c.inicio,c.fin):lab_slots[('SECCION',*scope),c.dia,h].append(v)
        for entity in entities:
            for h in range(c.inicio,c.fin):slots[entity,c.dia,h].append(v)
        preference=preferences.get(str(a['docente_id']))
        if preference in ('MANANA','TARDE'):
            cost=sum((h>=14 if preference=='MANANA' else h<13) for h in range(c.inicio,c.fin))*v
            pref.append(cost);teacher_preferences[('DOCENTE',str(a['docente_id']))].append(cost)
    groups=sorted({key[0] for key in slots});gaps=defaultdict(list);shorts=defaultdict(list);loads=defaultdict(list);busy_days=defaultdict(list);no_lab=[]
    for n,entity in enumerate(groups):
        for day in planning_days(i):
            occ=[logic.disjunction(slots[entity,day,h],f'q_oc_{n}_{day}_{h}') for h in hours]
            busy=logic.disjunction(occ,f'q_dia_{n}_{day}')
            busy_days[entity].append(busy)
            previous=logic.zero
            following=[logic.zero]*(len(hours)+1)
            for h in reversed(range(len(hours))):following[h]=logic.disjunction([occ[h],following[h+1]],f'q_fin_{n}_{day}_{h}')
            for h,current in enumerate(occ):
                gap=logic.conjunction([previous,following[h+1],current.Not()],f'q_hueco_{n}_{day}_{h}')
                gaps[entity].append(gap)
                if entity[0]=='SECCION':
                    lab=logic.disjunction(lab_slots[entity,day,hours[h]],f'q_oferta_lab_{n}_{day}_{h}')
                    no_lab.append(logic.conjunction([gap,lab.Not()],f'q_libre_sin_lab_{n}_{day}_{h}'))
                previous=logic.disjunction([previous,current],f'q_inicio_{n}_{day}_{h}')
            short=model.new_bool_var(f'q_corta_{n}_{day}')
            model.add(sum(occ)<=2).only_enforce_if(short)
            model.add(sum(occ)>=3).only_enforce_if(short.Not())
            shorts[entity].append(logic.conjunction([busy,short],f'q_aislada_{n}_{day}'))
            loads[entity].append(sum(occ))
    maximum={}
    for n,(entity,daily) in enumerate(loads.items()):
        if entity[0]=='DOCENTE':
            maximum[entity]=model.new_int_var(0,len(hours),f'q_max_carga_{n}')
            model.add_max_equality(maximum[entity],daily)
    metrics={'huecos_comunes':sum(v for e,vs in gaps.items() if e[0]=='SECCION' for v in vs),
        'huecos_docentes':sum(v for e,vs in gaps.items() if e[0]=='DOCENTE' for v in vs),
        'jornadas_cortas_seccion':sum(v for e,vs in shorts.items() if e[0]=='SECCION' for v in vs),
        'jornadas_cortas_docentes':sum(v for e,vs in shorts.items() if e[0]=='DOCENTE' for v in vs),
        'horas_fuera_preferencia_docente':sum(pref),'carga_maxima_docente_sumada':sum(maximum.values()),'huecos_sin_oferta_lab':sum(no_lab)}
    return metrics,{'huecos':{e:sum(vs) for e,vs in gaps.items()},'cortas':{e:sum(vs) for e,vs in shorts.items()},'carga_maxima':maximum,
        'dias':{e:sum(vs) for e,vs in busy_days.items()},'preferencias':{e:sum(vs) for e,vs in teacher_preferences.items()}}
