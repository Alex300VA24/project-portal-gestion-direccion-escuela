"""python -m cpsat_hard --help"""
import argparse
from pathlib import Path
import sys
from .data import InputError, load
from .exporting import write_json, write_csv,write_fixed_csv
from .precheck import inspect_curriculum, limitations
from .policy import policy_metadata
from exporters.runs import default_output,start_run,finish_run


def main(argv=None):
    p=argparse.ArgumentParser(description='Motor HARD sin optimización SOFT. No certifica datos institucionales ausentes.')
    p.add_argument('--instance',default='instancia_cp_sat_2026II.json')
    p.add_argument('--placements',default='colocaciones_candidatas.csv')
    p.add_argument('--conflicts',help='JSON adicional de pares y declaración de cobertura; no altera fuente')
    p.add_argument('--policy',help='Excepciones explícitas del periodo; omitir conserva HARD original')
    p.add_argument('--scenario',choices=['A','B'],help='Predeterminado: el escenario declarado en metadata')
    p.add_argument('--time',type=float,default=300)
    p.add_argument('--workers',type=int,choices=[1],default=1)
    p.add_argument('--seed',type=int,default=1)
    p.add_argument('--output-dir',help='Predeterminado: carpeta única en results/optimization/hard')
    p.add_argument('--export-model',action='store_true')
    p.add_argument('--require-complete',action='store_true',help='Devuelve código 3 si falta acreditación completa H01-H22')
    p.add_argument('--institutional-blocks')
    args=p.parse_args(argv)
    out=Path(args.output_dir) if args.output_dir else default_output('hard')
    # Evitar destruir resultados de una corrida anterior y mezclar sus artefactos.
    if out.exists() and any(out.iterdir()):
        p.error('Directorio de salida no vacío: elija otro para conservar evidencia')
    out.mkdir(parents=True,exist_ok=True)
    print(f'SALIDA={out}',flush=True)
    record=start_run(out,'HARD',vars(args))
    try:
        i=load(args.instance,args.placements,args.conflicts,args.policy,args.institutional_blocks)
        if i.raw.get('excepciones_periodo'):
            write_json(out/'politica_aplicada.json',i.raw['excepciones_periodo'])
        declared=i.raw.get('metadata',{}).get('escenario')
        scenario=args.scenario or {'B_OBSERVADO_M0':'B','A_ESTRICTO':'A'}.get(declared)
        if scenario is None: raise InputError('Escenario desconocido: indicar --scenario A o B')
        if args.time<=0 or args.seed<0: raise InputError('Tiempo/semilla inválidos')
        if i.raw.get('politica_repeticion_fija'):
            from .fixed_repetitions import analyze
            write_json(out/'repeticiones_fijas_entrada.json',analyze(i))
            write_fixed_csv(out/'repeticiones_fijas_entrada.csv',i.raw['repeticiones_fijas_m0'])
        errors=inspect_curriculum(i)
        from .institutional_blocks import inspect as inspect_blocks
        write_json(out/'bloqueos_institucionales.json',inspect_blocks(i,scenario))
        notes=limitations(i)
        write_json(out/'precheck.json',{'errores':errors,'limitaciones':notes,'politica_conflictos':policy_metadata(i),'escenario':scenario,'escenario_fuente':declared,'fuentes_sha256':i.hashes})
        if errors: raise InputError(f'{len(errors)} errores curriculares; ver precheck.json')
        from .model import build
        from .runner import solve
        from .validation import validate
        built=build(i,scenario)
        write_json(out/'cotas_ciclos.json',built.cycle_bounds)
        write_json(out/'dominios.json',{'rechazos':built.rejected,'n_entrada':len(i.placements)})
        outcome,diagnostic=solve(i,built,args.time,args.workers,args.seed)
        outcome.update(run_id=record['run_id'],generado_UTC=record['creado_UTC'])
        outcome['escenario']=scenario
        outcome['limitaciones']=notes
        if args.export_model or diagnostic:
            built.model.export_to_file(str(out/'modelo.pbtxt'))
            write_json(out/'supuestos.json',built.labels)
        if diagnostic:
            from .diagnostic_report import detailed,write_report
            write_report(out,detailed(i,built,outcome['estado'],scenario))
            write_json(out/'diagnostico.json',diagnostic)
            write_json(out/'resultado.json',outcome)
            finish_run(out,record,outcome,i)
            print(f"ESTADO={outcome['estado']}; diagnóstico: {out/'diagnostico.json'}")
            return 2
        report=validate(i,outcome,scenario)
        outcome['validacion']=report['estado']
        outcome['certificacion_completa_H01_H22']=report['certificacion_completa_H01_H22']
        write_json(out/'resultado.json',outcome)
        write_json(out/'validacion_independiente.json',report)
        write_csv(out/'horario.csv',i,outcome['sesiones_elegidas'])
        if outcome.get('sesiones_fijas_repeticion'):
            write_fixed_csv(out/'horario_repeticiones_fijas.csv',outcome['sesiones_fijas_repeticion'])
        finish_run(out,record,outcome,i,report)
        print(f"ESTADO={outcome['estado']} VALIDACION={report['estado']} ACTIVIDADES={report['n_actividades']} SESIONES={report['n_sesiones']} HORAS={report['horas_programadas']} TIEMPO={outcome['tiempo_solver_s']:.3f}s")
        for note in report['limitaciones']: print(f"LIMITACION {note['regla']}: {note['detalle']}")
        if not report['cumple_reglas_con_datos_disponibles']: return 2
        return 3 if args.require_complete and not report['certificacion_completa_H01_H22'] else 0
    except (InputError,OSError,ValueError,KeyError,TypeError) as exc:
        finish_run(out,record,{'estado':'EXPORT_ERROR' if (out/'resultado.json').exists() else 'INPUT_INVALID'})
        write_json(out/'diagnostico_entrada.json',{'estado':'INPUT_INVALID','detalle':str(exc)})
        print(f'INPUT_INVALID: {exc}',file=sys.stderr)
        return 2


if __name__=='__main__':
    raise SystemExit(main())
