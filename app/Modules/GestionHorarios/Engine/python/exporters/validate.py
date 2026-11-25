"""Reabre un XLSX y comprueba su contenido contra la solución validada."""
import argparse
import json
from pathlib import Path
from zipfile import BadZipFile
from openpyxl.utils.exceptions import InvalidFileException
from cpsat_hard.data import load
from cpsat_hard.exporting import write_json
from .events import validated_metrics
from .excel_timetables import verify_workbook


def main():
    p=argparse.ArgumentParser(description=__doc__)
    for flag in ('instance','placements','schedule','workbook','manifest','output'):
        p.add_argument('--'+flag,required=True)
    p.add_argument('--policy');p.add_argument('--conflicts');p.add_argument('--spec')
    p.add_argument('--institutional-blocks')
    a=p.parse_args()
    try:
        i=load(a.instance,a.placements,a.conflicts,a.policy,a.institutional_blocks)
        def read(path): return json.loads(Path(path).read_text(encoding='utf-8'))
        outcome=read(a.schedule)
        validated_metrics(i,outcome,read(a.spec) if a.spec else None)
        report=verify_workbook(i,outcome,a.workbook,read(a.manifest))
    except (ValueError,OSError,KeyError,TypeError,BadZipFile,InvalidFileException) as exc:
        report={'estado':'INVALIDO','detalle':str(exc)}
    Path(a.output).parent.mkdir(parents=True,exist_ok=True);write_json(a.output,report)
    print(report['estado']);return 0 if report['estado']=='VALIDO' else 2


if __name__=='__main__': raise SystemExit(main())
