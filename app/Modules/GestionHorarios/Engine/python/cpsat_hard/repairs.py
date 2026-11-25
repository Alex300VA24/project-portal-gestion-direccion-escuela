"""Propuestas mínimas de ventanas, en un problema local separado de producción.

No cambia el JSON/CSV original, no reasigna docentes ni reduce horas. La
disponibilidad nueva queda marcada como propuesta pendiente de autorización.
"""
import argparse
import copy
from collections import defaultdict
from dataclasses import asdict
import json
from itertools import combinations
from pathlib import Path
from ortools.sat.python import cp_model
from .data import from_records,load,Placement,InputError
from .domains import windows_for
from .model import build
from .policy import conflict_cohort,cycle_capacity_bounds
from .exporting import schedule,write_json
from .validation import validate
from .diagnostic_report import detailed,write_report


def project(i,cohort):
    raw=copy.deepcopy(i.raw)
    acts=[a for a in raw['actividades'] if conflict_cohort(i,a)==cohort]
    aids={a['id'] for a in acts}; sections={a['seccion_id'] for a in acts}
    course_ids={a['curso_id'] for a in acts}
    raw['actividades']=acts
    raw['secciones']=[s for s in raw['secciones'] if s['id'] in sections]
    raw['cursos']=[c for c in raw['cursos'] if c['id'] in course_ids]
    for c in raw['cursos']:
        ss=[s for s in raw['secciones'] if s['curso_id']==c['id']]
        c['n_secciones']=len(ss)
        c['demanda_experimental']=sum(s['estudiantes'] for s in ss)
    raw['patrones']=[p for p in raw['patrones'] if p['actividad_id'] in aids]
    raw['sesiones_alternativas']=[s for s in raw['sesiones_alternativas'] if s['actividad_id'] in aids]
    raw['conflictos_estudiantes']=[p for p in raw.get('conflictos_estudiantes',[]) if set(p)<=aids]
    raw['cobertura_conflictos']={'completa':False,'fuente':'PROYECCION_LOCAL_NO_CERTIFICA_COBERTURA_GLOBAL'}
    raw['metadata']['calidad']='SUPUESTOS_EXPERIMENTALES_ETIQUETADOS'
    return raw


def generate_candidates(raw):
    """Malla completa local para explorar ventanas nuevas; el resto sigue HARD."""
    acts={a['id']:a for a in raw['actividades']}
    candidates=[]
    cal=raw['calendario']
    for s in raw['sesiones_alternativas']:
        a=acts[s['actividad_id']]
        for day in cal['dias']:
            for start in range(cal['hora_inicial'],cal['hora_final']-s['duracion']+1):
                for rid in a['ambientes_candidatos'] if a['requiere_ambiente'] else ['']:
                    candidates.append(Placement(f'PROP{len(candidates):06d}',s['id'],s['patron_id'],a['id'],a['docente_id'],
                        a['seccion_id'],a['tipo'],day,start,start+s['duracion'],s['duracion'],rid,a['ciclo_politica_sabado']))
    return candidates


def outside_slots(i,c,scenario):
    ws=windows_for(i,i.activities[c.actividad_id],scenario)
    if ws is None: return []
    return [(c.docente_id,c.dia,h) for h in range(c.inicio,c.fin)
            if not any(w['dia']==c.dia and w['inicio']<=h<w['fin'] for w in ws)]


def repair_lower_bounds(local,built,outside):
    """Cotas desde la malla local completa; no supone CSV exhaustivo.

    Si dos cursos saturan sus dominios previos y comparten bloques, al menos
    un docente de esos cursos necesita ampliar su disponibilidad. El mínimo
    conjunto que toca todos esos pares es otra cota necesaria, no un horario.
    """
    forbidden={r['id'] for r in built.rejected}
    forbidden.update(local.placements[j].id for j,slots in outside.items() if slots)
    rows=cycle_capacity_bounds(local,built.cohorts,forbidden)
    edges=[]
    evidence=[]
    for row in rows:
        for a,b in combinations(row['cursos'],2):
            sa,sb=set(map(tuple,a['slots_permitidos'])),set(map(tuple,b['slots_permitidos']))
            if len(sa)==a['minimo_horas_ocupadas'] and len(sb)==b['minimo_horas_ocupadas'] and sa & sb:
                teachers=set(a['cargas_por_docente']) | set(b['cargas_por_docente'])
                edges.append(teachers)
                evidence.append({'cursos':[a['curso_id'],b['curso_id']],
                                 'docentes_al_menos_uno_debe_cambiar':sorted(teachers),
                                 'coincidencias_forzadas':sorted(sa & sb)})
    docs=sorted(set().union(*edges)) if edges else []
    minimum_docs=0
    exhaustive=len(docs)<=16
    if edges and exhaustive:
        for size in range(1,len(docs)+1):
            if any(all(set(subset)&edge for edge in edges) for subset in combinations(docs,size)):
                minimum_docs=size
                break
    return {'metodo':'COTAS_DE_DOMINIO_COMPLETO_Y_COBERTURA_DE_PARES_FORZADOS',
            'cota_horas_nuevas':sum(r['deficit'] for r in rows),'cota_docentes_afectados':minimum_docs,
            'busqueda_docentes_exhaustiva':exhaustive,'pares_forzados':evidence,
            'dominios_previos_regenerados':rows}


def propose(i,row,scenario='B',time_limit=30):
    cohort=(row['ciclo'],row['seccion_horario'])
    raw=project(i,cohort)
    local=from_records(raw,generate_candidates(raw),hashes=i.hashes)
    built=build(local,scenario,diagnostic_window_expansion=True)
    outside={j:outside_slots(local,c,scenario) for j,c in enumerate(local.placements)}
    costs=[len(slots)*built.placements[j] for j,slots in outside.items() if slots]
    teachers=defaultdict(list)
    for j,slots in outside.items():
        if slots: teachers[local.placements[j].docente_id].append(built.placements[j])
    changed=[]
    for doc,vs in sorted(teachers.items()):
        v=built.model.new_bool_var('propuesta_doc_'+doc)
        built.model.add_max_equality(v,vs)
        changed.append(v)
    hours=sum(costs)
    # La cota calculada desde el dominio original solo prueba horas necesarias
    # al ampliar sus ventanas. Este modelo también permite más colocaciones
    # dentro de ventanas previas, por lo que no se impone esa cota como supuesto.
    lower=repair_lower_bounds(local,built,outside)
    built.model.add(hours>=lower['cota_horas_nuevas'])
    built.model.add(sum(changed)>=lower['cota_docentes_afectados'])
    built.model.minimize((len(teachers)+1)*hours+sum(changed))
    solver=cp_model.CpSolver()
    solver.parameters.max_time_in_seconds=time_limit
    solver.parameters.num_search_workers=1
    solver.parameters.random_seed=1
    status=solver.solve(built.model)
    label=solver.status_name(status)
    result={'horario':f'{cohort[0]}-{cohort[1]}','estado':label,'autorizado':False,
            'tipo':'PROPUESTA_HIPOTETICA_DISPONIBILIDAD','alcance':'HORARIO_DEFICITARIO_AISLADO',
            'minimo_local_demostrado':status==cp_model.OPTIMAL,
            'tiempo_s':solver.wall_time,'limite_s':time_limit,'estadisticas':solver.response_stats(),
            'funcion_objetivo':'MIN_HORAS_NUEVAS_Y_LUEGO_MIN_DOCENTES; NO_SOFT_ACADEMICO',
            'prueba_cotas_minimas':lower,
            'fuentes_sha256':i.hashes}
    if status not in (cp_model.OPTIMAL,cp_model.FEASIBLE): return result
    chosen=schedule(local,built,solver)
    points=set()
    for j,c in enumerate(local.placements):
        if solver.value(built.placements[j]):
            for _,day,h in outside[j]:
                points.add((c.docente_id,local.activities[c.actividad_id]['curso_id'],day,h))
    grouped=defaultdict(list)
    for doc,course,day,h in sorted(points): grouped[(doc,course,day)].append(h)
    windows=[]
    for (doc,course,day),hs in sorted(grouped.items()):
        for h in hs:
            if windows and (windows[-1]['docente_id'],windows[-1]['curso_id'],windows[-1]['dia'],windows[-1]['fin'])==(doc,course,day,h):
                windows[-1]['fin']=h+1
            else: windows.append({'docente_id':doc,'curso_id':course,'dia':day,'inicio':h,'fin':h+1,
                                  'autorizado':False,'estado':'PENDIENTE_CONFIRMACION_DOCENTE_Y_VALIDACION'})
    result.update(horas_nuevas=int(solver.value(hours)),docentes_afectados=int(sum(solver.value(v) for v in changed)),
                  ventanas_propuestas=windows,horario_local=chosen)
    if result['horas_nuevas']==lower['cota_horas_nuevas'] and result['docentes_afectados']==lower['cota_docentes_afectados']:
        result['minimo_local_demostrado']=True
    # Comprobación sin predicados del modelo: añadir solo ventanas propuestas
    # a una copia y ejecutar el validador independiente del horario testigo.
    assumed=copy.deepcopy(raw)
    for n,w in enumerate(windows):
        aa=[a for a in assumed['actividades'] if a['docente_id']==w['docente_id'] and a['curso_id']==w['curso_id']]
        if not all(a['es_repeticion'] for a in aa):
            raise InputError('La planificación automática de ventanas está acotada a repeticiones; otros casos requieren análisis de alcance')
        assumed['ventanas_disponibilidad'].append({'id':f'PROPUESTA_{n}',**w,
            'alcance':f"CURSO_{w['curso_id']}",'escenario_fuente':'REP_B_RESTRINGIDO',
            'evidencia':'HIPOTESIS_PENDIENTE_AUTORIZACION_NO_USAR_COMO_DISPONIBILIDAD_REAL'})
    hypothetical=from_records(assumed,local.placements,hashes=i.hashes)
    result['validacion_local']=validate(hypothetical,chosen,scenario)
    if not result['validacion_local']['cumple_reglas_con_datos_disponibles']:
        raise InputError('La propuesta no superó la validación independiente local')
    return result


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--instance',default='instancia_cp_sat_2026II.json')
    p.add_argument('--placements',default='colocaciones_candidatas.csv')
    p.add_argument('--policy');p.add_argument('--conflicts');p.add_argument('--institutional-blocks')
    p.add_argument('--run-dir',required=True)
    p.add_argument('--time',type=float,default=30)
    args=p.parse_args()
    if args.time<=0: p.error('Tiempo debe ser positivo')
    folder=Path(args.run_dir)
    outcome=json.loads((folder/'resultado.json').read_text(encoding='utf-8'))
    i=load(args.instance,args.placements,args.conflicts,args.policy,args.institutional_blocks)
    if i.hashes!=outcome['fuentes_sha256']: raise InputError('Fuentes distintas de la corrida original')
    if (folder/'propuestas_minimas.json').exists(): raise InputError('Propuestas existentes; no sobrescribir')
    built=build(i,outcome['escenario'])
    report=detailed(i,built,outcome['estado'],outcome['escenario'])
    plans=[]
    for row in built.cycle_bounds:
        if row['deficit']:
            aa=[a for a in i.activities.values() if built.cohorts[a['id']]==(row['ciclo'],row['seccion_horario'])]
            if not all(a['es_repeticion'] for a in aa):
                plans.append({'horario':f"{row['ciclo']}-{row['seccion_horario']}",'estado':'REQUIERE_ANALISIS_DE_ALCANCE_REGULAR','autorizado':False})
            else: plans.append(propose(i,row,outcome['escenario'],args.time))
    write_json(folder/'propuestas_minimas.json',plans)
    write_report(folder,report,plans)
    print([(r['horario'],r['estado'],r.get('horas_nuevas')) for r in plans])


if __name__=='__main__': main()
