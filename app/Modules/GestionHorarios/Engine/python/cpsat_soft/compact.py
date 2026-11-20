"""M1-B: auxiliares compartidos, equivalencias bidireccionales y HARD intacto."""
from collections import defaultdict
from .original import events


class Booleans:
    def __init__(self, model):
        self.model = model
        self.cache = {}
        self.zero = model.new_constant(0)
        self.one = model.new_constant(1)

    def disjunction(self, values, name):
        unique = {v.index: v for v in values if v.index != self.zero.index}
        if self.one.index in unique:
            return self.one
        if not unique:
            return self.zero
        if len(unique) == 1:
            return next(iter(unique.values()))
        key = ('OR', tuple(sorted(unique)))
        if key not in self.cache:
            b = self.model.new_bool_var(name)
            self.model.add_max_equality(b, [unique[k] for k in key[1]])
            self.cache[key] = b
        return self.cache[key]

    def conjunction(self, values, name):
        unique = {v.index: v for v in values if v.index != self.one.index}
        if self.zero.index in unique or any(-k-1 in unique for k in unique):
            return self.zero
        if not unique:
            return self.one
        if len(unique) == 1:
            return next(iter(unique.values()))
        key = ('AND', tuple(sorted(unique)))
        if key not in self.cache:
            b = self.model.new_bool_var(name)
            self.model.add_bool_and(list(unique.values())).only_enforce_if(b)
            self.model.add_bool_or([v.Not() for v in unique.values()]).only_enforce_if(b.Not())
            self.cache[key] = b
        return self.cache[key]


def add(i, built, spec):
    m = built.model
    logic = Booleans(m)
    bounds = spec['cotas']
    terms = defaultdict(list)
    occupied, sessions, durations, loads = (defaultdict(list) for _ in range(4))
    groups, counted = spec['grupo_por_actividad'], set(spec['grupos_jornada'])
    rejected = {r['id'] for r in built.rejected}
    for e, v in events(i, built):
        # Variables ya fijadas a cero por HARD, cuyos supuestos se conservan.
        if e['id'] in rejected:
            continue
        aid, day = e['aid'], e['dia']
        duration, group = e['fin']-e['inicio'], groups[aid]
        pref = spec['preferencia_por_actividad'][aid]
        if pref:
            outside = sum((h < 13) != (pref == 'MANANA') for h in range(e['inicio'], e['fin']))
            if outside:
                terms['S01'].append(outside*v)
        if group in counted:
            sessions[group, day].append(v)
            durations[group, day].append(duration*v)
            for h in range(e['inicio'], e['fin']):
                occupied[group, day, h].append(v)
        if e['tipo'] == 'LAB':
            if day not in spec['dias_laboratorio']:
                raise ValueError('Días LAB insuficientes: existe una sesión HARD admisible fuera del conjunto')
            loads[day].append(duration*v)
        rid = e['ambiente_id']
        if rid and i.rooms[rid]['tipo'] == 'AULA':
            cost = (i.rooms[rid]['capacidad']-e['estudiantes'])*duration
            if cost:
                terms['S06'].append(cost*v)
    hours = spec['horas']
    for g in spec['grupos_jornada']:
        for day in spec['dias']:
            if not sessions[g, day]:
                continue
            name = f'{g}_{day}'
            occ = [logic.disjunction(occupied[g, day, h], f'oc_{name}_{h}') for h in hours]
            morning = logic.disjunction([v for h,v in zip(hours,occ) if h < 13], 'am_'+name)
            afternoon = logic.disjunction([v for h,v in zip(hours,occ) if h >= 14], 'pm_'+name)
            terms['S02'].append(logic.conjunction([morning, afternoon], 'mixta_'+name))
            prefixes, suffixes = [logic.zero], [logic.zero]*(len(hours)+1)
            for j, v in enumerate(occ):
                prefixes.append(logic.disjunction([prefixes[-1], v], f'antes_{name}_{j+1}'))
            for j in reversed(range(len(hours))):
                suffixes[j] = logic.disjunction([occ[j], suffixes[j+1]], f'despues_{name}_{j}')
            for j, v in enumerate(occ):
                terms['S03'].append(logic.conjunction([prefixes[j], suffixes[j+1], v.Not()], f'hueco_{name}_{j}'))
            one = m.new_bool_var('una_'+name)
            n = sum(sessions[g, day])
            m.add(n == 1).only_enforce_if(one)
            m.add(n != 1).only_enforce_if(one.Not())
            short = m.new_bool_var('corta_'+name)
            duration = sum(durations[g, day])
            m.add(duration <= 2).only_enforce_if(short)
            m.add(duration >= 3).only_enforce_if(short.Not())
            terms['S04'] += [one, logic.conjunction([one, short], 'aislada_corta_'+name)]
    daily = []
    for day in spec['dias_laboratorio']:
        if not loads[day]:
            daily.append(logic.zero)
        else:
            u = m.new_int_var(0, bounds['S05'], 'lab_'+day)
            m.add(u == sum(loads[day]))
            daily.append(u)
    high = m.new_int_var(0, bounds['S05'], 'lab_max')
    low = m.new_int_var(0, bounds['S05'], 'lab_min')
    m.add_max_equality(high, daily)
    m.add_min_equality(low, daily)
    terms['S05'] = [high-low]
    penalties = {}
    for k, bound in bounds.items():
        penalties[k] = m.new_int_var(0, bound, 'penalizacion_'+k)
        m.add(penalties[k] == sum(terms[k]))
    m.minimize(sum(spec['normalizacion']['coeficientes'][k]*v for k,v in penalties.items()))
    error = m.validate()
    if error:
        raise ValueError(error)
    return penalties
