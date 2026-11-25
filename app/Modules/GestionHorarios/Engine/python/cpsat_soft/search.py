"""Búsquedas experimentales; completar un hint nunca fija el modelo principal."""
import ctypes
import os
import sys
import time
from ortools.sat.python import cp_model

PROFILES = ('complete_hint', 'portfolio', 'lns')


def complete_hint(model, budget, seed):
    started = time.perf_counter()
    record = {'estado': 'SIN_HINT', 'tiempo_s': 0.0, 'variables_completadas': 0}
    if not model.proto.solution_hint.vars:
        return record
    clone = model.clone()
    clone.clear_objective()
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = budget
    solver.parameters.num_search_workers = 1
    solver.parameters.random_seed = seed
    solver.parameters.fix_variables_to_their_hinted_value = True
    status = solver.solve(clone)
    record.update(estado=solver.status_name(status), tiempo_s=time.perf_counter()-started)
    if status in (cp_model.FEASIBLE, cp_model.OPTIMAL):
        values = [solver.value(clone.get_int_var_from_proto_index(j)) for j in range(len(clone.proto.variables))]
        model.clear_hints()
        for j, value in enumerate(values):
            model.add_hint(model.get_int_var_from_proto_index(j), value)
        record['variables_completadas'] = len(values)
    return record


def configure(solver, profile):
    if profile not in PROFILES:
        raise ValueError(f'Perfil de búsqueda desconocido: {profile}')
    if profile == 'complete_hint':
        solver.parameters.search_branching = cp_model.HINT_SEARCH
    elif profile == 'portfolio':
        solver.parameters.search_branching = cp_model.PORTFOLIO_SEARCH
    elif profile == 'lns':
        solver.parameters.use_lns_only = True


def peak_memory():
    """Pico RSS del proceso aislado, incluida construcción; no una estimación."""
    if os.name == 'nt':
        class Counters(ctypes.Structure):
            _fields_ = [('cb',ctypes.c_ulong), ('PageFaultCount',ctypes.c_ulong)] + [
                (k,ctypes.c_size_t) for k in ('PeakWorkingSetSize','WorkingSetSize','QuotaPeakPagedPoolUsage',
                    'QuotaPagedPoolUsage','QuotaPeakNonPagedPoolUsage','QuotaNonPagedPoolUsage','PagefileUsage','PeakPagefileUsage')]
        counters = Counters()
        counters.cb = ctypes.sizeof(counters)
        kernel = ctypes.WinDLL('kernel32',use_last_error=True)
        kernel.GetCurrentProcess.restype = ctypes.c_void_p
        psapi = ctypes.WinDLL('psapi',use_last_error=True)
        psapi.GetProcessMemoryInfo.argtypes = [ctypes.c_void_p,ctypes.POINTER(Counters),ctypes.c_ulong]
        if psapi.GetProcessMemoryInfo(kernel.GetCurrentProcess(),ctypes.byref(counters),counters.cb):
            return {'bytes': counters.PeakWorkingSetSize, 'metodo': 'Windows PeakWorkingSetSize', 'alcance': 'PROCESO_INCLUIDA_CONSTRUCCION'}
    else:
        import resource
        value = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss
        return {'bytes': int(value*(1 if sys.platform == 'darwin' else 1024)),
                'metodo': 'getrusage(RUSAGE_SELF).ru_maxrss', 'alcance': 'PROCESO_INCLUIDA_CONSTRUCCION'}
    return {'bytes': None, 'metodo': None, 'alcance': 'NO_DISPONIBLE'}
