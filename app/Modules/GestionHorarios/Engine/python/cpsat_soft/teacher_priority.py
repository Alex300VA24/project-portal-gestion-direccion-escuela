"""SOFT-P01: decisiones globales, objetivos docentes lexicográficos certificados.

Nunca fija clases de un docente para optimizar el siguiente. Solo conserva
valores óptimos demostrados de criterios no negativos. Ante una etapa sin
certificado, detiene la jerarquía y devuelve la mejor solución HARD disponible.
"""
from time import monotonic
from collections import defaultdict
from ortools.sat.python import cp_model
from cpsat_hard.data import InputError
from cpsat_hard.search_support import fixed_assumptions_copy, recover_seed
from .academic import add
from .hierarchy import complete_hint

POLICY='SOFT_P01_LEXICOGRAFICA_V1'
CRITERIA=('huecos','dias','preferencias','cortas')


def lower_bounds(entry,i,built):
    """Cotas necesarias, no disponibilidad nueva ni restricciones adicionales.

    H02 impide dictar dos clases simultáneas: las horas de dominio de un día
    limitan su carga. Para k días hay, como máximo, floor(carga/3) jornadas
    largas; las demás tienen una o dos horas. El receso no se cuenta.
    """
    slots=defaultdict(lambda:defaultdict(set));loads=defaultdict(int)
    rejected={r['id'] for r in built.rejected}
    for a in entry['actividades']:loads[str(a['docente_id'])]+=a['horas']
    for j,c in enumerate(i.placements):
        if j in built.placements and c.id not in rejected:slots[str(c.docente_id)][c.dia].update(range(c.inicio,c.fin))
    result={}
    for tid,load in loads.items():
        capacities=sorted((len(hs) for hs in slots[tid].values()),reverse=True)
        total=0;days=0
        for capacity in capacities:
            if total>=load:break
            total+=capacity;days+=1
        forced={s['dia'] for s in entry['sesiones'] if s.get('fijada') and any(a['id']==s['actividad_id'] and str(a['docente_id'])==tid for a in entry['actividades'])}
        result[tid]={'dias':max(days,len(forced)),'horas':load,'capacidades_diarias':capacities}
    return result


def participants(entry):
    snapshot=entry.get('prioridad_docente')
    if snapshot is None:return []
    if not isinstance(snapshot,dict) or snapshot.get('politica')!=POLICY or type(snapshot.get('version')) is not int or snapshot['version']<0:
        raise InputError('Instantánea de prioridad docente inválida')
    order=snapshot.get('orden')
    if not isinstance(order,list):raise InputError('Orden docente inválido')
    ids=[]
    for n,row in enumerate(order):
        if not isinstance(row,dict) or not isinstance(row.get('docente_id'),str) or not row['docente_id'] or type(row.get('posicion')) is not int or row['posicion']!=n+1:
            raise InputError('La prioridad debe contener identidades y posiciones consecutivas')
        ids.append(row['docente_id'])
    if len(ids)!=len(set(ids)):raise InputError('Docente duplicado en la prioridad')
    active={str(a['docente_id']) for a in entry['actividades']}
    return [row for row in order if row['docente_id'] in active]


def optimize(entry,i,built,general,seconds,workers,seed=None,reference_valid=False):
    start=monotonic();ordered=participants(entry)
    _,entities=add(entry,i,built)
    bounds=lower_bounds(entry,i,built)
    model=fixed_assumptions_copy(built.model);model.clear_objective()
    trace=[];best=None;status=cp_model.UNKNOWN
    reference_used=False
    if reference_valid:
        index={(c.session_id,c.dia,c.inicio,c.fin,c.ambiente_id):j for j,c in enumerate(i.placements) if j in built.placements}
        chosen=[index.get((s['sesion_motor'],s['dia'],s['inicio'],s['fin'],s['ambiente_id'] or '')) for s in entry['sesiones']]
        if all(j is not None for j in chosen):
            selected=set(chosen);patterns={i.placements[j].patron_id for j in selected}
            values={v.index:int(j in selected) for j,v in built.placements.items()}
            values.update({v.index:int(pid in patterns) for pid,v in built.patterns.items()})
            class Reference:
                def value(self,v):return values[v.index]
            best,status=recover_seed(built,Reference(),max(.01,min(10,seconds/5)))
            reference_used=status in (cp_model.FEASIBLE,cp_model.OPTIMAL)
            if not reference_used:best=None
    if best is None and seed is not None:
        best,status=recover_seed(built,seed,max(.01,min(10,seconds/5)))
        if status not in (cp_model.FEASIBLE,cp_model.OPTIMAL):best=None
    if best is None:
        first=cp_model.CpSolver();first.parameters.num_search_workers=workers;first.parameters.random_seed=1
        first.parameters.max_time_in_seconds=max(.001,seconds-(monotonic()-start))
        first.parameters.stop_after_first_solution=True
        status=first.solve(model)
        if status not in (cp_model.FEASIBLE,cp_model.OPTIMAL):
            return first,status,{'politica':POLICY,'version':entry['prioridad_docente']['version'],'etapas':[],'jerarquia_certificada':False,'tiempo_s':monotonic()-start}
        best=first
    first_time=monotonic()-start
    initial_quality={row['docente_id']:{key:best.value(entities[key].get(('DOCENTE',row['docente_id']),0)) for key in CRITERIA} for row in ordered}
    stages=[(row,criterion,entities[criterion].get(('DOCENTE',row['docente_id']),0)) for row in ordered for criterion in CRITERIA]
    stages.append((None,'OBJETIVOS_GENERALES',general))
    complete=True
    for n,(teacher,criterion,expression) in enumerate(stages):
        value=best.value(expression)
        remaining=seconds-(monotonic()-start)
        item={'docente_id':teacher['docente_id'] if teacher else None,'posicion':teacher['posicion'] if teacher else None,'criterio':criterion,'valor_inicial':value,'valor':value}
        bound=0;proof=None
        if teacher:
            proof=bounds[teacher['docente_id']]
            if criterion=='dias':bound=proof['dias']
            if criterion=='cortas':bound=max(0,best.value(entities['dias'].get(('DOCENTE',teacher['docente_id']),0))-proof['horas']//3)
        if value==bound:
            model.add(expression==bound);trace.append({**item,'estado':'OPTIMAL','certificada':True,'metodo':'COTA_ANALITICA' if bound else 'COTA_INFERIOR_CERO','cota':bound,'prueba':proof,'tiempo_s':0});continue
        if remaining<=0:
            trace.append({**item,'estado':'SIN_TIEMPO','certificada':False});complete=False;break
        model.add(expression<=value)
        model.minimize(expression);complete_hint(model,best)
        solver=cp_model.CpSolver();solver.parameters.num_search_workers=workers;solver.parameters.random_seed=1
        # La etapa superior dispone de todo el tiempo restante. Reservar cuotas
        # para etapas que no pueden empezar sin este certificado desperdicia tiempo.
        solver.parameters.max_time_in_seconds=max(.001,remaining)
        current=solver.solve(model)
        if current in (cp_model.FEASIBLE,cp_model.OPTIMAL):best=solver;value=solver.value(expression)
        trace.append({**item,'valor':value,'estado':solver.status_name(current),'cota':solver.best_objective_bound,'tiempo_s':solver.wall_time,'certificada':current==cp_model.OPTIMAL})
        if current!=cp_model.OPTIMAL:
            complete=False;break
        model.add(expression==value)
    certified=[row['docente_id'] for row in ordered if all(any(t['docente_id']==row['docente_id'] and t['criterio']==key and t['estado']=='OPTIMAL' for t in trace) for key in CRITERIA)]
    return best,cp_model.OPTIMAL if complete else cp_model.FEASIBLE,{
        'politica':POLICY,'version':entry['prioridad_docente']['version'],'orden_aplicado':ordered,'criterios_por_docente':list(CRITERIA),
        'etapas':trace,'docentes_certificados':certified,'jerarquia_certificada':len(certified)==len(ordered),'objetivos_generales_certificados':complete,
        'calidad_inicial':initial_quality,'calidad_final':{row['docente_id']:{key:best.value(entities[key].get(('DOCENTE',row['docente_id']),0)) for key in CRITERIA} for row in ordered},
        'tiempo_primera_solucion_s':first_time,'tiempo_s':monotonic()-start,
        'punto_partida':'HORARIO_GUARDADO_VALIDADO' if reference_used else 'SOLUCION_HARD_INICIAL',
        'alcance':'Óptimos sucesivos condicionados a los óptimos anteriores. La primera etapa no certificada detiene las prioridades inferiores; no se congelan sesiones.'}
