<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;

$radice = dirname(__DIR__);

// Autoload PSR-4 sul namespace App\ senza dipendenze esterne.
spl_autoload_register(static function (string $classe) use ($radice): void {
    if (!str_starts_with($classe, 'App\\')) {
        return;
    }
    $file = $radice . '/app/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

Config::carica(dirname($radice) . '/.env');

session_start();

// Ogni POST deve portare un token valido: nessuna eccezione.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::verifica($_POST['_csrf'] ?? null)) {
    http_response_code(419);
    View::rendi('errore', [
        'titoloPagina' => 'Sessione scaduta',
        'titolo' => 'Sessione scaduta',
        'messaggio' => 'Ricarica la pagina e riprova a inviare il modulo.',
    ]);
    return;
}

$router = new Router();

$router->get('/', [new App\Controllers\AgendaController(), 'index'](...));
$router->get('/punti-vendita', [new App\Controllers\StoreController(), 'elenco'](...));
$router->get('/punti-vendita/{id}', [new App\Controllers\StoreController(), 'scheda'](...));
$router->get('/campagne', [new App\Controllers\CampagneController(), 'index'](...));
$router->get('/banchetti', [new App\Controllers\BanchettiController(), 'index'](...));

$router->post('/campagne', [new App\Controllers\CampagneController(), 'crea'](...));
$router->post('/banchetti', [new App\Controllers\BanchettiController(), 'crea'](...));
$router->post('/richieste', [new App\Controllers\OutreachController(), 'crea'](...));
$router->post('/richieste/{id}', [new App\Controllers\OutreachController(), 'aggiorna'](...));

try {
    $router->esegui($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    http_response_code(500);
    error_log((string) $e);
    View::rendi('errore', [
        'titoloPagina' => 'Errore',
        'titolo' => 'Qualcosa non ha funzionato',
        // Il dettaglio finisce nei log, non davanti all'utente.
        'messaggio' => 'Riprova fra poco. Se il problema resta, segnalalo a chi cura il sistema.',
    ]);
}
