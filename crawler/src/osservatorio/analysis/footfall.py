"""Stima dell'affluenza di un punto vendita, giorno per giorno.

Serve a una domanda operativa sola: in quale giorno conviene chiedere il
banchetto solidale, perche' il negozio sara' piu' frequentato e la raccolta
rendera' di piu'.

I pesi qui sotto sono un'IPOTESI di partenza, non una misura. Vanno ricalibrati
sui dati veri di `stall_events` (raccolto per banchetto, affluenza percepita)
appena ce ne sono a sufficienza: e' esattamente per questo che quella tabella
registra il raccolto e se c'era una promozione attiva.
"""

from __future__ import annotations

import dataclasses
import datetime as dt
import decimal

from osservatorio.models import StoreFormat

#: Quanto pesa la tipologia di punto vendita sul transito di persone.
PESO_TIPOLOGIA: dict[StoreFormat, float] = {
    StoreFormat.IPERMERCATO: 1.00,
    StoreFormat.SUPERSTORE: 0.85,
    StoreFormat.DISCOUNT: 0.70,
    StoreFormat.SUPERMERCATO: 0.65,
    StoreFormat.CASH_AND_CARRY: 0.40,
    StoreFormat.SUPERETTE: 0.35,
}
PESO_TIPOLOGIA_IGNOTA = 0.50

#: Giorno della settimana, 0 = lunedi'. Il sabato resta il giorno della spesa.
PESO_GIORNO: dict[int, float] = {
    0: 0.55, 1: 0.55, 2: 0.60, 3: 0.65, 4: 0.85, 5: 1.00, 6: 0.60,
}

#: Fase della campagna promozionale.
PESO_LANCIO = 1.00        # primi giorni: il volantino e' appena uscito
PESO_CENTRALE = 0.80
PESO_CHIUSURA = 0.90      # ultimi giorni: effetto ultima occasione
PESO_SENZA_PROMO = 0.55   # giorno senza campagna attiva

GIORNI_LANCIO = 3
GIORNI_CHIUSURA = 2


@dataclasses.dataclass(frozen=True, slots=True)
class GiornoConsigliato:
    """Un giorno candidato per il banchetto, con il perche'."""

    data: dt.date
    punteggio: float
    fase: str

    @property
    def giorno_settimana(self) -> str:
        nomi = ["lunedi", "martedi", "mercoledi", "giovedi", "venerdi", "sabato", "domenica"]
        return nomi[self.data.weekday()]


def _peso_fase(giorno: dt.date, inizio: dt.date | None, fine: dt.date | None) -> tuple[float, str]:
    if inizio is None or fine is None or not (inizio <= giorno <= fine):
        return PESO_SENZA_PROMO, "nessuna promozione"
    if (giorno - inizio).days < GIORNI_LANCIO:
        return PESO_LANCIO, "lancio campagna"
    if (fine - giorno).days < GIORNI_CHIUSURA:
        return PESO_CHIUSURA, "chiusura campagna"
    return PESO_CENTRALE, "campagna in corso"


def _arrotonda(valore: float) -> float:
    """Arrotonda a un decimale con la regola del mezzo per eccesso.

    round() di Python arrotonda al pari (55.25 -> 55.2), PHP per eccesso
    (55.3). Senza questa uniformita' il crawler e la dashboard mostrerebbero
    punteggi diversi per lo stesso giorno: la parita' e' verificata da
    crawler/tests/test_footfall_parity.py.
    """
    return float(
        decimal.Decimal(str(valore)).quantize(
            decimal.Decimal("0.1"), rounding=decimal.ROUND_HALF_UP
        )
    )


def punteggio_affluenza(
    giorno: dt.date,
    tipologia: StoreFormat | None = None,
    inizio_campagna: dt.date | None = None,
    fine_campagna: dt.date | None = None,
) -> float:
    """Punteggio 0-100 dell'affluenza attesa in un dato giorno.

    E' una stima comparativa: serve a ordinare i giorni e i punti vendita fra
    loro, non a prevedere quante persone entreranno davvero.
    """
    peso_tipo = PESO_TIPOLOGIA.get(tipologia, PESO_TIPOLOGIA_IGNOTA)
    peso_giorno = PESO_GIORNO[giorno.weekday()]
    peso_fase, _ = _peso_fase(giorno, inizio_campagna, fine_campagna)
    return _arrotonda(100 * peso_tipo * peso_giorno * peso_fase)


def giorni_consigliati(
    inizio_campagna: dt.date,
    fine_campagna: dt.date,
    tipologia: StoreFormat | None = None,
    quanti: int = 3,
) -> list[GiornoConsigliato]:
    """I giorni migliori per il banchetto dentro una finestra promozionale.

    Restituisce i candidati ordinati dal piu' promettente, cosi' la segretaria
    puo' proporre al punto vendita una data prima e un paio di alternative.
    """
    if fine_campagna < inizio_campagna:
        raise ValueError("la fine della campagna precede l'inizio")
    if quanti < 1:
        raise ValueError("serve almeno un giorno")

    candidati = []
    giorno = inizio_campagna
    while giorno <= fine_campagna:
        _, fase = _peso_fase(giorno, inizio_campagna, fine_campagna)
        candidati.append(
            GiornoConsigliato(
                data=giorno,
                punteggio=punteggio_affluenza(
                    giorno, tipologia, inizio_campagna, fine_campagna
                ),
                fase=fase,
            )
        )
        giorno += dt.timedelta(days=1)

    candidati.sort(key=lambda c: (-c.punteggio, c.data))
    return candidati[:quanti]
