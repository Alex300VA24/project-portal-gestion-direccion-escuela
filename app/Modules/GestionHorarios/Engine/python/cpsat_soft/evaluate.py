"""Revalidación posterior en proceso independiente sin dependencia OR-Tools."""
import argparse
import json
from cpsat_hard.data import load
from cpsat_hard.validation import validate
from cpsat_hard.exporting import write_json
from .metrics import evaluate
from .specification import prepare


def main():
    p=argparse.ArgumentParser()
    p.add_argument('--instance',default='instancia_cp_sat_2026II.json')
    p.add_argument('--placements',default='colocaciones_candidatas.csv')
    p.add_argument('--policy');p.add_argument('--conflicts')
    p.add_argument('--schedule',required=True);p.add_argument('--spec',required=True);p.add_argument('--output',required=True)
    p.add_argument('--institutional-blocks')
    args=p.parse_args()
    i=load(args.instance,args.placements,args.conflicts,args.policy,args.institutional_blocks)
    with open(args.schedule,encoding='utf-8') as f: outcome=json.load(f)
    with open(args.spec,encoding='utf-8') as f: spec=json.load(f)
    if outcome['fuentes_sha256']!=i.hashes: raise ValueError('Entradas diferentes de la corrida')
    if spec!=prepare(i,spec['dias_laboratorio'],lab_source=spec['fuente_dias_laboratorio']):
        raise ValueError('Especificación de conjuntos/cotas/pesos alterada')
    hard=validate(i,outcome,outcome['escenario']);metrics=evaluate(i,outcome,spec)
    ok=(hard['cumple_reglas_con_datos_disponibles'] and not metrics['violaciones_evaluacion']
        and metrics['objetivo_entero_recalculado']==outcome['valor_objetivo']
        and metrics['penalizaciones']==outcome['penalizaciones_solver'])
    report={'estado':'VALIDO' if ok else 'INVALIDO','validacion_hard':hard,'metricas_soft':metrics}
    write_json(args.output,report);print(report['estado'])
    return 0 if ok else 2


if __name__=='__main__': raise SystemExit(main())
