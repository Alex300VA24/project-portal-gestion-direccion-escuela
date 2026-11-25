"""python -m exporters: vuelve a validar y exporta una solución existente."""
import argparse
import hashlib
import json
from pathlib import Path
from cpsat_hard.data import load
from .events import validated_events
from .runs import default_output,start_run,finish_run


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--instance',required=True);p.add_argument('--placements',required=True)
    p.add_argument('--policy');p.add_argument('--conflicts');p.add_argument('--schedule',required=True)
    p.add_argument('--spec',help='Obligatorio para resultados SOFT')
    p.add_argument('--output-dir')
    p.add_argument('--institutional-blocks')
    args=p.parse_args()
    out=Path(args.output_dir) if args.output_dir else default_output('reports')
    if out.exists() and any(out.iterdir()): p.error('La carpeta de salida debe estar vacía')
    out.mkdir(parents=True,exist_ok=True)
    record=start_run(out,'REEXPORTACION',vars(args))
    try:
        i=load(args.instance,args.placements,args.conflicts,args.policy,args.institutional_blocks)
        source=Path(args.schedule)
        result=json.loads(source.read_text(encoding='utf-8'))
        spec=json.loads(Path(args.spec).read_text(encoding='utf-8')) if args.spec else None
        record.update(operacion='REEXPORTACION',codigo_rol='SOLO_EXPORTADOR',
            fase='HARD+SOFT' if 'valor_objetivo' in result else 'HARD')
        record['origen_solucion']={'archivo':str(source.resolve()),
            'sha256':hashlib.sha256(source.read_bytes()).hexdigest(),
            'codigo_solver':'Consultar artefactos originales; no inferido del exportador'}
        _,report=validated_events(i,result)
        (out/'resultado.json').write_bytes(source.read_bytes())
        if args.spec: (out/'especificacion_soft.json').write_bytes(Path(args.spec).read_bytes())
        finish_run(out,record,result,i,report,spec=spec)
        print(f'XLSX VALIDADO: {out / "horario_optimizado.xlsx"}');return 0
    except (ValueError,OSError,KeyError,TypeError) as exc:
        finish_run(out,record,{'estado':'EXPORT_ERROR'})
        print(f'EXPORT_ERROR: {exc}');return 2


if __name__=='__main__': raise SystemExit(main())
