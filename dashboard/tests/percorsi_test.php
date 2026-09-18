<?php

declare(strict_types=1);

/**
 * Prove su come si costruiscono e si leggono i percorsi.
 *
 *   php dashboard/tests/percorsi_test.php
 *
 * Due funzioni decidono dove finisce una persona dopo l'accesso, e sbagliarle
 * non da' un errore visibile: da' un giro di redirect a vuoto, oppure manda la
 * segretaria su un sito altrui che le chiede di nuovo la password.
 *
 * Non serve ne' database ne' server: sono funzioni pure.
 */

$radice = dirname(__DIR__);
spl_autoload_register(static function (string $classe) use ($radice): void {
    if (!str_starts_with($classe, 'App\\')) {
        return;
    }
    $file = $radice . '/app/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

use App\Core\Auth;
use App\Core\Percorsi;

$falliti = 0;
$passati = 0;

function uguale(string $descrizione, mixed $atteso, mixed $ottenuto): void
{
    global $falliti, $passati;
    if ($atteso === $ottenuto) {
        $passati++;
        return;
    }
    $falliti++;
    printf("  FALLITO %s\n          atteso  %s\n          ottenuto %s\n",
        $descrizione, var_export($atteso, true), var_export($ottenuto, true));
}

function rifiuta(string $descrizione, callable $azione): void
{
    global $falliti, $passati;
    try {
        $azione();
    } catch (Throwable $e) {
        $passati++;
        return;
    }
    $falliti++;
    printf("  FALLITO %s (accettato invece di essere rifiutato)\n", $descrizione);
}

function conBase(string $valore): void
{
    putenv('APP_BASE_PATH=' . $valore);
    Percorsi::dimentica();
}

// ============================================================
echo "== la base si legge da APP_BASE_PATH ==\n";

conBase('');
uguale('vuoto = radice del dominio', '', Percorsi::base());

conBase('/promosupermercati');
uguale('con la barra davanti', '/promosupermercati', Percorsi::base());

conBase('promosupermercati');
uguale('senza la barra davanti', '/promosupermercati', Percorsi::base());

conBase('/promosupermercati/');
uguale('con la barra finale', '/promosupermercati', Percorsi::base());

conBase('/gestionale/promosupermercati');
uguale('su due livelli', '/gestionale/promosupermercati', Percorsi::base());

// Un valore storto deve fermare l'applicazione subito, non produrre indirizzi
// strani in ogni pagina.
foreach (['../etc', 'promo supermercati', 'http://altrosito.it', 'promo?x=1', "promo\nx"] as $storto) {
    rifiuta("valore rifiutato: " . var_export($storto, true), static function () use ($storto) {
        conBase($storto);
        Percorsi::base();
    });
}

// ============================================================
echo "== dal percorso interno all'indirizzo scritto nella pagina ==\n";

conBase('');
uguale('radice: /',          '/',          Percorsi::a('/'));
uguale('radice: /campagne',  '/campagne',  Percorsi::a('/campagne'));

conBase('/promosupermercati');
uguale('sottocartella: /',           '/promosupermercati',           Percorsi::a('/'));
uguale('sottocartella: /campagne',   '/promosupermercati/campagne',  Percorsi::a('/campagne'));
uguale('sottocartella: /punti-vendita/1',
       '/promosupermercati/punti-vendita/1', Percorsi::a('/punti-vendita/1'));

// ============================================================
echo "== dall'indirizzo del browser al percorso interno ==\n";

conBase('');
uguale('radice: /',              '/',          Percorsi::interno('/'));
uguale('radice: /campagne',      '/campagne',  Percorsi::interno('/campagne'));
uguale('radice: barra finale',   '/campagne',  Percorsi::interno('/campagne/'));
uguale('radice: con parametri',  '/campagne',  Percorsi::interno('/campagne?errore=x'));

conBase('/promosupermercati');
uguale('sottocartella: prefisso nudo',   '/', Percorsi::interno('/promosupermercati'));
uguale('sottocartella: prefisso e barra','/', Percorsi::interno('/promosupermercati/'));
uguale('sottocartella: una pagina',      '/campagne',
       Percorsi::interno('/promosupermercati/campagne'));
uguale('sottocartella: con parametri',   '/campagne',
       Percorsi::interno('/promosupermercati/campagne?errore=x'));

// Il prefisso e' un segmento intero, non una stringa qualunque: /promosupermercatiX
// e' un altro sito, e togliergli il prefisso lo farebbe entrare qui dentro.
uguale('prefisso solo se segmento intero', '/promosupermercatiX',
       Percorsi::interno('/promosupermercatiX'));
uguale('fuori dall\'applicazione resta fuori', '/altro-sito',
       Percorsi::interno('/altro-sito'));

// ============================================================
echo "== dove si puo' tornare dopo l'accesso ==\n";

uguale('niente',                '/', Auth::percorsoSicuro(null));
uguale('vuoto',                 '/', Auth::percorsoSicuro(''));
uguale('la pagina di accesso',  '/', Auth::percorsoSicuro('/accesso'));
uguale('una pagina vera',       '/campagne', Auth::percorsoSicuro('/campagne'));
uguale('una scheda',            '/punti-vendita/12', Auth::percorsoSicuro('/punti-vendita/12'));

// Questi sono tentativi di mandare la segretaria altrove: su una copia della
// dashboard che le richiede la password.
$trappole = [
    '//evil.example.com',            // doppia barra: il browser lo legge come dominio
    '///evil.example.com',
    'https://evil.example.com',
    'http://evil.example.com',
    '/\\evil.example.com',           // barra rovesciata: alcuni browser la normalizzano
    'javascript:alert(1)',
    "/campagne\r\nSet-Cookie: x=y",  // iniezione di intestazioni
    "/campagne\nLocation: http://evil",
    '/ /evil',
    'campagne',                      // relativo: non comincia con /
];
foreach ($trappole as $trappola) {
    uguale('respinto: ' . var_export($trappola, true), '/', Auth::percorsoSicuro($trappola));
}

// ============================================================
echo "\n";
if ($falliti === 0) {
    echo "Tutte le prove superate ({$passati}).\n";
    exit(0);
}
echo "{$falliti} prove fallite, {$passati} superate.\n";
exit(1);
