"""Parita' fra l'implementazione Python e quella PHP del punteggio.

I pesi sono duplicati in due linguaggi per necessita': il crawler analizza
offline, la dashboard calcola a ogni pagina. Questo test impedisce che le due
copie divergano in silenzio.
"""

import datetime as dt
import json
import pathlib
import shutil
import subprocess

import pytest

from osservatorio.analysis.footfall import punteggio_affluenza
from osservatorio.models import StoreFormat

DUMP_PHP = pathlib.Path(__file__).resolve().parents[2] / "dashboard" / "tests" / "footfall_dump.php"

pytestmark = pytest.mark.skipif(
    shutil.which("php") is None or not DUMP_PHP.exists(),
    reason="PHP non disponibile: parita' non verificabile",
)


def test_punteggi_identici_fra_php_e_python():
    uscita = subprocess.run(
        ["php", str(DUMP_PHP)], capture_output=True, text=True, timeout=60, check=True
    )
    da_php = json.loads(uscita.stdout)
    assert da_php, "lo script PHP non ha prodotto punteggi"

    inizio, fine = dt.date(2026, 9, 17), dt.date(2026, 9, 23)
    per_nome = {f.value: f for f in StoreFormat}

    divergenze = []
    for chiave, atteso in da_php.items():
        nome_tipologia, iso = chiave.split("|")
        tipologia = per_nome.get(nome_tipologia)  # 'ignota' -> None, come in PHP
        ottenuto = punteggio_affluenza(
            dt.date.fromisoformat(iso), tipologia, inizio, fine
        )
        if abs(ottenuto - atteso) > 0.05:
            divergenze.append(f"{chiave}: PHP {atteso} != Python {ottenuto}")

    assert not divergenze, "I pesi sono divergenti:\n" + "\n".join(divergenze)
