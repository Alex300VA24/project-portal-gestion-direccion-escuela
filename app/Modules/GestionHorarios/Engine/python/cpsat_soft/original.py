"""Penalizaciones exactas, equivalencias en ambos sentidos, sin relajar HARD."""
from collections import defaultdict
from dataclasses import asdict


def events(i,built):
    for j,v in built.placements.items():
        c=i.placements[j];a=i.activities[c.actividad_id]
        yield {**asdict(c),'aid':a['id'],'estudiantes':a['estudiantes']},v
    for e in i.raw.get('repeticiones_fijas_m0',[]):
        yield {**e,'aid':e['actividad_ids'][0],
               'estudiantes':i.sections[e['seccion_id']]['estudiantes']},built.fixed_events[e['id']]


def add(i,built,spec):
    m=built.model;bounds=spec['cotas'];terms=defaultdict(list)
    occupied=defaultdict(list);sessions=defaultdict(list);durations=defaultdict(list);loads=defaultdict(list)
    groups=spec['grupo_por_actividad'];counted=set(spec['grupos_jornada'])
    for e,v in events(i,built):
        aid=e['aid'];day=e['dia'];duration=e['fin']-e['inicio'];group=groups[aid]
        pref=spec['preferencia_por_actividad'][aid]
        if pref:
            outside=sum(1 for h in range(e['inicio'],e['fin']) if (h<13)!=(pref=='MANANA'))
            terms['S01'].append(outside*v)
        if group in counted:
            sessions[(group,day)].append(v)
            durations[(group,day)].append(duration*v)
            for h in range(e['inicio'],e['fin']): occupied[(group,day,h)].append(v)
        if e['tipo']=='LAB':
            # Nunca prohibir horarios HARD: validar cobertura del conjunto de días
            # antes de construir y abortar si la especificación es insuficiente.
            if day in spec['dias_laboratorio']: loads[day].append(duration*v)
            elif e['id'] not in {r['id'] for r in built.rejected}:
                raise ValueError('Días LAB insuficientes: existe una sesión HARD admisible fuera del conjunto')
        rid=e['ambiente_id']
        if rid and i.rooms[rid]['tipo']=='AULA':
            terms['S06'].append((i.rooms[rid]['capacidad']-e['estudiantes'])*duration*v)
    def boolean_or(vs,name):
        b=m.new_bool_var(name)
        if vs: m.add_max_equality(b,vs)
        else: m.add(b==0)
        return b
    def conjunction(vs,name):
        b=m.new_bool_var(name)
        m.add_bool_and(vs).only_enforce_if(b)
        m.add_bool_or([v.Not() for v in vs]).only_enforce_if(b.Not())
        return b
    hours=spec['horas']
    for g in spec['grupos_jornada']:
        for day in spec['dias']:
            name=f'{g}_{day}'
            occ=[boolean_or(occupied[(g,day,h)],f'oc_{name}_{h}') for h in hours]
            morning=boolean_or([v for h,v in zip(hours,occ) if h<13],'am_'+name)
            afternoon=boolean_or([v for h,v in zip(hours,occ) if h>=14],'pm_'+name)
            terms['S02'].append(conjunction([morning,afternoon],'mixta_'+name))
            for j,h in enumerate(hours):
                before=boolean_or(occ[:j],f'antes_{name}_{h}')
                after=boolean_or(occ[j+1:],f'despues_{name}_{h}')
                terms['S03'].append(conjunction([before,after,occ[j].Not()],f'hueco_{name}_{h}'))
            # Una sesión elegida, no una candidata ni un curso completo.
            one=m.new_bool_var('una_'+name);n=sum(sessions[(g,day)])
            m.add(n==1).only_enforce_if(one);m.add(n!=1).only_enforce_if(one.Not())
            short=m.new_bool_var('corta_'+name);d=sum(durations[(g,day)])
            m.add(d<=2).only_enforce_if(short);m.add(d>=3).only_enforce_if(short.Not())
            terms['S04'] += [one,conjunction([one,short],'aislada_corta_'+name)]
    daily=[]
    for day in spec['dias_laboratorio']:
        u=m.new_int_var(0,bounds['S05'],'lab_'+day);m.add(u==sum(loads[day]));daily.append(u)
    high=m.new_int_var(0,bounds['S05'],'lab_max');low=m.new_int_var(0,bounds['S05'],'lab_min')
    m.add_max_equality(high,daily);m.add_min_equality(low,daily)
    terms['S05']=[high-low]
    penalties={}
    for k,bound in bounds.items():
        penalties[k]=m.new_int_var(0,bound,'penalizacion_'+k)
        m.add(penalties[k]==sum(terms[k]))
    coeff=spec['normalizacion']['coeficientes']
    m.minimize(sum(coeff[k]*v for k,v in penalties.items()))
    error=m.validate()
    if error: raise ValueError(error)
    return penalties
