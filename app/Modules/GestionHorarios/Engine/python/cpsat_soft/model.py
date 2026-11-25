"""Explicit formulation dispatch; promotion controls the production default."""
from .original import events
from .official import DEFAULT_VARIANT, VARIANTS


def add(i, built, spec, *, variant=None):
    variant = variant or DEFAULT_VARIANT
    if variant not in VARIANTS:
        raise ValueError(f"Unknown SOFT variant: {variant}")
    if variant == 'M1-A':
        from .original import add as formulation
    else:
        from .compact import add as formulation
    return formulation(i, built, spec)
