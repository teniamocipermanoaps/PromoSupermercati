#!/usr/bin/env python3
"""Importa i punti vendita da OpenStreetMap.

    python3 ops/scripts/importa_negozi_osm.py > negozi.sql
    python3 ops/scripts/importa_negozi_osm.py --citta Napoli --citta Bari
    python3 ops/scripts/importa_negozi_osm.py --da-file risposta.json

Perche' OpenStreetMap e non i siti delle catene: i supermercati italiani sono
gia' mappati con insegna, indirizzo e posizione, il dato e' aperto e fatto
apposta per essere riusato. Nessun Termine d'uso da leggere, nessuna pagina da
raschiare, nessun adapter da mantenere quando la catena rifa' il sito.

Non e' perfetto: nomi e posizioni sono buoni, i telefoni molto meno. Si parte
da schede compilate in gran parte e le segretarie completano il resto man mano
che chiamano, che e' lavoro che farebbero comunque.

Lo script NON scrive nel database: stampa SQL, che si legge prima di applicare.
E' rieseguibile: l'identificativo di ogni negozio e' il suo id OpenStreetMap,
quindi rilanciarlo aggiorna le schede invece di duplicarle.

Richiede Python 3.6+ e PyYAML (pip3 install --user pyyaml).
"""

import argparse
import json
import pathlib
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Dict, List, Optional, Tuple

RADICE = pathlib.Path(__file__).resolve().parents[2]
OVERPASS = "https://overpass-api.de/api/interpreter"
USER_AGENT = "OsservatorioPromoBot/0.1 (+mailto:pixartdesignltd@gmail.com)"
TIMEOUT_S = 180

# Overpass e' un servizio pubblico e gratuito: fra una citta' e l'altra si
# aspetta. Con 100 citta' sono una quindicina di minuti, e vanno bene.
ATTESA_S = 8


def errore(messaggio: str) -> None:
    sys.stderr.write("Errore: " + messaggio + "\n")
    sys.exit(1)


def carica_yaml(percorso: pathlib.Path):
    try:
        import yaml
    except ImportError:
        errore("serve PyYAML. Installalo con:  pip3 install --user pyyaml")
    if not percorso.exists():
        errore("file di configurazione mancante: {0}".format(percorso))
    return yaml.safe_load(percorso.read_text(encoding="utf-8"))


def indice_marchi(catene: List[dict]) -> List[Tuple[str, str, str]]:
    """Da nome di marchio a (slug catena, slug tipologia, nome marchio).

    Ordinato dal piu' lungo al piu' corto: "Conad Superstore" deve vincere su
    "Conad", altrimenti ogni superstore finirebbe classificato supermercato e
    il confronto fra formati direbbe sciocchezze.
    """
    voci = []
    for catena in catene:
        tipologie = catena.get("banners") or []
        for marchio in catena.get("osm_brands") or []:
            tipologia = scegli_tipologia(marchio, tipologie)
            voci.append((marchio.lower(), catena["slug"], tipologia))
    voci.sort(key=lambda v: len(v[0]), reverse=True)
    return voci


def scegli_tipologia(marchio: str, tipologie: List[dict]) -> Optional[str]:
    """La tipologia il cui nome corrisponde meglio al marchio."""
    migliore = None
    for tipologia in tipologie:
        nome = tipologia["name"].lower()
        if nome == marchio.lower():
            return tipologia["slug"]
        if nome in marchio.lower() and (migliore is None or len(nome) > len(migliore[0])):
            migliore = (nome, tipologia["slug"])
    return migliore[1] if migliore else None


def riconosci(etichette: dict, marchi: List[Tuple[str, str, str]]):
    """Che insegna e' questo negozio? None se non e' una di quelle seguite.

    Si scorrono i marchi (gia' ordinati dal piu' lungo) e per ciascuno tutte le
    etichette, non il contrario: un negozio con brand="Deco" e name="Deco Maxi
    Ponticelli" e' un superstore, e guardando prima il brand lo si
    classificherebbe supermercato. Siccome la tipologia e' una dimensione di
    analisi e non un dettaglio, sbagliarla falsa i confronti fra insegne.
    """
    candidati = [
        etichette.get("brand"), etichette.get("name"), etichette.get("operator"),
    ]
    candidati = [c.lower() for c in candidati if c]

    for marchio, catena, tipologia in marchi:
        for candidato in candidati:
            if candidato == marchio or candidato.startswith(marchio + " "):
                return catena, tipologia
    return None


def query(citta: str, regione: str) -> str:
    """La regione disambigua i nomi di comune che si ripetono in Italia."""
    return (
        '[out:json][timeout:{t}];\n'
        'area["name"="{r}"]["admin_level"="4"]->.regione;\n'
        'area["name"="{c}"]["admin_level"="8"](area.regione)->.comune;\n'
        'nwr["shop"~"^(supermarket|convenience)$"](area.comune);\n'
        'out center tags;'
    ).format(t=TIMEOUT_S - 10, r=regione, c=citta)


def chiedi(corpo: str) -> dict:
    richiesta = urllib.request.Request(
        OVERPASS,
        data=urllib.parse.urlencode({"data": corpo}).encode("utf-8"),
        headers={"User-Agent": USER_AGENT},
    )
    try:
        with urllib.request.urlopen(richiesta, timeout=TIMEOUT_S) as risposta:
            return json.loads(risposta.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        if exc.code == 429:
            raise RuntimeError("Overpass sta limitando le richieste: aspetta e riprova")
        raise RuntimeError("HTTP {0} da Overpass".format(exc.code))
    except urllib.error.URLError as exc:
        raise RuntimeError("rete non raggiungibile: {0}".format(exc.reason))


def negozi(dati: dict, citta: dict, marchi: List[Tuple[str, str, str]]) -> List[dict]:
    trovati = []
    for elemento in dati.get("elements", []):
        etichette = elemento.get("tags") or {}
        riconosciuto = riconosci(etichette, marchi)
        if riconosciuto is None:
            continue
        catena, tipologia = riconosciuto

        centro = elemento.get("center") or {}
        trovati.append({
            "external_id": "osm:{0}/{1}".format(elemento.get("type"), elemento.get("id")),
            "catena": catena,
            "tipologia": tipologia,
            "name": etichette.get("name") or etichette.get("brand") or "senza nome",
            "address": indirizzo(etichette),
            "postal_code": (etichette.get("addr:postcode") or "")[:5] or None,
            "phone": etichette.get("phone") or etichette.get("contact:phone"),
            "opening_hours": etichette.get("opening_hours"),
            "city": citta["name"],
            "province": citta["province"],
            "region": citta["region"],
            "latitude": elemento.get("lat", centro.get("lat")),
            "longitude": elemento.get("lon", centro.get("lon")),
        })
    return trovati


def indirizzo(etichette: dict) -> Optional[str]:
    via = etichette.get("addr:street")
    if not via:
        return None
    civico = etichette.get("addr:housenumber")
    return (via + " " + civico) if civico else via


def cita(valore) -> str:
    if valore is None or valore == "":
        return "NULL"
    if isinstance(valore, (int, float)):
        return str(valore)
    return "'" + str(valore).replace("\\", "\\\\").replace("'", "''") + "'"


def sql(negozio: dict) -> str:
    tipologia = negozio["tipologia"]
    aggancio = (
        "LEFT JOIN store_banners b ON b.chain_id = c.id AND b.slug = {0}".format(cita(tipologia))
        if tipologia else "LEFT JOIN store_banners b ON 1 = 0"
    )
    return (
        "INSERT INTO stores\n"
        "  (chain_id, banner_id, external_id, name, address, phone, opening_hours,\n"
        "   postal_code, city, province, region, latitude, longitude)\n"
        "SELECT c.id, b.id, {ext}, {nome}, {ind}, {tel}, {orari},\n"
        "       {cap}, {citta}, {prov}, {reg}, {lat}, {lon}\n"
        "FROM chains c {aggancio}\n"
        "WHERE c.slug = {catena}\n"
        "ON DUPLICATE KEY UPDATE\n"
        "  banner_id = VALUES(banner_id), name = VALUES(name),\n"
        "  address = VALUES(address), postal_code = VALUES(postal_code),\n"
        "  latitude = VALUES(latitude), longitude = VALUES(longitude),\n"
        "  phone = COALESCE(stores.phone, VALUES(phone)),\n"
        "  opening_hours = COALESCE(stores.opening_hours, VALUES(opening_hours));"
    ).format(
        ext=cita(negozio["external_id"]), nome=cita(negozio["name"]),
        ind=cita(negozio["address"]), tel=cita(negozio["phone"]),
        orari=cita(negozio["opening_hours"]), cap=cita(negozio["postal_code"]),
        citta=cita(negozio["city"]), prov=cita(negozio["province"]),
        reg=cita(negozio["region"]), lat=cita(negozio["latitude"]),
        lon=cita(negozio["longitude"]), aggancio=aggancio, catena=cita(negozio["catena"]),
    )


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--citta", action="append", default=[],
                    help="limita a una o piu' citta' (ripetibile). Senza, le fa tutte.")
    ap.add_argument("--da-file", type=pathlib.Path,
                    help="legge una risposta Overpass gia' salvata invece di chiederla")
    ap.add_argument("--mostra-query", action="store_true",
                    help="stampa la query e basta, senza chiedere niente a nessuno")
    ap.add_argument("--attesa", type=float, default=ATTESA_S,
                    help="secondi fra una citta' e l'altra (predefinito {0})".format(ATTESA_S))
    args = ap.parse_args()

    catene = carica_yaml(RADICE / "config" / "chains.yaml")["chains"]
    citta_tutte = carica_yaml(RADICE / "config" / "cities.yaml")["cities"]
    marchi = indice_marchi(catene)

    scelte = citta_tutte
    if args.citta:
        volute = set(c.lower() for c in args.citta)
        scelte = [c for c in citta_tutte if c["name"].lower() in volute]
        mancanti = volute - set(c["name"].lower() for c in scelte)
        if mancanti:
            errore("citta' non in config/cities.yaml: " + ", ".join(sorted(mancanti)))

    if args.mostra_query:
        for c in scelte:
            sys.stderr.write("=== {0} ===\n".format(c["name"]))
            print(query(c["name"], c["region"]))
        return 0

    print("-- Punti vendita da OpenStreetMap (ODbL), {0}".format(time.strftime("%Y-%m-%d")))
    print("-- Generato da ops/scripts/importa_negozi_osm.py. Rieseguibile.")
    print("-- Insegne cercate: {0}".format(", ".join(sorted(c["slug"] for c in catene))))
    print()

    totale = 0
    for indice, c in enumerate(scelte):
        try:
            if args.da_file:
                dati = json.loads(args.da_file.read_text(encoding="utf-8"))
            else:
                if indice:
                    time.sleep(args.attesa)
                dati = chiedi(query(c["name"], c["region"]))
        except RuntimeError as exc:
            sys.stderr.write("{0}: {1}\n".format(c["name"], exc))
            continue

        trovati = negozi(dati, c, marchi)
        totale += len(trovati)
        sys.stderr.write("{0:<28} {1:>4} negozi\n".format(c["name"], len(trovati)))
        if trovati:
            print("-- {0} ({1})".format(c["name"], c["province"]))
            for negozio in trovati:
                print(sql(negozio))
            print()

    sys.stderr.write("\nTotale: {0} negozi in {1} citta'.\n".format(totale, len(scelte)))
    sys.stderr.write("Rileggi il file prima di applicarlo, poi:\n")
    sys.stderr.write("  mariadb <database> < negozi.sql\n")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(130)
