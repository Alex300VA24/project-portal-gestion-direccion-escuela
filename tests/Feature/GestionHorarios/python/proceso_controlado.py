import json
import os
import sys
import time

modo = json.load(sys.stdin).get('modo')
if modo == 'timeout':
    if os.environ.get('HORARIOS_TIMEOUT_READY'):
        with open(os.environ['HORARIOS_TIMEOUT_READY'], 'w', encoding='utf-8') as ready:
            ready.write('iniciado')
    time.sleep(3)
    if os.environ.get('HORARIOS_TIMEOUT_MARKER'):
        with open(os.environ['HORARIOS_TIMEOUT_MARKER'], 'w', encoding='utf-8') as marker:
            marker.write('proceso aun activo')
elif modo == 'error':
    print('fallo controlado', file=sys.stderr)
    sys.exit(2)
elif modo == 'incompleto':
    print('{"contrato":')
elif modo == 'invalido':
    print(json.dumps({'contrato': 'OTRO', 'estado': 'OPTIMAL'}))
else:
    print(json.dumps({'contrato': 'HORARIOS_RESULTADO_V1', 'estado': modo}))
