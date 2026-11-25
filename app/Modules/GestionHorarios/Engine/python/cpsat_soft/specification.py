"""Conjuntos y cotas fijados antes de resolver; aritmética racional trazable."""
from fractions import Fraction
from math import lcm
from cpsat_hard.data import InputError
from cpsat_hard.policy import conflict_cycle, conflict_cohort

KEYS=tuple(f'S{k:02}' for k in range(1,7))
PREFERENCES={2:'MANANA',4:'TARDE',6:'MANANA',8:'TARDE',10:'MANANA'}
SEMESTER_PREFERENCES={cycle: ('MANANA' if ((cycle-1)//2)%2 == 0 else 'TARDE') for cycle in range(1,11)}
WEIGHTS=dict(zip(KEYS,[Fraction(1,8)]*4+[Fraction(1,4)]*2))


def prepare(i, lab_days, *, lab_source='CONFIRMACION_EXPRESA_USUARIO'):
    from cpsat_hard.calendar_policy import planning_days
    cal=i.raw['calendario'];days=planning_days(i)
    if not lab_days or len(set(lab_days))!=len(lab_days) or not set(lab_days)<=set(days):
        raise InputError('Se requieren días habilitados de laboratorio explícitos, únicos y dentro del calendario')
    hours=[b['inicio'] for b in cal['bloques_hora_base']]
    if len(hours)!=12 or cal['receso']!={'inicio':13,'fin':14}:
        raise InputError('Las cotas de tesis presuponen 12 bloques y receso 13–14')
    groups={};preferences={};excluded=[]
    cycle_preferences = SEMESTER_PREFERENCES if i.raw.get('politica_turnos') == 'ALTERNANCIA_CICLOS_V2' else PREFERENCES
    for aid,a in i.activities.items():
        # Las tutorías de Tesis no identifican una sección de clase común.
        if not a['requiere_ambiente']:
            groups[aid]='TESIS:'+aid
            excluded.append(aid)
        else:
            cycle,label=conflict_cohort(i,a)
            groups[aid]=f'{cycle}:{label}'
        preferences[aid]=cycle_preferences.get(conflict_cycle(a))
    fixed=i.raw.get('repeticiones_fijas_m0',[])
    fixed_aids={aid for e in fixed for aid in e['actividad_ids']}
    pref_hours=sum(a['horas_semanales'] for aid,a in i.activities.items() if aid not in fixed_aids and preferences[aid])
    lab_hours=sum(a['horas_semanales'] for aid,a in i.activities.items() if aid not in fixed_aids and a['tipo']=='LAB')
    max_rooms={};b6=0
    for aid,a in i.activities.items():
        if aid in fixed_aids or not a['requiere_ambiente'] or a['tipo_ambiente']!='AULA': continue
        capacities=[i.rooms[r]['capacidad'] for r in a['ambientes_candidatos']
                    if i.rooms[r]['tipo']=='AULA' and i.rooms[r]['capacidad']>=a['estudiantes']]
        if not capacities: raise InputError(f'{aid}: sin aula compatible para calcular B6')
        max_rooms[aid]=max(capacities)
        b6+=(max_rooms[aid]-a['estudiantes'])*a['horas_semanales']
    for e in fixed:
        aid=e['actividad_ids'][0]
        if preferences[aid]: pref_hours+=e['duracion_h']
        room=i.rooms[e['ambiente_id']]
        if e['tipo']=='LAB': lab_hours+=e['duracion_h']
        if room['tipo']=='AULA': b6+=(room['capacidad']-i.sections[e['seccion_id']]['estudiantes'])*e['duracion_h']
    section_groups=sorted(set(g for a,g in groups.items() if a not in excluded))
    sd=len(section_groups)*len(days)
    bounds=dict(zip(KEYS,[pref_hours,sd,10*sd,2*sd,lab_hours,b6]))
    return {'version':'TESIS_S01_S06_V1','politica_turnos':i.raw.get('politica_turnos', 'HISTORICA_PARES_V1'), 'preferencia_por_ciclo':{str(c):shift for c,shift in cycle_preferences.items()}, 'dias':list(days),'horas':hours,
        'dias_laboratorio':list(lab_days),'fuente_dias_laboratorio':lab_source,
        'grupo_por_actividad':groups,'grupos_jornada':section_groups,
        'preferencia_por_actividad':preferences,'tesis_fuera_de_jornadas':excluded,
        'max_capacidad_compatible_por_actividad':max_rooms,'cotas':bounds,
        'normalizacion':integer_weights(bounds),
        'limitaciones':[
            'Jornadas por ciclo/sección confirmadas por el usuario; intervalos de subgrupos se unen. Sin padrones completos no representan itinerarios individuales.',
            'Tesis: sesiones sin aula incluidas en turno si tienen preferencia; jornadas de tutoría reportadas por separado, excluidas de S02–S04 y sus cotas.',
            'Repeticiones: cada curso fijo constituye un grupo separado; no se infiere matrícula compartida III-R.',
            'Días LAB lunes–sábado confirmados por usuario; los sábados HARD de ciclos regulares siguen deshabilitados. No se amplían dominios.',
            'S03 usa B3=10|S||D| de la tesis, frente a la descripción histórica 12|S||D| del JSON original.',
            'Las repeticiones aportan las componentes físicas efectivas de su política explícita; no se inventan horas LAB.'
        ]}


def integer_weights(bounds, *, weights=None):
    conceptual = WEIGHTS if weights is None else weights
    keys = tuple(conceptual)
    active=[k for k in keys if bounds[k]>0]
    total=sum((conceptual[k] for k in active),Fraction())
    w={k:conceptual[k]/total for k in active} if active else {}
    ratios={k:w[k]/bounds[k] for k in active}
    scale=lcm(*(v.denominator for v in ratios.values())) if ratios else 1
    coeff={k:int(v*scale) for k,v in ratios.items()}
    exact=True
    if sum(coeff[k]*bounds[k] for k in active)>2**53-1:
        exact=False;scale=10**9
        while True:
            coeff={k:(2*(v*scale).numerator+(v*scale).denominator)//(2*(v*scale).denominator) for k,v in ratios.items()}
            if all(coeff.values()) and sum(abs(Fraction(coeff[k]*bounds[k],scale)-w[k]) for k in active)<=Fraction(1,10**6): break
            scale*=10
            if scale>10**15: raise InputError('No se puede escalar objetivo con precisión segura')
    upper=sum(coeff[k]*bounds[k] for k in active)
    if upper>2**53-1: raise InputError('Objetivo entero supera rango exacto de reporte')
    effective={k:Fraction(coeff[k]*bounds[k],upper) for k in active}
    return {'pesos_conceptuales':{k:str(conceptual[k]) for k in keys},
        'pesos_activos':{k:str(v) for k,v in w.items()},'inactivos_cota_cero':[k for k in keys if k not in active],
        'escala':scale,'coeficientes':{k:coeff.get(k,0) for k in keys},'conversion_exacta':exact,
        'pesos_efectivos':{k:str(v) for k,v in effective.items()},
        'error_maximo_peso':float(max((abs(effective[k]-w[k]) for k in active),default=0)),
        'cota_superior_objetivo_entero':upper}
