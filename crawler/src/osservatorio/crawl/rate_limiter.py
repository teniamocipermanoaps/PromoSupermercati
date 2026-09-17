"""Rate limiting per dominio.

Una richiesta ogni 3-5 secondi per dominio, con jitter, e mai piu' di una
richiesta in volo verso lo stesso host. Se il robots.txt dichiara un
crawl-delay maggiore, vince quello.

Clock, sleep e generatore casuale sono iniettabili: cosi' i test verificano la
temporizzazione senza attendere davvero.
"""

from __future__ import annotations

import random
import threading
import time
import urllib.parse
from collections.abc import Callable


class DomainRateLimiter:
    """Impone un intervallo minimo casuale fra due richieste allo stesso host."""

    def __init__(
        self,
        min_delay_s: float = 3.0,
        max_delay_s: float = 5.0,
        clock: Callable[[], float] = time.monotonic,
        sleeper: Callable[[float], None] = time.sleep,
        rng: random.Random | None = None,
    ) -> None:
        if min_delay_s <= 0 or max_delay_s < min_delay_s:
            raise ValueError("intervallo non valido: serve 0 < min <= max")
        self.min_delay_s = min_delay_s
        self.max_delay_s = max_delay_s
        self._clock = clock
        self._sleeper = sleeper
        self._rng = rng or random.Random()
        self._last_call: dict[str, float] = {}
        self._overrides: dict[str, float] = {}
        self._lock = threading.Lock()

    @staticmethod
    def domain_of(url: str) -> str:
        return urllib.parse.urlparse(url).netloc.lower()

    def set_crawl_delay(self, url: str, delay_s: float | None) -> None:
        """Applica il crawl-delay dichiarato da un robots.txt, se piu' restrittivo."""
        if delay_s and delay_s > 0:
            self._overrides[self.domain_of(url)] = float(delay_s)

    def _delay_for(self, domain: str) -> float:
        wanted = self._rng.uniform(self.min_delay_s, self.max_delay_s)
        declared = self._overrides.get(domain)
        return max(wanted, declared) if declared else wanted

    def wait(self, url: str) -> float:
        """Attende quanto serve prima di poter chiamare `url`. Ritorna l'attesa."""
        domain = self.domain_of(url)
        with self._lock:
            now = self._clock()
            last = self._last_call.get(domain)
            sleep_for = 0.0
            if last is not None:
                elapsed = now - last
                needed = self._delay_for(domain)
                if elapsed < needed:
                    sleep_for = needed - elapsed
            if sleep_for > 0:
                self._sleeper(sleep_for)
            self._last_call[domain] = self._clock()
            return sleep_for
