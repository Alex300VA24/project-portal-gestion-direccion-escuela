"""Ayudas de búsqueda operativa; no definen ni relajan reglas académicas."""
from ortools.sat.python import cp_model


def fixed_assumptions_copy(model):
    copy = model.clone()
    literals = list(copy.proto.assumptions)
    copy.clear_assumptions()
    for literal in literals:
        variable = copy.get_bool_var_from_proto_index(literal if literal >= 0 else -literal-1)
        copy.add(variable == (1 if literal >= 0 else 0))
    return copy


def seed_model(i, built, current):
    """Mismo conjunto factible: supuestos fijados verdaderos, pistas sin fijar clases."""
    model = fixed_assumptions_copy(built.model)
    index = {(c.session_id,c.dia,c.inicio,c.fin,c.ambiente_id):j for j,c in enumerate(i.placements) if j in built.placements}
    blocked = {r['id'] for r in built.rejected}
    hints = 0
    for row in current:
        key = (row['sesion_motor'],row['dia'],row['inicio'],row['fin'],row['ambiente_id'] or '')
        j = index.get(key)
        if j is not None and i.placements[j].id not in blocked:
            model.add_hint(model.get_bool_var_from_proto_index(built.placements[j].index), 1)
            hints += 1
    return model, hints


def recover_seed(built, seed, seconds):
    """Reconstruir auxiliares SOFT de una solución HARD sin fingir óptimo global."""
    model = fixed_assumptions_copy(built.model)
    model.clear_hints()
    for variable in [*built.patterns.values(), *built.placements.values()]:
        model.add(model.get_bool_var_from_proto_index(variable.index) == seed.value(variable))
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = seconds
    solver.parameters.num_search_workers = 1
    solver.parameters.random_seed = 1
    status = solver.solve(model)
    return solver, status
