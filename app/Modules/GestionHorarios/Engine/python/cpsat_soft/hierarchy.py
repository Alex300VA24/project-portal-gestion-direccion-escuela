"""Mejora desde un horario válido: prioridades explícitas y recuperación segura."""
from time import monotonic
from ortools.sat.python import cp_model
from cpsat_hard.search_support import fixed_assumptions_copy
from cpsat_hard.data import InputError
from .academic import add
from .quality import measure

PROFILES=('CALIDAD_JERARQUICA_V1','CALIDAD_PARETO_PERSONAL_V1','CALIDAD_VECINDADES_V1','CALIDAD_PRIORIDADES_V2')


class ReferenceTimeout(TimeoutError):
    def __init__(self, mensaje, tiempo_s=0.0):
        super().__init__(mensaje)
        self.tiempo_s = tiempo_s


def complete_hint(model,solver):
    model.clear_hints()
    model.proto.solution_hint.vars.extend(range(len(model.proto.variables)))
    model.proto.solution_hint.values.extend(solver.response_proto.solution)


def optimize(entry,i,built,primary,legacy,lab_cost,seconds,workers):
    start=monotonic();metrics,entities=add(entry,i,built)
    metrics['cruces_secciones']=legacy.get('CRUCE_SECCIONES',0)
    metrics['pares_lab_incompatibles']=lab_cost if lab_cost is not None else 0
    index={(c.session_id,c.dia,c.inicio,c.fin,c.ambiente_id):j for j,c in enumerate(i.placements) if j in built.placements}
    chosen=set()
    for row in entry['sesiones']:
        j=index.get((row['sesion_motor'],row['dia'],row['inicio'],row['fin'],row['ambiente_id'] or ''))
        if j is None:raise InputError('El horario inicial no corresponde a las candidatas actuales')
        chosen.add(j)
    fixed=fixed_assumptions_copy(built.model);fixed.clear_hints()
    for j,v in built.placements.items():fixed.add(v==int(j in chosen))
    selected_patterns={i.placements[j].patron_id for j in chosen}
    for pid,v in built.patterns.items():fixed.add(v==int(pid in selected_patterns))
    initial=cp_model.CpSolver();initial.parameters.num_search_workers=workers
    initial.parameters.max_time_in_seconds=min(15,max(1,seconds/4));initial.parameters.random_seed=1
    status=initial.solve(fixed)
    if status==cp_model.UNKNOWN:raise ReferenceTimeout('Se agotó la verificación del punto de partida; se conserva el horario aceptado', initial.wall_time)
    if status not in (cp_model.FEASIBLE,cp_model.OPTIMAL):
        raise InputError('No se pudo verificar el punto de partida con las reglas actuales; el horario guardado se conserva')
    baseline_quality=measure(entry)
    independent=baseline_quality['resumen']
    for key,expression in metrics.items():
        if initial.value(expression)!=independent[key]:raise InputError(f'Métrica de calidad incoherente: {key}')
    # Estos límites son guardas SOFT del experimento, no nuevas reglas HARD.
    model=fixed_assumptions_copy(built.model);model.clear_hints()
    protected=set(metrics)
    if entry.get('perfil_calidad')=='CALIDAD_PRIORIDADES_V2':
        protected={'cruces_secciones','huecos_comunes','huecos_sin_oferta_lab','huecos_docentes','horas_fuera_preferencia_docente'}
    for key in protected:model.add(metrics[key]<=initial.value(metrics[key]))
    for key in ('S01','PREFERENCIAS'):
        if key in legacy:model.add(legacy[key]<=initial.value(legacy[key]))
    if entry.get('perfil_calidad')=='CALIDAD_PARETO_PERSONAL_V1':
        for group in (entities[key] for key in ('huecos','cortas','carga_maxima')):
            for expression in group.values():model.add(expression<=initial.value(expression))
    stages=[('COMPATIBILIDAD',metrics['cruces_secciones']),
        ('COMPACTACION',metrics['huecos_sin_oferta_lab']+metrics['huecos_docentes']),
        ('PREFERENCIAS_DOCENTES',metrics['horas_fuera_preferencia_docente']),
        ('LABORATORIOS',metrics['pares_lab_incompatibles']),
        ('JORNADAS',metrics['jornadas_cortas_seccion']+metrics['jornadas_cortas_docentes']+metrics['carga_maxima_docente_sumada']),
        ('OBJETIVO_EXISTENTE',primary)]
    best=initial;trace=[];first=monotonic()-start
    sections={s['id']:s for s in entry['secciones']}
    same_time={}
    for j,c in enumerate(i.placements):
        if j in built.placements:same_time.setdefault((c.session_id,c.dia,c.inicio,c.fin),[]).append(built.placements[j])
    def neighborhoods(name):
        if entry.get('perfil_calidad')!='CALIDAD_VECINDADES_V1':return [('PERIODO_COMPLETO',None)]
        if name=='OBJETIVO_EXISTENTE':return [('SOLO_AMBIENTES',set())]
        scopes=[]
        if name in ('COMPATIBILIDAD','COMPACTACION','LABORATORIOS'):
            field='cruces_secciones' if name=='COMPATIBILIDAD' else 'huecos_comunes'
            cycles=[c['ciclo'] for c in baseline_quality['ciclos'] if c[field]>0 or name=='LABORATORIOS']
            for cycle in cycles:
                from .quality import section_scopes
                _,alternatives=section_scopes(entry)
                own={a['id'] for a in entry['actividades'] if sections[a['seccion_id']]['ciclo']==cycle and (name!='LABORATORIOS' or a['id'] in alternatives)}
                if own:scopes.append((f'CICLO_{cycle}',own))
        if name in ('COMPACTACION','JORNADAS','PREFERENCIAS_DOCENTES'):
            field={'COMPACTACION':'huecos','JORNADAS':'carga_maxima_dia','PREFERENCIAS_DOCENTES':'horas_fuera_preferencia'}[name]
            for teacher in sorted(baseline_quality['docentes'],key=lambda t:-t[field])[:4]:
                if teacher[field]>0:
                    scopes.append((f"DOCENTE_{teacher['docente_id']}",{a['id'] for a in entry['actividades'] if str(a['docente_id'])==teacher['docente_id']}))
        return scopes or [('PERIODO_COMPLETO',None)]
    for n,(name,expression) in enumerate(stages):
        remaining=seconds-(monotonic()-start)
        if remaining<=0:break
        before=best.value(expression)
        if before==0:
            model.add(expression<=0);trace.append({'etapa':name,'estado':'CERO_INICIAL','valor':0,'tiempo_s':0});continue
        targets=neighborhoods(name);deadline=monotonic()+remaining/(len(stages)-n)
        for at,(label,movable) in enumerate(targets):
            remaining_stage=deadline-monotonic()
            if remaining_stage<=0:break
            candidate=model.clone();candidate.clear_hints();candidate.add(expression<=best.value(expression))
            if movable is not None:
                values=best.response_proto.solution
                selected=[(j,c) for j,c in enumerate(i.placements) if j in built.placements and values[built.placements[j].index]]
                for j,c in selected:
                    if label=='SOLO_AMBIENTES':
                        same=same_time[c.session_id,c.dia,c.inicio,c.fin]
                        candidate.add(sum(same)==1)
                    elif c.actividad_id not in movable:candidate.add(built.placements[j]==1)
            complete_hint(candidate,best)
            candidate.minimize(expression)
            solver=cp_model.CpSolver();solver.parameters.num_search_workers=workers
            solver.parameters.max_time_in_seconds=max(0.02,(deadline-monotonic())/(len(targets)-at))
            solver.parameters.random_seed=1
            state=solver.solve(candidate)
            accepted=state in (cp_model.FEASIBLE,cp_model.OPTIMAL) and solver.value(expression)<=best.value(expression)
            if accepted:best=solver
            trace.append({'etapa':name,'vecindad':label,'estado':solver.status_name(state),'valor_inicial':before,'valor':best.value(expression),
                'cota':solver.best_objective_bound,'tiempo_s':solver.wall_time,'aplicado':accepted,
                'alcance_cota':'Etapa/vecindad condicionada por guardas y resultados anteriores; no es cota global.'})
        model.add(expression<=best.value(expression))
    return best,cp_model.FEASIBLE,{'perfil':entry.get('perfil_calidad'),'etapas':trace,'tiempo_s':monotonic()-start,
        'primera_solucion_s':first,'referencia_verificada':True,'metricas_protegidas':sorted(protected),'original':{k:initial.value(v) for k,v in metrics.items()},
        'obtenido':{k:best.value(v) for k,v in metrics.items()},'objetivo_original_inicial':initial.value(primary),
        'objetivo_original_final':best.value(primary),'alcance':'Deterioro agregado cero respecto a la referencia en las métricas protegidas y cero deterioro de objetivos de etapas anteriores. Los criterios posteriores pueden variar; ninguna etapa relaja HARD. No acredita óptimo global.'}
