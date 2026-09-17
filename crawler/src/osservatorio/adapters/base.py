"""Interfaccia comune a tutti gli adapter di catena.

Una catena si aggiunge scrivendo una sottoclasse di ChainAdapter e una voce in
config/chains.yaml. Nessun altro punto del sistema conosce le singole catene.

Due sorgenti possibili, e non sono equivalenti:

- SourceKind.STRUCTURED: la catena espone gia' le offerte (API interna del
  visualizzatore di volantini, JSON-LD, feed). L'adapter le restituisce
  direttamente: nessuna rasterizzazione, nessuna chiamata al modello, quindi
  costo zero e nessun errore di lettura. Da preferire sempre quando esiste.
- SourceKind.DOCUMENT: esiste solo il PDF o le immagini. Si passa per
  rasterizzazione ed estrazione AI, con il costo e il margine d'errore che
  ne conseguono.
"""

from __future__ import annotations

import abc
from collections.abc import Iterable
from typing import ClassVar

from osservatorio.models import (
    CampaignRef,
    FetchedDocument,
    RawOffer,
    SourceKind,
    StoreRef,
)


class AdapterError(RuntimeError):
    """Errore recuperabile di un adapter: finisce in crawl_log, non ferma il run."""


class CollectionNotPermitted(AdapterError):
    """La sorgente vieta la raccolta automatica.

    Sollevata quando robots.txt nega o i Termini d'uso escludono la raccolta.
    Non va mai intercettata per riprovare in altro modo: la catena si disattiva.
    """


class ChainAdapter(abc.ABC):
    """Contratto di un adapter di catena."""

    #: Identificativo, deve coincidere con `slug` in chains.yaml.
    slug: ClassVar[str]
    #: Come la catena pubblica le offerte.
    source_kind: ClassVar[SourceKind] = SourceKind.DOCUMENT

    def __init__(self, config: dict) -> None:
        self.config = config

    # -- scoperta -----------------------------------------------------------

    @abc.abstractmethod
    def discover_stores(self, city: str, province: str) -> Iterable[StoreRef]:
        """Elenca i punti vendita della catena in una citta'.

        Deve valorizzare `banner_slug` e `store_format` quando il sito li
        dichiara: la tipologia di punto vendita e' una dimensione di analisi,
        non un dettaglio accessorio.
        """

    @abc.abstractmethod
    def discover_campaigns(self, store: StoreRef) -> Iterable[CampaignRef]:
        """Elenca le campagne promozionali attive per un punto vendita."""

    # -- raccolta -----------------------------------------------------------

    def fetch_offers(self, campaign: CampaignRef) -> list[RawOffer]:
        """Offerte da sorgente strutturata. Solo per source_kind STRUCTURED."""
        raise NotImplementedError(
            f"{type(self).__name__} non espone offerte strutturate"
        )

    def fetch_document(self, campaign: CampaignRef) -> FetchedDocument:
        """Documento da rasterizzare. Solo per source_kind DOCUMENT."""
        raise NotImplementedError(f"{type(self).__name__} non scarica documenti")

    def fetch(self, campaign: CampaignRef) -> list[RawOffer] | FetchedDocument:
        """Punto d'ingresso unico: smista in base alla sorgente della campagna."""
        if campaign.source_kind is SourceKind.STRUCTURED:
            return self.fetch_offers(campaign)
        return self.fetch_document(campaign)
