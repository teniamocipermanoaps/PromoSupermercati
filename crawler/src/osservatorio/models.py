"""Tipi di dominio condivisi da adapter, estrattore e normalizzatore.

Il modello ruota sull'offerta: dove vale, quando vale, con quale tipologia di
punto vendita. La campagna promozionale (il volantino) e' solo il contenitore
che lega un insieme di offerte a un periodo e a un insieme di negozi.
"""

from __future__ import annotations

import dataclasses
import datetime as dt
import enum
import hashlib
import json
from typing import Any


class StoreFormat(str, enum.Enum):
    """Tipologia di punto vendita. Dimensione di analisi di primo livello."""

    IPERMERCATO = "ipermercato"
    SUPERSTORE = "superstore"
    SUPERMERCATO = "supermercato"
    DISCOUNT = "discount"
    SUPERETTE = "superette"
    CASH_AND_CARRY = "cash_and_carry"


class SourceKind(str, enum.Enum):
    """Come la catena rende disponibili le offerte."""

    #: La catena espone gia' le offerte in forma strutturata: nessuna
    #: rasterizzazione, nessuna chiamata al modello, costo zero e piu' precisione.
    STRUCTURED = "structured"
    #: Solo PDF o immagini: serve rasterizzare ed estrarre con l'AI.
    DOCUMENT = "document"


class PromoType(str, enum.Enum):
    SCONTO = "sconto"
    PREZZO_FISSO = "prezzo_fisso"
    TRE_X_DUE = "3x2"
    DUE_X_UNO = "2x1"
    SOTTOCOSTO = "sottocosto"
    FEDELTA = "fedelta"
    BUNDLE = "bundle"
    ALTRO = "altro"


class Unit(str, enum.Enum):
    G = "g"
    KG = "kg"
    ML = "ml"
    L = "l"
    PZ = "pz"
    CONF = "conf"


@dataclasses.dataclass(frozen=True, slots=True)
class StoreRef:
    """Un punto vendita come lo restituisce un adapter."""

    chain_slug: str
    external_id: str
    name: str
    city: str
    province: str
    address: str | None = None
    postal_code: str | None = None
    region: str | None = None
    banner_slug: str | None = None
    store_format: StoreFormat | None = None
    latitude: float | None = None
    longitude: float | None = None


@dataclasses.dataclass(frozen=True, slots=True)
class CampaignRef:
    """Una campagna promozionale attiva, prima del download.

    Corrisponde a una riga di `flyers`. Il documento e' opzionale: alcune
    catene pubblicano le offerte senza un PDF da scaricare.
    """

    chain_slug: str
    source_kind: SourceKind
    source_url: str
    external_id: str | None = None
    title: str | None = None
    valid_from: dt.date | None = None
    valid_to: dt.date | None = None
    store_external_ids: tuple[str, ...] = ()


@dataclasses.dataclass(frozen=True, slots=True)
class FetchedDocument:
    """Il documento scaricato, artefatto di lavorazione transitorio.

    Si conserva `content_hash` per la deduplica anche dopo aver cancellato i
    byte: un volantino gia' estratto non si riprocessa.
    """

    campaign: CampaignRef
    content: bytes
    media_type: str
    content_hash: str

    @staticmethod
    def hash_bytes(content: bytes) -> str:
        return hashlib.sha256(content).hexdigest()


@dataclasses.dataclass(slots=True)
class RawOffer:
    """Un'offerta come esce dall'estrattore o da una sorgente strutturata.

    Non normalizzata: prezzo al kg e abbinamento al catalogo arrivano dopo.
    """

    raw_name: str
    price: float
    brand: str | None = None
    description: str | None = None
    category_raw: str | None = None
    quantity_value: float | None = None
    quantity_unit: Unit | None = None
    price_original: float | None = None
    promo_type: PromoType = PromoType.SCONTO
    promo_text: str | None = None
    requires_loyalty: bool = False
    valid_from: dt.date | None = None
    valid_to: dt.date | None = None
    confidence: float | None = None
    bbox: dict[str, Any] | None = None
    page_number: int | None = None


def payload_hash(offers: list[RawOffer]) -> str:
    """Hash stabile di un insieme di offerte da sorgente strutturata.

    Serve a deduplicare le sorgenti senza file: `flyers.file_hash` ospita
    questo valore, cosi' la chiave di deduplica e' la stessa per entrambe le
    sorgenti. L'ordinamento rende l'hash indipendente dall'ordine di lettura.
    """
    canonical = sorted(
        (o.raw_name.strip().lower(), round(o.price, 2), o.quantity_value, 
         o.quantity_unit.value if o.quantity_unit else None)
        for o in offers
    )
    blob = json.dumps(canonical, ensure_ascii=False, sort_keys=True, default=str)
    return hashlib.sha256(blob.encode("utf-8")).hexdigest()
