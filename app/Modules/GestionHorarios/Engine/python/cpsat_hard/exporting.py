"""Exportación trazable en JSON y CSV; entradas originales inmutables."""
import csv
import json
from dataclasses import asdict
from pathlib import Path


def write_json(path, value):
    Path(path).write_text(json.dumps(value,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')


def schedule(i, built, solver):
    selected=[asdict(c) for j,c in enumerate(i.placements) if j in built.placements and solver.value(built.placements[j])]
    from .calendar_policy import planning_days
    order={day:k for k,day in enumerate(planning_days(i))}
    selected.sort(key=lambda c:(order[c['dia']],c['inicio'],c['actividad_id'],c['session_id']))
    fixed=[]
    for event in i.raw.get('repeticiones_fijas_m0',[]):
        row=dict(event)
        if event['tipo']=='T/P_MIXTA_FUENTE':
            row['composicion_curricular_modelada']={typ:solver.value(built.mixed_composition[(event['id'],typ)]) for typ in ('T','P')}
            row['orden_interno_T_P_no_determinado_por_fuente']=True
        fixed.append(row)
    return {'patrones_elegidos':[pid for pid,v in built.patterns.items() if solver.value(v)],
            'sesiones_elegidas':selected,'sesiones_fijas_repeticion':fixed}


def write_fixed_csv(path,rows):
    fields=['id','m0_id','curso_id','seccion_id','tipo','docente_id','ambiente_id','ambiente','dia','inicio','fin','duracion_h','fijo']
    with Path(path).open('w',encoding='utf-8-sig',newline='') as f:
        w=csv.DictWriter(f,fieldnames=fields,extrasaction='ignore');w.writeheader();w.writerows(rows)


def write_csv(path, i, rows):
    fields=['id','actividad_id','curso_id','curso','seccion_id','subgrupo_id','tipo','docente_id','ambiente_id','ambiente','dia','inicio','fin','duracion_h','patron_id','session_id','ciclo']
    with Path(path).open('w',encoding='utf-8-sig',newline='') as f:
        writer=csv.DictWriter(f,fieldnames=fields)
        writer.writeheader()
        for row in rows:
            a=i.activities[row['actividad_id']]
            extra={'curso_id':a['curso_id'],'curso':i.courses[a['curso_id']]['nombre'],
                   'subgrupo_id':a.get('subgrupo_id',a['id']) if a['tipo']=='LAB' else '',
                   'ambiente':i.rooms[row['ambiente_id']]['nombre'] if row['ambiente_id'] else 'CUBICULO_DOCENTE'}
            writer.writerow({**row,**extra})
