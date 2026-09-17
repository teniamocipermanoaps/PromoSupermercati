"""Test del contratto adapter e del registro."""

import pytest

from osservatorio.adapters import registry
from osservatorio.adapters.base import ChainAdapter
from osservatorio.models import CampaignRef, FetchedDocument, RawOffer, SourceKind


@pytest.fixture(autouse=True)
def registro_pulito():
    registry.clear()
    yield
    registry.clear()


class AdapterStrutturato(ChainAdapter):
    slug = "catena-strutturata"
    source_kind = SourceKind.STRUCTURED

    def discover_stores(self, city, province):
        return []

    def discover_campaigns(self, store):
        return []

    def fetch_offers(self, campaign):
        return [RawOffer(raw_name="Pasta 500 g", price=0.99)]


class AdapterDocumentale(ChainAdapter):
    slug = "catena-documentale"
    source_kind = SourceKind.DOCUMENT

    def discover_stores(self, city, province):
        return []

    def discover_campaigns(self, store):
        return []

    def fetch_document(self, campaign):
        content = b"%PDF-1.4 finto"
        return FetchedDocument(
            campaign=campaign,
            content=content,
            media_type="application/pdf",
            content_hash=FetchedDocument.hash_bytes(content),
        )


def campagna(kind: SourceKind) -> CampaignRef:
    return CampaignRef(
        chain_slug="x", source_kind=kind, source_url="https://esempio.it/v"
    )


def test_registrazione_e_risoluzione():
    registry.register(AdapterStrutturato)
    assert registry.get("catena-strutturata") is AdapterStrutturato
    assert registry.available() == ("catena-strutturata",)


def test_slug_sconosciuto_elenca_i_disponibili():
    registry.register(AdapterStrutturato)
    with pytest.raises(LookupError, match="catena-strutturata"):
        registry.get("inesistente")


def test_slug_duplicato_rifiutato():
    registry.register(AdapterStrutturato)

    class Doppione(ChainAdapter):
        slug = "catena-strutturata"

        def discover_stores(self, city, province):
            return []

        def discover_campaigns(self, store):
            return []

    with pytest.raises(ValueError, match="gia' registrato"):
        registry.register(Doppione)


def test_adapter_senza_slug_rifiutato():
    class SenzaSlug(ChainAdapter):
        def discover_stores(self, city, province):
            return []

        def discover_campaigns(self, store):
            return []

    with pytest.raises(ValueError, match="slug"):
        registry.register(SenzaSlug)


def test_fetch_smista_su_sorgente_strutturata():
    risultato = AdapterStrutturato({}).fetch(campagna(SourceKind.STRUCTURED))
    assert isinstance(risultato, list)
    assert risultato[0].raw_name == "Pasta 500 g"


def test_fetch_smista_su_documento():
    risultato = AdapterDocumentale({}).fetch(campagna(SourceKind.DOCUMENT))
    assert isinstance(risultato, FetchedDocument)
    assert len(risultato.content_hash) == 64


def test_sorgente_non_supportata_solleva_errore_esplicito():
    with pytest.raises(NotImplementedError, match="documenti"):
        AdapterStrutturato({}).fetch(campagna(SourceKind.DOCUMENT))
    with pytest.raises(NotImplementedError, match="strutturate"):
        AdapterDocumentale({}).fetch(campagna(SourceKind.STRUCTURED))


def test_hash_offerte_indipendente_dall_ordine():
    from osservatorio.models import payload_hash

    a = RawOffer(raw_name="Pasta", price=0.99)
    b = RawOffer(raw_name="Latte", price=1.29)
    assert payload_hash([a, b]) == payload_hash([b, a])


def test_hash_offerte_cambia_se_cambia_un_prezzo():
    from osservatorio.models import payload_hash

    a = RawOffer(raw_name="Pasta", price=0.99)
    b = RawOffer(raw_name="Pasta", price=1.09)
    assert payload_hash([a]) != payload_hash([b])
