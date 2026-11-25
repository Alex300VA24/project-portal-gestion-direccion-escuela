"""Revalidación fuera del proceso de resolución, sin importar OR-Tools."""
import argparse
import json
from .data import load
from .exporting import write_json
from .validation import validate


def main():
    p=argparse.ArgumentParser()
    p.add_argument('--instance',default='instancia_cp_sat_2026II.json')
    p.add_argument('--placements',default='colocaciones_candidatas.csv')
    p.add_argument('--conflicts')
    p.add_argument('--policy')
    p.add_argument('--schedule',required=True)
    p.add_argument('--output',required=True)
    p.add_argument('--institutional-blocks')
    args=p.parse_args()
    i=load(args.instance,args.placements,args.conflicts,args.policy,args.institutional_blocks)
    with open(args.schedule,encoding='utf-8') as f: result=json.load(f)
    if result.get('fuentes_sha256') and result['fuentes_sha256']!=i.hashes:
        raise ValueError('Hashes de entrada no coinciden con la corrida')
    report=validate(i,result,result['escenario'])
    write_json(args.output,report)
    print(report['estado'])
    return 0 if report['cumple_reglas_con_datos_disponibles'] else 2


if __name__=='__main__': raise SystemExit(main())
