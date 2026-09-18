# Sessione con le segretarie

Traccia per mostrare la dashboard a chi la usera' davvero e raccogliere un
riscontro utilizzabile. Circa un'ora.

Esiste anche come pagina con le schermate; questo file serve perche' le domande
restino nel repository insieme al codice che le ha generate.

## Prima di cominciare

```bash
ops/scripts/demo.sh        # prepara i dati finti e avvia il server
```

Apri `http://127.0.0.1:8080`. Falla aprire anche da telefono: le pagine sono
state sistemate per lo schermo stretto, e quel giudizio conta piu' di quello
da scrivania.

Due cose da dire subito:

- i punti vendita sono inventati, cosi' non perdono tempo a segnalarlo;
- stai mostrando una bozza per farla cambiare. Se pensano di dover essere
  gentili con un lavoro finito, il riscontro utile non arriva.

## Il giro delle quattro schermate

L'ordine parte da cio' che risolve un problema che hanno gia' e finisce con
cio' che chiede loro un lavoro in piu'.

### 1. Agenda contatti (15 min)

Chi richiamare entro sette giorni con il numero cliccabile, e le campagne in
arrivo con i tre giorni migliori da proporre.

- Aprendo questa pagina lunedi' mattina, cosa fai per prima cosa?
- I tre giorni proposti ti convincono, o ne sceglieresti altri? Perche'?
- Cosa cerchi qui dentro che non trovi?

### 2. Scheda del punto vendita (15 min)

Quello che serve avere sotto gli occhi mentre si telefona.

- Immagina di avere il direttore al telefono adesso: questa pagina ti basta?
- Cosa ti serve sapere di un negozio che qui non c'e'?
- Gli stati della richiesta sono i tuoi, o ne usi altri?

### 3. Campagne (10 min)

Inserimento manuale delle date di un volantino: insegna, titolo, due date.

- Chi lo farebbe, e quando? Una volta a settimana e' sostenibile?
- Le date dei volantini dove le trovate oggi?
- Vi capita che negozi della stessa insegna abbiano volantini diversi?
- La campagna si limita scegliendo le province. **Quando un volantino non vale
  per tutta Italia, fin dove arriva?** Se rispondono con un nome che non e' una
  provincia ("il Tirreno", "la cooperativa di qui"), quello e' il
  raggruppamento vero e va annotato: e' il dato che serve per sostituire le
  province con le aree promozionali.

### 4. Banchetti (10 min)

Registro della raccolta e confronto fra raccolto con e senza promozione.

- Chi compila la scheda, e quando? Sul posto o il giorno dopo?
- Il raccolto in euro lo sapete sempre, o a volte contate altro?
- C'e' qualcosa che ricordate sempre di un banchetto andato bene, che qui non
  si puo' scrivere?

## Cosa osservare senza chiedere

Le risposte a voce sono gentili, quello che fanno le mani no.

| Segnale | Cosa significa |
|---|---|
| Pausa prima di cliccare | Un'etichetta scritta male, non una persona lenta |
| Come chiamano le cose | Se dicono "negozio" e la pagina dice "punto vendita", vince la loro parola |
| Tornano indietro a cercare un dato | Quel dato va spostato dove l'hanno cercato |
| "Questo me lo segno a parte" | Manca un campo. E' il segnale piu' prezioso |
| Schermate che non aprono mai | Non servono, oppure non si capisce che esistono |

## Cosa dire che manca, prima che lo chiedano

- **Serve un accesso.** Ognuna ha email e password sue: non si presta, e
  quando una volontaria smette si disattiva il suo accesso.
- **I dati sono inventati.**
- **Le campagne si inseriscono a mano**: la raccolta automatica e' ferma in
  attesa di verificare robots.txt e condizioni d'uso delle catene.
- **I punteggi dei giorni sono una stima**, non una misura. Si correggeranno
  con i dati veri di `stall_events`.
- **Non si cancella niente**: si corregge lo stato di una richiesta, non la si
  elimina.

## Dopo la sessione

Le risposte che contano per la prossima iterazione:

1. Cosa hanno cercato e non c'era.
2. Con quali parole chiamano le cose.
3. Dove hanno preso carta e penna.
4. La sola cosa che cambierebbero per prima.
