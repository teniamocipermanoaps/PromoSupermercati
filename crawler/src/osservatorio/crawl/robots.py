"""Gate robots.txt.

Regola del progetto: si fallisce in modo chiuso. Se il robots non e'
raggiungibile o non e' interpretabile, la raccolta non parte. Non esistono
percorsi alternativi per aggirare un divieto: se una catena nega, si disattiva
e se ne discute con la catena.
"""

from __future__ import annotations

import dataclasses
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
from collections.abc import Callable

DEFAULT_USER_AGENT = "OsservatorioPromoBot/0.1 (+mailto:pixartdesignltd@gmail.com)"


@dataclasses.dataclass(frozen=True, slots=True)
class RobotsDecision:
    """Esito della verifica, da registrare in crawl_log."""

    url: str
    allowed: bool
    reason: str
    crawl_delay_s: float | None = None


def _default_fetcher(robots_url: str, user_agent: str, timeout: float) -> str:
    request = urllib.request.Request(robots_url, headers={"User-Agent": user_agent})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        charset = response.headers.get_content_charset() or "utf-8"
        return response.read().decode(charset, errors="replace")


class RobotsGate:
    """Decide se un URL e' raccoglibile, con cache per origine."""

    def __init__(
        self,
        user_agent: str = DEFAULT_USER_AGENT,
        timeout_s: float = 30.0,
        fetcher: Callable[[str, str, float], str] = _default_fetcher,
    ) -> None:
        self.user_agent = user_agent
        self.timeout_s = timeout_s
        self._fetcher = fetcher
        self._cache: dict[str, urllib.robotparser.RobotFileParser | None] = {}
        self._errors: dict[str, str] = {}

    @staticmethod
    def origin_of(url: str) -> str:
        parts = urllib.parse.urlparse(url)
        return f"{parts.scheme}://{parts.netloc}"

    def _parser_for(self, origin: str) -> urllib.robotparser.RobotFileParser | None:
        if origin in self._cache:
            return self._cache[origin]

        try:
            body = self._fetcher(f"{origin}/robots.txt", self.user_agent, self.timeout_s)
        except urllib.error.HTTPError as exc:
            if exc.code == 404:
                # Nessun robots.txt: nulla di vietato, ma resta il rate limiting.
                parser = urllib.robotparser.RobotFileParser()
                parser.parse([])
                self._cache[origin] = parser
                return parser
            self._errors[origin] = f"HTTP {exc.code} sul robots.txt"
            self._cache[origin] = None
            return None
        except Exception as exc:  # rete, TLS, timeout, decodifica
            self._errors[origin] = f"robots.txt irraggiungibile: {exc}"
            self._cache[origin] = None
            return None

        parser = urllib.robotparser.RobotFileParser()
        parser.parse(body.splitlines())
        self._cache[origin] = parser
        return parser

    def check(self, url: str) -> RobotsDecision:
        """Verifica un URL. In caso di dubbio risponde 'non consentito'."""
        origin = self.origin_of(url)
        parser = self._parser_for(origin)
        if parser is None:
            return RobotsDecision(
                url=url,
                allowed=False,
                reason=self._errors.get(origin, "robots.txt non verificabile"),
            )

        allowed = parser.can_fetch(self.user_agent, url)
        delay = parser.crawl_delay(self.user_agent)
        return RobotsDecision(
            url=url,
            allowed=allowed,
            reason="consentito da robots.txt" if allowed else "vietato da robots.txt",
            crawl_delay_s=float(delay) if delay else None,
        )
