"""Registro degli adapter: dalla configurazione alla classe, senza if/else.

Aggiungere una catena non deve richiedere modifiche al codice di pipeline.
"""

from __future__ import annotations

from osservatorio.adapters.base import ChainAdapter

_REGISTRY: dict[str, type[ChainAdapter]] = {}


def register(cls: type[ChainAdapter]) -> type[ChainAdapter]:
    """Decoratore di registrazione da apporre a ogni adapter."""
    slug = getattr(cls, "slug", None)
    if not slug:
        raise ValueError(f"{cls.__name__} non dichiara l'attributo slug")
    existing = _REGISTRY.get(slug)
    if existing is not None and existing is not cls:
        raise ValueError(
            f"slug '{slug}' gia' registrato da {existing.__name__}"
        )
    _REGISTRY[slug] = cls
    return cls


def get(slug: str) -> type[ChainAdapter]:
    """Risolve uno slug di configurazione nella sua classe adapter."""
    try:
        return _REGISTRY[slug]
    except KeyError:
        disponibili = ", ".join(sorted(_REGISTRY)) or "nessuno"
        raise LookupError(
            f"nessun adapter registrato per '{slug}' (disponibili: {disponibili})"
        ) from None


def available() -> tuple[str, ...]:
    return tuple(sorted(_REGISTRY))


def clear() -> None:
    """Solo per i test."""
    _REGISTRY.clear()
