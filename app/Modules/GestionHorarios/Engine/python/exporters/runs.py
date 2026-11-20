"""Registro por UUID; compatibilidad con artefactos históricos del motor."""
import csv
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import platform
import subprocess
import uuid
import ortools
from cpsat_hard.exporting import write_json
from . import SCHEMA_VERSION
from .events import validated_events, validated_metrics, reservations, utilization
from .excel_timetables import export_excel, verify_workbook

ROOT = Path(__file__).resolve().parents[1]


def code_version():
    def git(*args):
        p = subprocess.run(['git','-C',str(ROOT),*args],capture_output=True,text=True)
        return p.stdout.strip() if p.returncode==0 else None
    sources = [p for folder in ('cpsat_hard','cpsat_soft','experimentos','exporters')
               for p in (ROOT/folder).glob('*.py')]
    return {'commit':git('rev-parse','HEAD'),'arbol_modificado':bool(git('status','--porcelain')),
        'sha256':{p.relative_to(ROOT).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(sources)}}


def default_output(phase):
    stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S_%fZ')
    return Path('results/optimization')/phase.lower()/f'run_{stamp}_{uuid.uuid4().hex[:8]}'


def start_run(out,phase,configuration):
    record = {'schema_version':SCHEMA_VERSION,'run_id':str(uuid.uuid4()),
        'creado_UTC':datetime.now(timezone.utc).isoformat(),'fase':phase,'estado':'RUNNING',
        'escenario':configuration.get('scenario'),'semilla':configuration.get('seed'),
        'limite_s':configuration.get('time'),'trabajadores':configuration.get('workers'),
        'configuracion':configuration,'codigo':code_version(),
        'versiones':{'python':platform.python_version(),'ortools':ortools.__version__,
            'pythonhashseed':os.environ.get('PYTHONHASHSEED','NO_FIJADO')},
        'aprobado':False,'fuentes_sha256':{},'archivos_sha256':{}}
    write_json(Path(out)/'metadata.json',record)
    return record


def finish_run(out,record,outcome,instance=None,report=None,*,spec=None):
    out = Path(out)
    parameters=outcome.get('parametros',{})
    record.update(semilla=parameters.get('random_seed',record['semilla']),
        limite_s=parameters.get('max_time_in_seconds',record['limite_s']),
        trabajadores=parameters.get('num_search_workers',record['trabajadores']))
    record.update(estado=outcome['estado'],escenario=outcome.get('escenario',record['escenario']),
        valor_objetivo=outcome.get('valor_objetivo'),mejor_cota=outcome.get('mejor_cota'),
        tiempo_solver_s=outcome.get('tiempo_solver_s'),tiempo_ejecucion_s=outcome.get('tiempo_ejecucion_s'),
        brecha_relativa=outcome.get('brecha_relativa'),fuentes_sha256=instance.hashes if instance else {},
        validacion_independiente=report['estado'] if report else None,
        n_actividades_programadas=report['n_actividades'] if report else None,
        horas_cubiertas=report['horas_programadas'] if report else None)
    record['n_actividades_entrada']=len(instance.activities) if instance else None
    record['conflictos_HARD_no_exceptuados']=(sum(len(r['violaciones']) for r in report['reglas'].values())
        +len(report['otras_violaciones'])) if report else None
    if report: write_json(out/'hard_validation.json',report)
    if instance:
        write_json(out/'instancia_hash.json',instance.hashes)
    valid = report and report['cumple_reglas_con_datos_disponibles'] and outcome['estado'] in ('FEASIBLE','OPTIMAL')
    if valid:
        events,_ = validated_events(instance,outcome)
        metrics = validated_metrics(instance,outcome,spec)
        write_json(out/'solution.json',{'schema_version':SCHEMA_VERSION,'run_id':record['run_id'],
            'periodo':instance.raw.get('metadata',{}).get('periodo'),'aprobado':False,'sesiones':events})
        write_json(out/'hard_validation.json',report)
        write_json(out/'metrics.json',metrics or {'disponible':False,'motivo':'Ejecución HARD sin evaluación SOFT',
            'actividades':report['n_actividades'],'horas':report['horas_programadas']})
        usage = utilization(instance,events,reservations(instance))
        for name,rows in (('horario_optimizado.csv',events),('utilizacion_ambientes.csv',usage)):
            if not rows: continue
            with (out/name).open('w',encoding='utf-8-sig',newline='') as f:
                w = csv.DictWriter(f,fieldnames=list(rows[0]));w.writeheader()
                w.writerows({k:json.dumps(v,ensure_ascii=False) if isinstance(v,(list,tuple)) else v
                             for k,v in e.items()} for e in rows)
        manifest = export_excel(instance,outcome,out/'horario_optimizado.xlsx',spec=spec)
        write_json(out/'excel_manifest.json',manifest)
        write_json(out/'excel_validation.json',verify_workbook(instance,outcome,out/'horario_optimizado.xlsx',manifest))
    (out/'README.md').write_text(f"# Ejecución {record['run_id']}\n\n"
        f"Fase: {record['fase']}. Escenario: {record['escenario']}. Estado: {record['estado']}.\n\n"
        f"Validación: {record['validacion_independiente'] or 'No hay solución validada'}. "
        "El horario es una propuesta; el motor no otorga aprobación institucional.\n\n"
        "metadata.json registra configuración, código, versiones y hashes. resultado.json conserva "
        "la respuesta nativa del motor. Un XLSX solo se genera para soluciones que superan la validación "
        "independiente; solution.json contiene las sesiones físicas, incluidas repeticiones fijas.\n",
        encoding='utf-8')
    record['fin_UTC'] = datetime.now(timezone.utc).isoformat()
    record['archivos_sha256'] = {p.relative_to(out).as_posix():hashlib.sha256(p.read_bytes()).hexdigest()
        for p in sorted(out.rglob('*')) if p.is_file() and p.name!='metadata.json'}
    write_json(out/'metadata.json',record)
