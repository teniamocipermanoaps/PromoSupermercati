#!/usr/bin/env python3
"""Trasforma le date dei volantini trovate a mano in SQL per il database.

Le segretarie hanno bisogno di sapere quando un negozio sara' affollato, e per
questo bastano due date per volantino. Quelle date sono pubblicate ovunque:
sul sito della catena, nei comunicati, nei siti che li raccolgono. Trovarle e'
una ricerca, non una raccolta massiva, e chi la fa - una persona o un agente -
scrive il risultato in un file come questo.

Questo script non cerca niente in rete: prende il file, lo controlla riga per
riga e stampa l'SQL. Come ops/scripts/importa_negozi_osm.py, stampa e basta:
guardare l'SQL prima di darlo in pasto al database e' la rete di sicurezza fra
una ricerca che puo' sbagliare e l'agenda su cui una segretaria fa telefonate.

Uso:
    python3 ops/scripts/importa_campagne.py config/campagne.json
    python3 ops/scripts/importa_campagne.py config/campagne.json | mysql -u root NOMEDB

Formato del file (una lista di oggetti):

    [
      {
        "insegna": "lidl",                 # slug da config/chains.yaml
        "titolo": "Volantino settimanale",
        "dal": "2026-09-17",
        "al": "2026-09-23",
        "province": [],                    # vuoto = tutta Italia
        "tipologie": [],                    # vuoto = tutti i formati dell'insegna
        "fonte": "https://...",            # dove si e' letta la data
        "note": "nazionale, esce di giovedi'"
      }
    ]

Exit code: 0 se il file e' valido, 1 se almeno una riga e' da correggere.
Sull'uscita 1 non viene stampato nessun SQL: meglio niente che meta'.
"""

# Annotazioni con typing invece di list[str]: sul server il python3 di sistema
# puo' essere un 3.6, e questo script deve poterci girare.
from typing import Any, Dict, List

import argparse
import datetime as dt
import hashlib
import json
import pathlib
import re
import sys

SIGLA = re.compile(r"^[A-Z]{2}$")

# Gli stessi campi che scrive il modulo Campagne della dashboard. L'impronta e'
# calcolata con la stessa formula di CampaignRepository::crea(), cosi' una
# campagna inserita a mano e la stessa campagna importata non si sdoppiano.
CAMPI_OBBLIGATORI = ("insegna", "titolo", "dal", "al")


def sql_stringa(valore: str) -> str:
    """Letterale SQL con gli apici raddoppiati.

    Lo script stampa SQL invece di eseguirlo, quindi non ci sono statement
    preparati a cui appoggiarsi: l'escaping va fatto qui, su ogni stringa,
    senza eccezioni.
    """
    return "'" + str(valore).replace("\\", "\\\\").replace("'", "''") + "'"


def leggi_insegne(percorso: pathlib.Path) -> Dict[str, List[str]]:
    """Slug delle insegne e slug delle loro tipologie, da config/chains.yaml."""
    try:
        import yaml
    except ImportError:
        print("Serve PyYAML: pip3 install --user pyyaml", file=sys.stderr)
        raise SystemExit(1)

    dati = yaml.safe_load(percorso.read_text(encoding="utf-8")) or {}
    insegne = {}  # type: Dict[str, List[str]]
    for catena in dati.get("chains", []):
        banner = [b["slug"] for b in (catena.get("banners") or [])]
        insegne[catena["slug"]] = banner
    return insegne


def data(valore: Any) -> dt.date:
    return dt.datetime.strptime(str(valore), "%Y-%m-%d").date()


def controlla(riga: Dict[str, Any], indice: int, insegne: Dict[str, List[str]]) -> List[str]:
    """Tutti gli errori di una riga, non solo il primo.

    Chi corregge il file vuole sapere quante cose ci sono da sistemare, non
    scoprirle una alla volta rilanciando lo script.
    """
    errori = []  # type: List[str]
    dove = "riga %d" % (indice + 1)

    for campo in CAMPI_OBBLIGATORI:
        if not riga.get(campo):
            errori.append("%s: manca '%s'" % (dove, campo))
    if errori:
        return errori

    insegna = str(riga["insegna"])
    if insegna not in insegne:
        errori.append("%s: insegna '%s' non e' in config/chains.yaml (%s)"
                      % (dove, insegna, ", ".join(sorted(insegne))))

    try:
        dal, al = data(riga["dal"]), data(riga["al"])
        if al < dal:
            errori.append("%s: la data di fine (%s) precede quella di inizio (%s)"
                          % (dove, riga["al"], riga["dal"]))
        elif (al - dal).days > 120:
            # Un volantino dura giorni o settimane. Quattro mesi e' quasi sempre
            # un anno sbagliato o due campagne diverse finite nella stessa riga,
            # e in agenda diventerebbe un negozio "in promozione" per sempre.
            errori.append("%s: la campagna dura %d giorni, controlla le date"
                          % (dove, (al - dal).days + 1))
    except (ValueError, TypeError):
        errori.append("%s: date non in formato AAAA-MM-GG (%r, %r)"
                      % (dove, riga.get("dal"), riga.get("al")))

    for sigla in riga.get("province") or []:
        if not SIGLA.match(str(sigla)):
            errori.append("%s: '%s' non e' una sigla di provincia" % (dove, sigla))

    note = insegne.get(insegna, [])
    for tipologia in riga.get("tipologie") or []:
        if str(tipologia) not in note:
            errori.append("%s: tipologia '%s' non e' fra quelle di %s (%s)"
                          % (dove, tipologia, insegna, ", ".join(note) or "nessuna"))

    if not riga.get("fonte"):
        # Non blocca, ma senza fonte la data non si puo' ricontrollare: fra un
        # mese nessuno ricorda da dove era uscita.
        errori.append("%s: manca 'fonte' (l'indirizzo dove hai letto le date)" % dove)

    return errori


def sql_campagna(riga: Dict[str, Any]) -> str:
    insegna = str(riga["insegna"])
    titolo = str(riga["titolo"])
    dal, al = str(riga["dal"]), str(riga["al"])
    fonte = str(riga.get("fonte") or "ricerca manuale")
    province = [str(p) for p in (riga.get("province") or [])]
    tipologie = [str(t) for t in (riga.get("tipologie") or [])]

    catena = "(SELECT id FROM chains WHERE slug = %s)" % sql_stringa(insegna)

    # Stessa formula di CampaignRepository::crea(): chain_id|titolo|dal|al. Il
    # chain_id non e' noto qui, quindi l'impronta usa lo slug e ci si aggiunge
    # un prefisso che la distingue da quella scritta dalla dashboard. Le due
    # strade non si incrociano mai sullo stesso volantino: o lo inserisce una
    # segretaria dal modulo, o arriva da qui.
    impronta = hashlib.sha256(
        ("ricerca|" + insegna + "|" + titolo + "|" + dal + "|" + al).encode("utf-8")
    ).hexdigest()

    parti = []
    descrizione = "%s - %s (dal %s al %s)" % (insegna, titolo, dal, al)
    if province:
        descrizione += " solo %s" % " ".join(province)
    if tipologie:
        descrizione += " solo %s" % " ".join(tipologie)
    parti.append("-- " + descrizione)
    if riga.get("note"):
        parti.append("-- " + str(riga["note"]))

    # ON DUPLICATE KEY sulla chiave (chain_id, file_hash): rilanciare l'import
    # con le stesse date aggiorna la campagna invece di duplicarla, esattamente
    # come fa l'importatore dei punti vendita.
    parti.append(
        "INSERT INTO flyers\n"
        "  (chain_id, source_kind, title, valid_from, valid_to,\n"
        "   source_url, file_type, file_hash, downloaded_at, status)\n"
        "VALUES\n"
        "  (%s, 'structured', %s, %s, %s,\n"
        "   %s, 'json', %s, NOW(), 'extracted')\n"
        "ON DUPLICATE KEY UPDATE\n"
        "  title = VALUES(title),\n"
        "  valid_from = VALUES(valid_from),\n"
        "  valid_to = VALUES(valid_to),\n"
        "  source_url = VALUES(source_url);"
        % (catena, sql_stringa(titolo), sql_stringa(dal), sql_stringa(al),
           sql_stringa(fonte), sql_stringa(impronta))
    )

    volantino = ("(SELECT id FROM flyers WHERE chain_id = %s AND file_hash = %s)"
                 % (catena, sql_stringa(impronta)))

    # INSERT IGNORE e non REPLACE: se la campagna era gia' collegata a dei
    # negozi, quei collegamenti restano. Cancellare non si puo' comunque,
    # l'utente applicativo non ha il DELETE.
    aggancio = (
        "INSERT IGNORE INTO flyer_stores (flyer_id, store_id)\n"
        "SELECT %s, s.id\n"
        "  FROM stores s\n"
        " WHERE s.chain_id = %s\n"
        "   AND s.is_active = 1" % (volantino, catena)
    )
    if province:
        aggancio += "\n   AND s.province IN (%s)" % ", ".join(sql_stringa(p) for p in province)
    if tipologie:
        aggancio += ("\n   AND s.banner_id IN (SELECT id FROM store_banners"
                     " WHERE chain_id = %s AND slug IN (%s))"
                     % (catena, ", ".join(sql_stringa(t) for t in tipologie)))
    parti.append(aggancio + ";")

    return "\n".join(parti)


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("file", type=pathlib.Path, help="JSON con le campagne trovate")
    ap.add_argument("--config", type=pathlib.Path, default=pathlib.Path("config/chains.yaml"))
    argomenti = ap.parse_args()

    if not argomenti.file.exists():
        print("File non trovato: %s" % argomenti.file, file=sys.stderr)
        return 1

    try:
        righe = json.loads(argomenti.file.read_text(encoding="utf-8"))
    except ValueError as errore:
        print("JSON non valido: %s" % errore, file=sys.stderr)
        return 1
    if not isinstance(righe, list):
        print("Il file deve contenere una lista di campagne.", file=sys.stderr)
        return 1

    insegne = leggi_insegne(argomenti.config)

    errori = []  # type: List[str]
    for indice, riga in enumerate(righe):
        if not isinstance(riga, dict):
            errori.append("riga %d: non e' un oggetto" % (indice + 1))
            continue
        errori.extend(controlla(riga, indice, insegne))

    if errori:
        print("Il file ha %d problemi, nessun SQL prodotto:" % len(errori), file=sys.stderr)
        for errore in errori:
            print("  " + errore, file=sys.stderr)
        return 1

    oggi = dt.date.today().isoformat()
    print("-- Campagne promozionali, da ricerca manuale. Generato il %s" % oggi)
    print("-- Rilanciare questo file aggiorna le campagne, non le duplica.")
    print("-- Prodotto da ops/scripts/importa_campagne.py: controllare prima di eseguire.")
    print("")
    print("START TRANSACTION;")
    print("")
    for riga in righe:
        print(sql_campagna(riga))
        print("")
    print("COMMIT;")

    scadute = sum(1 for r in righe if data(r["al"]) < dt.date.today())
    print("")
    print("-- %d campagne, di cui %d gia' finite alla data di oggi." % (len(righe), scadute))
    return 0


if __name__ == "__main__":
    sys.exit(main())
