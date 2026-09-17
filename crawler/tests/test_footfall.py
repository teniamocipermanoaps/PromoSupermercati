"""Test della stima di affluenza per i banchetti solidali."""

import datetime as dt

import pytest

from osservatorio.analysis.footfall import (
    giorni_consigliati,
    punteggio_affluenza,
)
from osservatorio.models import StoreFormat

# Campagna di esempio: giovedi 18 settembre 2026 -> mercoledi 24 settembre.
INIZIO = dt.date(2026, 9, 17)   # giovedi
FINE = dt.date(2026, 9, 23)     # mercoledi
SABATO = dt.date(2026, 9, 19)
MARTEDI = dt.date(2026, 9, 22)


def test_ipermercato_batte_superette_a_parita_di_giorno():
    iper = punteggio_affluenza(SABATO, StoreFormat.IPERMERCATO, INIZIO, FINE)
    superette = punteggio_affluenza(SABATO, StoreFormat.SUPERETTE, INIZIO, FINE)
    assert iper > superette


def test_sabato_batte_il_martedi_nello_stesso_negozio():
    sabato = punteggio_affluenza(SABATO, StoreFormat.SUPERMERCATO, INIZIO, FINE)
    martedi = punteggio_affluenza(MARTEDI, StoreFormat.SUPERMERCATO, INIZIO, FINE)
    assert sabato > martedi


def test_giorno_con_promozione_batte_giorno_senza():
    con = punteggio_affluenza(SABATO, StoreFormat.SUPERMERCATO, INIZIO, FINE)
    senza = punteggio_affluenza(SABATO, StoreFormat.SUPERMERCATO, None, None)
    assert con > senza


def test_giorno_fuori_dalla_finestra_vale_come_senza_promozione():
    fuori = dt.date(2026, 10, 10)
    assert punteggio_affluenza(fuori, StoreFormat.SUPERMERCATO, INIZIO, FINE) == (
        punteggio_affluenza(fuori, StoreFormat.SUPERMERCATO, None, None)
    )


def test_tipologia_ignota_non_fa_esplodere_il_calcolo():
    assert punteggio_affluenza(SABATO, None, INIZIO, FINE) > 0


def test_punteggio_entro_i_limiti():
    for tipologia in list(StoreFormat) + [None]:
        p = punteggio_affluenza(SABATO, tipologia, INIZIO, FINE)
        assert 0 < p <= 100


def test_giorni_consigliati_mette_il_sabato_per_primo():
    consigli = giorni_consigliati(INIZIO, FINE, StoreFormat.IPERMERCATO, quanti=3)
    assert consigli[0].data == SABATO
    assert consigli[0].giorno_settimana == "sabato"
    assert len(consigli) == 3


def test_giorni_consigliati_restano_dentro_la_finestra():
    for consiglio in giorni_consigliati(INIZIO, FINE, StoreFormat.SUPERMERCATO, quanti=7):
        assert INIZIO <= consiglio.data <= FINE


def test_giorni_consigliati_ordinati_per_punteggio_decrescente():
    consigli = giorni_consigliati(INIZIO, FINE, StoreFormat.SUPERSTORE, quanti=7)
    punteggi = [c.punteggio for c in consigli]
    assert punteggi == sorted(punteggi, reverse=True)


def test_fase_riconosciuta():
    consigli = giorni_consigliati(INIZIO, FINE, StoreFormat.IPERMERCATO, quanti=7)
    fasi = {c.data: c.fase for c in consigli}
    assert fasi[INIZIO] == "lancio campagna"
    assert fasi[FINE] == "chiusura campagna"


def test_finestra_invertita_rifiutata():
    with pytest.raises(ValueError, match="precede"):
        giorni_consigliati(FINE, INIZIO, StoreFormat.IPERMERCATO)


def test_quanti_non_valido_rifiutato():
    with pytest.raises(ValueError, match="almeno un giorno"):
        giorni_consigliati(INIZIO, FINE, StoreFormat.IPERMERCATO, quanti=0)


def test_campagna_di_un_solo_giorno():
    unico = giorni_consigliati(SABATO, SABATO, StoreFormat.IPERMERCATO, quanti=3)
    assert len(unico) == 1
    assert unico[0].data == SABATO
