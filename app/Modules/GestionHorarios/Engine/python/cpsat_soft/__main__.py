"""python -m cpsat_soft: no modifica módulos ni restricciones del motor HARD."""
import argparse
import hashlib
import json
import time
from pathlib import Path
from cpsat_hard.data import load
from cpsat_hard.model import build
from cpsat_hard.validation import validate
from cpsat_hard.exporting import write_json,write_csv,write_fixed_csv
from .specification import prepare
from .model import add
from .runner import solve,add_hint
from .metrics import evaluate
from .official import DEFAULT_VARIANT,DEFAULT_SEARCH_PROFILE,VARIANTS
from .search import PROFILES
from exporters.runs import default_output,start_run,finish_run


def main():
    p=argparse.ArgumentParser()
    p.add_argument('--instance',default='instancia_cp_sat_2026II.json')
    p.add_argument('--placements',default='colocaciones_candidatas.csv')
    p.add_argument('--policy');p.add_argument('--conflicts')
    p.add_argument('--lab-days',nargs='+',required=True)
    p.add_argument('--scenario',choices=['A','B'],default='B')
    p.add_argument('--baseline',help='Resultado HARD real validado, usado como hint y comparación')
    p.add_argument('--variant',choices=VARIANTS,default=DEFAULT_VARIANT)
    p.add_argument('--search-profile',choices=PROFILES)
    p.add_argument('--time',type=float,default=300);p.add_argument('--workers',type=int,choices=[1],default=1)
    p.add_argument('--seed',type=int,default=1);p.add_argument('--output-dir')
    p.add_argument('--institutional-blocks')
    args=p.parse_args()
    args.search_profile=args.search_profile or (DEFAULT_SEARCH_PROFILE if args.variant=='M1-C' else 'complete_hint')
    out=Path(args.output_dir) if args.output_dir else default_output('soft')
    if out.exists() and any(out.iterdir()): p.error('Directorio no vacío: conservar evidencia anterior')
    out.mkdir(parents=True,exist_ok=True)
    print(f'SALIDA={out}',flush=True)
    record=start_run(out,'HARD+SOFT',vars(args))
    try:
        i=load(args.instance,args.placements,args.conflicts,args.policy,args.institutional_blocks)
        spec=prepare(i,args.lab_days)
        write_json(out/'especificacion_soft.json',spec)
        sources=list(Path('cpsat_hard').glob('*.py'))+list(Path('cpsat_soft').glob('*.py'))
        write_json(out/'manifiesto.json',{'entradas_sha256':i.hashes,
            'codigo_sha256':{str(f):hashlib.sha256(f.read_bytes()).hexdigest() for f in sources},
            'tesis_sha256':next((hashlib.sha256(f.read_bytes()).hexdigest() for f in sorted(Path('.').glob('Tesis_*.docx'))),None)})
        construction_start=time.perf_counter()
        b=build(i,args.scenario)
        hard_size={'variables':len(b.model.proto.variables),'restricciones':len(b.model.proto.constraints)}
        if args.baseline:
            path=Path(args.baseline);baseline=json.loads(path.read_text(encoding='utf-8'))
            if baseline.get('fuentes_sha256')!=i.hashes: raise ValueError('Baseline: hashes de entradas distintos')
            validation=validate(i,baseline,args.scenario)
            if not validation['cumple_reglas_con_datos_disponibles']: raise ValueError('Baseline no cumple HARD vigente')
            write_json(out/'baseline_metricas_reales.json',evaluate(i,baseline,spec))
            write_json(out/'baseline_fuente.json',{'archivo':str(path.resolve()),'sha256':hashlib.sha256(path.read_bytes()).hexdigest()})
            add_hint(i,b,baseline)
        penalties=add(i,b,spec,variant=args.variant)
        construction=time.perf_counter()-construction_start
        model_size={'variables':len(b.model.proto.variables),'restricciones':len(b.model.proto.constraints)}
        b.model.export_to_file(str(out/'modelo.pbtxt'))
        result,metrics=solve(i,b,penalties,spec,args.time,args.workers,args.seed,variant=args.variant,search_profile=args.search_profile)
        result.update(tiempo_construccion_s=construction,tamano_hard=hard_size,tamano_modelo=model_size)
        result.update(run_id=record['run_id'],generado_UTC=record['creado_UTC'])
        result['escenario']=args.scenario
        if metrics is None:
            write_json(out/'resultado.json',result)
            write_json(out/'diagnostico.json',{'estado':result['estado'],
                'detalle':'No se exporta horario sin solución. Con --baseline existe testigo HARD real; revisar estado y estadísticas, no relajar restricciones.',
                'estadisticas_solver':result['estadisticas_solver']})
            print(f"ESTADO={result['estado']}; sin horario SOFT")
            finish_run(out,record,result,i)
            return 2
        hard_report=validate(i,result,args.scenario)
        result['validacion']=hard_report['estado']
        write_json(out/'resultado.json',result);write_json(out/'metricas_soft.json',metrics)
        write_json(out/'validacion_hard_independiente.json',hard_report)
        write_csv(out/'horario.csv',i,result['sesiones_elegidas'])
        write_fixed_csv(out/'horario_repeticiones_fijas.csv',result.get('sesiones_fijas_repeticion',[]))
        finish_run(out,record,result,i,hard_report,spec=spec)
        print(f"ESTADO={result['estado']} OBJETIVO={result['valor_objetivo']} COTA={result['mejor_cota']} BRECHA={result['brecha_relativa']} HARD={hard_report['estado']}",flush=True)
        return 0 if hard_report['cumple_reglas_con_datos_disponibles'] else 2
    except (ValueError,KeyError,TypeError,OSError) as e:
        finish_run(out,record,{'estado':'EXPORT_ERROR' if (out/'resultado.json').exists() else 'ERROR'})
        write_json(out/'error.json',{'estado':'ERROR','detalle':str(e)})
        print(f'ERROR: {e}',flush=True);return 2


if __name__=='__main__': raise SystemExit(main())
