#!/usr/bin/env python3
"""Genera db/seeds/004_insegne.sql da config/chains.yaml.

    python3 ops/scripts/genera_seed_insegne.py > db/seeds/004_insegne.sql

Le insegne e le loro tipologie stanno in un posto solo, la configurazione.
Il seed e' una copia meccanica: rigenerarlo invece di modificarlo a mano
impedisce che i due divergano in silenzio, che e' il modo in cui un negozio
finisce classificato col formato sbagliato.
"""

import pathlib
import sys

RADICE = pathlib.Path(__file__).resolve().parents[2]


def cita(valore: str) -> str:
    return "'" + str(valore).replace("\\", "\\\\").replace("'", "''") + "'"


def main() -> int:
    try:
        import yaml
    except ImportError:
        sys.stderr.write("Errore: serve PyYAML (pip3 install --user pyyaml)\n")
        return 1

    percorso = RADICE / "config" / "chains.yaml"
    catene = yaml.safe_load(percorso.read_text(encoding="utf-8"))["chains"]

    print("-- Insegne monitorate e loro tipologie di punto vendita.")
    print("--")
    print("-- GENERATO da config/chains.yaml: non modificarlo a mano.")
    print("--   python3 ops/scripts/genera_seed_insegne.py > db/seeds/004_insegne.sql")
    print("--")
    print("-- is_active = 0 su tutte: nessuna catena e' raccoglibile finche' il gate")
    print("-- legale (robots.txt + Termini d'uso) non e' superato e documentato.")
    print("-- Rieseguibile senza danno.")
    print()
    print("INSERT INTO chains (slug, name, website_url, adapter, segment, is_active, legal_notes) VALUES")
    righe = []
    for c in catene:
        righe.append("  ({0}, {1}, {2}, {3}, {4}, 0, {5})".format(
            cita(c["slug"]), cita(c["name"]), cita(c["website"]),
            cita(c["adapter"]), cita(c["segment"]), cita(c.get("legal_notes", ""))))
    print(",\n".join(righe))
    print("ON DUPLICATE KEY UPDATE")
    print("  name = VALUES(name), website_url = VALUES(website_url),")
    print("  adapter = VALUES(adapter), segment = VALUES(segment),")
    print("  legal_notes = VALUES(legal_notes);")
    print()
    print("-- Tipologie: la stessa insegna opera formati con promozioni diverse, e")
    print("-- confrontarle ignorando il formato da' numeri senza significato.")
    print()
    print("INSERT INTO store_banners (chain_id, slug, name, format)")
    print("SELECT c.id, v.slug, v.name, v.format")
    print("FROM chains c")
    print("JOIN (")
    tipologie = []
    for c in catene:
        for b in c.get("banners") or []:
            if not tipologie:
                tipologie.append("  SELECT {0} AS chain, {1} AS slug, {2} AS name, {3} AS format".format(
                    cita(c["slug"]), cita(b["slug"]), cita(b["name"]), cita(b["format"])))
            else:
                tipologie.append("  SELECT {0}, {1}, {2}, {3}".format(
                    cita(c["slug"]), cita(b["slug"]), cita(b["name"]), cita(b["format"])))
    print(" UNION ALL\n".join(tipologie))
    print(") v ON v.chain = c.slug")
    print("ON DUPLICATE KEY UPDATE name = VALUES(name), format = VALUES(format);")
    return 0


if __name__ == "__main__":
    sys.exit(main())
