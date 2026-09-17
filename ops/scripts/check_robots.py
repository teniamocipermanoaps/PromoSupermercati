#!/usr/bin/env python3
"""Gate legale di OsservatorioPromo.

Scarica il robots.txt di ogni dominio elencato in config/chains.yaml (piu'
eventuali domini extra passati da riga di comando), verifica se lo user-agent
del progetto puo' raccogliere gli URL dei volantini e salva una copia datata
del robots.txt in storage/legal/ come prova documentale.

Non aggira nulla: se il robots nega, l'esito e' DENIED e la catena va
disattivata in configurazione finche' non si ottiene un permesso esplicito.

Uso:
    python3 ops/scripts/check_robots.py
    python3 ops/scripts/check_robots.py --config config/chains.yaml
    python3 ops/scripts/check_robots.py --url https://www.esempio.it/volantini

Exit code: 0 se tutti i percorsi controllati sono consentiti, 1 se almeno un
robots.txt nega, 2 se almeno un robots.txt non e' stato raggiungibile.

La distinzione fra 1 e 2 conta: 1 e' una risposta del sito, che va annotata in
chains.legal_notes; 2 significa che non si e' verificato niente, e non va
scambiato per un divieto. In entrambi i casi non si raccoglie.
"""

import argparse
import datetime as dt
import pathlib
import sys
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
from typing import Dict, List

# Annotazioni con typing.List invece di list[str]: sui server Plesk il python3
# di sistema e' spesso un 3.6, dove le generiche incorporate non esistono
# ancora e il modulo non parte nemmeno. Il gate legale deve poter girare
# proprio la', sulla macchina che raggiunge i siti delle catene.

USER_AGENT = "OsservatorioPromoBot/0.1 (+mailto:pixartdesignltd@gmail.com)"
TIMEOUT_S = 30
LEGAL_DIR = pathlib.Path("storage/legal")

# I tre esiti possibili. BLOCCATO non e' un divieto del sito: e' l'assenza di
# una verifica, e si distingue perche' si corregge in modo diverso (una rete
# che non arriva, non un accordo da chiedere alla catena).
CONSENTITO = "CONSENTITO"
NEGATO = "NEGATO"
BLOCCATO = "BLOCCATO"

# Percorsi indicativi da verificare quando la catena non e' ancora configurata.
DEFAULT_TARGETS = {
    "conad": ["https://www.conad.it/", "https://www.conad.it/ricerca-negozi"],
    "carrefour": ["https://www.carrefour.it/", "https://www.carrefour.it/volantino"],
    "lidl": ["https://www.lidl.it/", "https://www.lidl.it/c/volantino/s10005610"],
}


def fetch_robots(origin: str) -> str:
    """Scarica il robots.txt di un'origine. Un 404 vale come robots assente."""
    url = urllib.parse.urljoin(origin, "/robots.txt")
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT_S) as resp:
            charset = resp.headers.get_content_charset() or "utf-8"
            return resp.read().decode(charset, errors="replace")
    except urllib.error.HTTPError as exc:
        if exc.code in (401, 403):
            # Accesso negato al robots stesso: lo trattiamo come divieto.
            raise RuntimeError(f"robots.txt non accessibile (HTTP {exc.code})") from exc
        if exc.code == 404:
            return ""
        raise RuntimeError(f"HTTP {exc.code} su {url}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"rete non raggiungibile: {exc.reason}") from exc


def archive(origin: str, body: str) -> pathlib.Path:
    """Salva una copia datata del robots.txt per tracciabilita'."""
    LEGAL_DIR.mkdir(parents=True, exist_ok=True)
    host = urllib.parse.urlparse(origin).netloc or origin
    stamp = dt.date.today().isoformat()
    path = LEGAL_DIR / f"{host}_{stamp}_robots.txt"
    path.write_text(body, encoding="utf-8")
    return path


def crawl_delay(parser: urllib.robotparser.RobotFileParser) -> str:
    delay = parser.crawl_delay(USER_AGENT)
    rate = parser.request_rate(USER_AGENT)
    if delay:
        return f"crawl-delay {delay}s"
    if rate:
        return f"request-rate {rate.requests}/{rate.seconds}s"
    return "non dichiarato (uso 3-5s)"


def check(label: str, targets: List[str]) -> str:
    origin = "{0.scheme}://{0.netloc}".format(urllib.parse.urlparse(targets[0]))
    print(f"\n=== {label} ({origin}) ===")
    try:
        body = fetch_robots(origin)
    except RuntimeError as exc:
        print(f"  ERRORE: {exc}")
        print("  ESITO: BLOCCATO (impossibile verificare -> non si raccoglie)")
        return BLOCCATO

    saved = archive(origin, body)
    parser = urllib.robotparser.RobotFileParser()
    parser.parse(body.splitlines())
    print(f"  robots.txt archiviato in {saved} ({len(body)} byte)")
    print(f"  Delay dichiarato: {crawl_delay(parser)}")

    all_allowed = True
    for url in targets:
        allowed = parser.can_fetch(USER_AGENT, url)
        all_allowed &= allowed
        print(f"  [{'OK     ' if allowed else 'DENIED '}] {url}")

    print(f"  ESITO: {'CONSENTITO' if all_allowed else 'NEGATO -> disattivare la catena'}")
    return CONSENTITO if all_allowed else NEGATO


def targets_from_config(path: pathlib.Path) -> Dict[str, List[str]]:
    """Legge config/chains.yaml se PyYAML e' disponibile, altrimenti None."""
    try:
        import yaml  # type: ignore
    except ImportError:
        print(f"(PyYAML non installato: ignoro {path}, uso i target di default)")
        return {}
    if not path.exists():
        print(f"({path} non trovato: uso i target di default)")
        return {}

    data = yaml.safe_load(path.read_text(encoding="utf-8")) or {}
    out = {}  # type: Dict[str, List[str]]
    for chain in data.get("chains", []):
        urls = [chain["website"]]
        template = (chain.get("discovery") or {}).get("url_template")
        if template:
            # Il template contiene segnaposto: verifichiamo il ramo di path.
            urls.append(template.split("{")[0])
        out[chain["slug"]] = urls
    return out


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--config", type=pathlib.Path, default=pathlib.Path("config/chains.yaml"))
    ap.add_argument("--url", action="append", default=[], help="URL extra da verificare")
    args = ap.parse_args()

    targets = targets_from_config(args.config) or dict(DEFAULT_TARGETS)
    for url in args.url:
        host = urllib.parse.urlparse(url).netloc
        targets.setdefault(host, []).append(url)

    print(f"User-Agent: {USER_AGENT}")
    esiti = {label: check(label, urls) for label, urls in sorted(targets.items()) if urls}

    print("\n--- RIEPILOGO ---")
    for label, esito in esiti.items():
        print(f"  {label:<14} {esito}")
    print("\nNota: robots.txt non sostituisce i Termini d'uso. Prima di attivare una")
    print("catena leggi anche le sue condizioni di servizio e annota l'esito in")
    print("chains.legal_notes.")

    # Un robots irraggiungibile ha la precedenza su un divieto: e' un guasto da
    # riparare prima di poter dire qualcosa sulla catena, non una risposta.
    if BLOCCATO in esiti.values():
        print("\nAlmeno una verifica non e' riuscita: nessun esito da annotare per")
        print("quelle catene, e niente da archiviare in storage/legal/. Ripetere il")
        print("controllo da una rete che raggiunge i siti.")
        return 2
    if NEGATO in esiti.values():
        return 1
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(130)
