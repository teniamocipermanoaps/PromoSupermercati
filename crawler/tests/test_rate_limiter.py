"""Test del rate limiting: temporizzazione verificata senza attese reali."""

import random

import pytest

from osservatorio.crawl.rate_limiter import DomainRateLimiter


class FakeClock:
    """Orologio finto: avanza solo quando qualcuno 'dorme'."""

    def __init__(self) -> None:
        self.now = 1000.0
        self.slept: list[float] = []

    def time(self) -> float:
        return self.now

    def sleep(self, seconds: float) -> None:
        self.slept.append(seconds)
        self.now += seconds

    def advance(self, seconds: float) -> None:
        self.now += seconds


def make_limiter(clock: FakeClock, seed: int = 0, **kw) -> DomainRateLimiter:
    return DomainRateLimiter(
        clock=clock.time, sleeper=clock.sleep, rng=random.Random(seed), **kw
    )


def test_prima_richiesta_non_attende():
    clock = FakeClock()
    limiter = make_limiter(clock)
    assert limiter.wait("https://www.esempio.it/a") == 0.0
    assert clock.slept == []


def test_seconda_richiesta_stesso_dominio_attende_almeno_il_minimo():
    clock = FakeClock()
    limiter = make_limiter(clock, min_delay_s=3.0, max_delay_s=5.0)
    limiter.wait("https://www.esempio.it/a")
    atteso = limiter.wait("https://www.esempio.it/b")
    assert 3.0 <= atteso <= 5.0
    assert clock.slept == [pytest.approx(atteso)]


def test_domini_diversi_non_si_bloccano_a_vicenda():
    clock = FakeClock()
    limiter = make_limiter(clock)
    limiter.wait("https://uno.it/a")
    assert limiter.wait("https://due.it/a") == 0.0


def test_tempo_gia_trascorso_riduce_l_attesa():
    clock = FakeClock()
    limiter = make_limiter(clock, min_delay_s=3.0, max_delay_s=3.0)
    limiter.wait("https://www.esempio.it/a")
    clock.advance(2.0)
    assert limiter.wait("https://www.esempio.it/b") == pytest.approx(1.0)


def test_nessuna_attesa_se_e_gia_passato_abbastanza_tempo():
    clock = FakeClock()
    limiter = make_limiter(clock, min_delay_s=3.0, max_delay_s=5.0)
    limiter.wait("https://www.esempio.it/a")
    clock.advance(60.0)
    assert limiter.wait("https://www.esempio.it/b") == 0.0


def test_crawl_delay_del_robots_prevale_se_piu_restrittivo():
    clock = FakeClock()
    limiter = make_limiter(clock, min_delay_s=3.0, max_delay_s=5.0)
    limiter.set_crawl_delay("https://www.esempio.it/", 20.0)
    limiter.wait("https://www.esempio.it/a")
    assert limiter.wait("https://www.esempio.it/b") == pytest.approx(20.0)


def test_crawl_delay_piu_permissivo_non_abbassa_il_nostro_minimo():
    clock = FakeClock()
    limiter = make_limiter(clock, min_delay_s=3.0, max_delay_s=5.0)
    limiter.set_crawl_delay("https://www.esempio.it/", 0.5)
    limiter.wait("https://www.esempio.it/a")
    assert limiter.wait("https://www.esempio.it/b") >= 3.0


def test_intervallo_non_valido_rifiutato():
    with pytest.raises(ValueError):
        DomainRateLimiter(min_delay_s=5.0, max_delay_s=3.0)
