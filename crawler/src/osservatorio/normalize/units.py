"""Normalizzazione di quantita' e prezzi unitari.

E' il cuore del confronto fra catene: due offerte sono paragonabili solo se
ridotte allo stesso denominatore (euro al chilo o al litro), e solo se si tiene
conto della meccanica promozionale (un 3x2 non costa quanto il prezzo esposto).

Tutto qui dentro e' puro: nessun accesso a rete o database, quindi e'
interamente testabile offline.
"""

from __future__ import annotations

import dataclasses
import re

from osservatorio.models import PromoType, Unit

# Alias italiani -> (unita' canonica, fattore di conversione verso quell'unita').
_UNIT_ALIASES: dict[str, tuple[Unit, float]] = {
    "g": (Unit.G, 1.0),
    "gr": (Unit.G, 1.0),
    "grammi": (Unit.G, 1.0),
    "grammo": (Unit.G, 1.0),
    "kg": (Unit.KG, 1.0),
    "chilo": (Unit.KG, 1.0),
    "chili": (Unit.KG, 1.0),
    "ml": (Unit.ML, 1.0),
    "cl": (Unit.ML, 10.0),
    "dl": (Unit.ML, 100.0),
    "l": (Unit.L, 1.0),
    "lt": (Unit.L, 1.0),
    "litro": (Unit.L, 1.0),
    "litri": (Unit.L, 1.0),
    "pz": (Unit.PZ, 1.0),
    "pezzo": (Unit.PZ, 1.0),
    "pezzi": (Unit.PZ, 1.0),
    "conf": (Unit.CONF, 1.0),
    "confezione": (Unit.CONF, 1.0),
    "conf.": (Unit.CONF, 1.0),
}

_NUM = r"\d+(?:[.,]\d+)?"

# "500 g", "2x250 g", "1,5 l"
_VALUE_FIRST = re.compile(
    rf"(?:(?P<mult>\d+)\s*[x×]\s*)?(?P<num>{_NUM})\s*(?P<unit>[a-zà.]+)", re.IGNORECASE
)
# "gr 400", "cl 75", "conf. 6"
_UNIT_FIRST = re.compile(rf"(?P<unit>[a-zà]+\.?)\s*(?P<num>{_NUM})", re.IGNORECASE)
# moltiplicatore in coda: "1 l x 6"
_TRAILING_MULT = re.compile(r"^\s*[x×]\s*(?P<mult>\d+)", re.IGNORECASE)


@dataclasses.dataclass(frozen=True, slots=True)
class Quantity:
    """Quantita' totale della confezione, gia' moltiplicata per il multipack."""

    value: float
    unit: Unit
    multiplier: int = 1

    @property
    def kilograms(self) -> float | None:
        if self.unit is Unit.KG:
            return self.value
        if self.unit is Unit.G:
            return self.value / 1000.0
        return None

    @property
    def liters(self) -> float | None:
        if self.unit is Unit.L:
            return self.value
        if self.unit is Unit.ML:
            return self.value / 1000.0
        return None


def _to_float(raw: str) -> float:
    """Converte un numero scritto all'italiana: la virgola e' decimale."""
    return float(raw.replace(".", "").replace(",", ".") if "," in raw else raw)


def _lookup(token: str) -> tuple[Unit, float] | None:
    return _UNIT_ALIASES.get(token.lower().rstrip("."))


def parse_quantity(text: str | None) -> Quantity | None:
    """Estrae la quantita' da una descrizione di prodotto.

    Riconosce "500 g", "1,5 L", "2x250 g", "gr 400", "cl 75", "1 l x 6".
    Restituisce None se nel testo non c'e' una quantita' interpretabile:
    meglio nessun dato che un prezzo al chilo inventato.
    """
    if not text:
        return None

    for match in _VALUE_FIRST.finditer(text):
        found = _lookup(match.group("unit"))
        if found is None:
            continue
        unit, factor = found
        multiplier = int(match.group("mult") or 1)

        tail = _TRAILING_MULT.match(text[match.end() :])
        if tail:
            multiplier *= int(tail.group("mult"))

        value = _to_float(match.group("num")) * factor * multiplier
        return Quantity(value=value, unit=unit, multiplier=multiplier)

    for match in _UNIT_FIRST.finditer(text):
        found = _lookup(match.group("unit"))
        if found is None:
            continue
        unit, factor = found
        return Quantity(value=_to_float(match.group("num")) * factor, unit=unit)

    return None


def effective_price(price: float, promo_type: PromoType) -> float:
    """Prezzo realmente pagato per unita', data la meccanica promozionale.

    Un 3x2 esposto a 2,00 costa in realta' 1,33 a pezzo: senza questa
    correzione il confronto fra catene premia chi usa i multibuy.
    """
    if promo_type is PromoType.TRE_X_DUE:
        return price * 2 / 3
    if promo_type is PromoType.DUE_X_UNO:
        return price / 2
    return price


def price_per_kg(price: float, quantity: Quantity | None) -> float | None:
    """Euro al chilo, o None se la quantita' non e' espressa in peso."""
    if quantity is None:
        return None
    kg = quantity.kilograms
    if not kg:
        return None
    return round(price / kg, 4)


def price_per_liter(price: float, quantity: Quantity | None) -> float | None:
    """Euro al litro, o None se la quantita' non e' espressa in volume."""
    if quantity is None:
        return None
    liters = quantity.liters
    if not liters:
        return None
    return round(price / liters, 4)


def discount_percent(price: float, price_original: float | None) -> float | None:
    """Sconto percentuale, o None se manca il prezzo pieno."""
    if not price_original or price_original <= 0:
        return None
    return round((price_original - price) / price_original * 100, 2)
