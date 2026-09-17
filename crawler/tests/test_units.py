"""Test della normalizzazione: stringhe reali da volantini italiani."""

import pytest

from osservatorio.models import PromoType, Unit
from osservatorio.normalize.units import (
    Quantity,
    discount_percent,
    effective_price,
    parse_quantity,
    price_per_kg,
    price_per_liter,
)


@pytest.mark.parametrize(
    "text, value, unit",
    [
        ("Prosciutto crudo 100 g", 100.0, Unit.G),
        ("Pasta di semola 500g", 500.0, Unit.G),
        ("Parmigiano Reggiano 1 kg", 1.0, Unit.KG),
        ("Latte intero 1,5 l", 1.5, Unit.L),
        ("Olio extravergine 750 ml", 750.0, Unit.ML),
        ("Vino Chianti 75 cl", 750.0, Unit.ML),
        ("Passata di pomodoro gr 700", 700.0, Unit.G),
        ("Detersivo lavatrice 30 pz", 30.0, Unit.PZ),
    ],
)
def test_parse_quantita_semplici(text, value, unit):
    q = parse_quantity(text)
    assert q is not None, text
    assert q.value == pytest.approx(value)
    assert q.unit is unit


@pytest.mark.parametrize(
    "text, value, unit",
    [
        ("Yogurt 2x250 g", 500.0, Unit.G),
        ("Acqua naturale 1,5 l x 6", 9.0, Unit.L),
        ("Birra conf. 6x33 cl", 1980.0, Unit.ML),
    ],
)
def test_parse_multipack(text, value, unit):
    q = parse_quantity(text)
    assert q is not None, text
    assert q.value == pytest.approx(value)
    assert q.unit is unit


@pytest.mark.parametrize("text", [None, "", "Offerta imperdibile", "Sconto del 50%"])
def test_quantita_assente_restituisce_none(text):
    # Meglio nessun dato che un prezzo al chilo inventato.
    assert parse_quantity(text) is None


def test_prezzo_al_chilo():
    q = parse_quantity("Prosciutto crudo 100 g")
    assert price_per_kg(2.49, q) == pytest.approx(24.90)


def test_prezzo_al_chilo_da_multipack():
    q = parse_quantity("Yogurt 2x250 g")
    assert price_per_kg(1.99, q) == pytest.approx(3.98)


def test_prezzo_al_litro():
    q = parse_quantity("Vino Chianti 75 cl")
    assert price_per_liter(4.50, q) == pytest.approx(6.0)


def test_unita_incompatibili_non_producono_prezzo():
    # Un peso non ha un prezzo al litro, e i pezzi non hanno ne' l'uno ne' l'altro.
    peso = parse_quantity("Pasta 500 g")
    assert price_per_liter(1.29, peso) is None
    pezzi = parse_quantity("Detersivo 30 pz")
    assert price_per_kg(5.99, pezzi) is None
    assert price_per_kg(5.99, None) is None


def test_quantita_zero_non_divide():
    assert price_per_kg(1.0, Quantity(value=0.0, unit=Unit.G)) is None


@pytest.mark.parametrize(
    "promo, atteso",
    [
        (PromoType.SCONTO, 3.00),
        (PromoType.TRE_X_DUE, 2.00),
        (PromoType.DUE_X_UNO, 1.50),
        (PromoType.SOTTOCOSTO, 3.00),
    ],
)
def test_prezzo_effettivo_per_meccanica(promo, atteso):
    # Senza questa correzione il confronto fra catene premia chi usa i multibuy.
    assert effective_price(3.00, promo) == pytest.approx(atteso)


def test_sconto_percentuale():
    assert discount_percent(1.99, 3.98) == pytest.approx(50.0)
    assert discount_percent(1.99, None) is None
    assert discount_percent(1.99, 0) is None
