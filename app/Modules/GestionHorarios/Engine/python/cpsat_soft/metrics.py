"""Recalcula S01–S06 a partir de intervalos exportados, sin OR-Tools."""
from collections import defaultdict
from fractions import Fraction


def evaluate(i,outcome,spec):
    groups=spec['grupo_por_actividad'];pref=spec['preferencia_por_actividad']
    rows=[]
    for e in outcome.get('sesiones_elegidas',[]):
        a=i.activities[e['actividad_id']]
        rows.append({**e,'aid':a['id'],'estudiantes':a['estudiantes']})
    for e in outcome.get('sesiones_fijas_repeticion',[]):
        rows.append({**e,'aid':e['actividad_ids'][0],
            'estudiantes':i.sections[e['seccion_id']]['estudiantes']})
    penalties={k:0 for k in spec['cotas']};journeys=defaultdict(list);thesis=defaultdict(list)
    labs={d:0 for d in spec['dias_laboratorio']};by_room=defaultdict(lambda:{d:0 for d in labs})
    room_hours=0;capacity_hours=0;preferred_hours=0;issues=[]
    for e in rows:
        aid=e['aid'];duration=e['fin']-e['inicio'];g=groups[aid]
        if pref[aid]:
            preferred_hours+=duration
            penalties['S01']+=sum(1 for h in range(e['inicio'],e['fin']) if (h<13)!=(pref[aid]=='MANANA'))
        (journeys if g in spec['grupos_jornada'] else thesis)[(g,e['dia'])].append(e)
        if e['tipo']=='LAB':
            if e['dia'] not in labs: issues.append(f"LAB fuera del conjunto de evaluación: {e['id']}")
            else:
                labs[e['dia']]+=duration;by_room[e['ambiente_id']][e['dia']]+=duration
        rid=e['ambiente_id']
        if rid and i.rooms[rid]['tipo']=='AULA':
            capacity_hours+=i.rooms[rid]['capacidad']*duration;room_hours+=duration
            penalties['S06']+=(i.rooms[rid]['capacidad']-e['estudiantes'])*duration
    details=[];isolated=0
    for (g,d),ee in sorted(journeys.items()):
        occupied={h for e in ee for h in range(e['inicio'],e['fin'])}
        usable=[h for h in spec['horas'] if min(occupied)<=h<max(occupied)+1]
        gaps=len(set(usable)-occupied)
        mixed=bool(any(h<13 for h in occupied) and any(h>=14 for h in occupied))
        alone=len(ee)==1;short=alone and ee[0]['fin']-ee[0]['inicio']<=2
        cost=int(alone)+int(short)
        penalties['S02']+=int(mixed);penalties['S03']+=gaps;penalties['S04']+=cost
        isolated+=int(alone)
        details.append({'grupo':g,'dia':d,'sesiones':len(ee),'horas_ocupadas_union':len(occupied),
            'huecos':gaps,'mixta':mixed,'aislada':alone,'penalizacion_aislada':cost})
    penalties['S05']=max(labs.values())-min(labs.values())
    normalized={k:Fraction(penalties[k],b) if b else None for k,b in spec['cotas'].items()}
    norm=spec['normalizacion'];weights=norm['pesos_activos']
    z=sum((Fraction(weights[k])*normalized[k] for k in weights),Fraction())
    integer=sum(norm['coeficientes'][k]*penalties[k] for k in penalties)
    for k,b in spec['cotas'].items():
        if penalties[k]<0 or penalties[k]>b: issues.append(f'{k}: penalización fuera de cota a priori')
    active=len(journeys)
    def ratio(n,d): return n/d if d else None
    return {'penalizaciones':penalties,'normalizadas':{k:float(v) if v is not None else None for k,v in normalized.items()},
        'normalizadas_exactas':{k:str(v) if v is not None else None for k,v in normalized.items()},
        'objetivo_entero_recalculado':integer,'objetivo_conceptual_exacto':str(z),'objetivo_conceptual':float(z),
        'objetivo_entero_sobre_escala':integer/norm['escala'],'violaciones_evaluacion':issues,
        'jornadas_activas':active,'detalle_jornadas':details,
        'tesis_sesiones_fuera_de_jornadas':sum(map(len,thesis.values())),
        'horas_laboratorio_por_dia':labs,'horas_por_laboratorio_y_dia':dict(by_room),
        'indicadores':{'cumplimiento_turno_porcentaje':ratio(100*(preferred_hours-penalties['S01']),preferred_hours),
            'huecos_promedio_jornada_activa':ratio(penalties['S03'],active),
            'jornadas_mixtas_porcentaje':ratio(100*penalties['S02'],active),
            'jornadas_aisladas_porcentaje':ratio(100*isolated,active),
            'ILAB':ratio(penalties['S05'],sum(labs.values())),
            'DCHN':ratio(penalties['S06'],room_hours),'desperdicio_capacidad':ratio(penalties['S06'],capacity_hours)},
        'limitaciones':spec['limitaciones']}
