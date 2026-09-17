"""Test del gate robots.txt: in caso di dubbio non si raccoglie."""

import urllib.error

import pytest

from osservatorio.crawl.robots import RobotsGate

UA = "OsservatorioPromoBot/0.1"

ROBOTS_PERMISSIVO = """
User-agent: *
Disallow: /checkout
Allow: /
"""

ROBOTS_RESTRITTIVO = """
User-agent: *
Disallow: /volantini
Disallow: /
"""

ROBOTS_CON_DELAY = """
User-agent: *
Crawl-delay: 10
Allow: /
"""


def gate_con(body: str) -> RobotsGate:
    return RobotsGate(user_agent=UA, fetcher=lambda url, ua, t: body)


def test_percorso_consentito():
    decisione = gate_con(ROBOTS_PERMISSIVO).check("https://esempio.it/volantini/napoli")
    assert decisione.allowed is True


def test_percorso_vietato():
    decisione = gate_con(ROBOTS_RESTRITTIVO).check("https://esempio.it/volantini/napoli")
    assert decisione.allowed is False
    assert "vietato" in decisione.reason


def test_crawl_delay_letto():
    decisione = gate_con(ROBOTS_CON_DELAY).check("https://esempio.it/x")
    assert decisione.allowed is True
    assert decisione.crawl_delay_s == pytest.approx(10.0)


def test_robots_assente_significa_consentito():
    def fetcher(url, ua, timeout):
        raise urllib.error.HTTPError(url, 404, "Not Found", {}, None)

    decisione = RobotsGate(user_agent=UA, fetcher=fetcher).check("https://esempio.it/x")
    assert decisione.allowed is True


def test_errore_di_rete_fallisce_in_modo_chiuso():
    def fetcher(url, ua, timeout):
        raise urllib.error.URLError("connessione rifiutata")

    decisione = RobotsGate(user_agent=UA, fetcher=fetcher).check("https://esempio.it/x")
    assert decisione.allowed is False
    assert "irraggiungibile" in decisione.reason


def test_403_sul_robots_fallisce_in_modo_chiuso():
    def fetcher(url, ua, timeout):
        raise urllib.error.HTTPError(url, 403, "Forbidden", {}, None)

    decisione = RobotsGate(user_agent=UA, fetcher=fetcher).check("https://esempio.it/x")
    assert decisione.allowed is False


def test_robots_scaricato_una_volta_sola_per_origine():
    chiamate = []

    def fetcher(url, ua, timeout):
        chiamate.append(url)
        return ROBOTS_PERMISSIVO

    gate = RobotsGate(user_agent=UA, fetcher=fetcher)
    gate.check("https://esempio.it/a")
    gate.check("https://esempio.it/b")
    gate.check("https://altro.it/a")
    assert chiamate == ["https://esempio.it/robots.txt", "https://altro.it/robots.txt"]
