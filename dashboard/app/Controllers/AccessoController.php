<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Percorsi;
use App\Core\View;
use App\Models\UserRepository;

/**
 * Accesso e uscita.
 *
 * Il messaggio di errore e' sempre lo stesso quando le credenziali non tornano,
 * qualunque sia il motivo: altrimenti la pagina di accesso diventa un modo per
 * sapere chi ha un account. Gli unici messaggi diversi sono quelli che si
 * raggiungono solo conoscendo gia' la password giusta.
 */
final class AccessoController
{
    public function mostra(): void
    {
        if (Auth::autenticata()) {
            header('Location: ' . Percorsi::a('/'));
            return;
        }

        $this->rendi(null, Auth::percorsoSicuro($_GET['ritorno'] ?? null));
    }

    public function entra(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ritorno = Auth::percorsoSicuro($_POST['ritorno'] ?? null);

        if ($email === '' || $password === '') {
            $this->rendi('credenziali', $ritorno);
            return;
        }

        $utente = UserRepository::trovaPerEmail($email);

        if ($utente === null) {
            // Si calcola comunque un hash, per non rispondere piu' in fretta
            // quando l'email non esiste: il tempo di risposta direbbe chi ha
            // un account. Si usa PASSWORD_DEFAULT, lo stesso con cui vengono
            // create le password, invece di un hash fisso nel codice: un hash
            // fisso resta al costo del giorno in cui e' stato generato, e se
            // il PHP di produzione ne usa un altro la difesa si rovescia
            // nell'oracolo che doveva chiudere.
            password_hash($password, PASSWORD_DEFAULT);
            $this->rendi('credenziali', $ritorno);
            return;
        }

        if (!password_verify($password, (string) $utente['password_hash'])) {
            UserRepository::registraFallimento((int) $utente['id']);
            $this->rendi('credenziali', $ritorno);
            return;
        }

        // Da qui in giu' la password e' quella giusta: questi messaggi non
        // rivelano nulla a chi sta tirando a indovinare.
        if (UserRepository::bloccato($utente)) {
            $this->rendi('bloccato', $ritorno);
            return;
        }

        if ((int) $utente['is_active'] !== 1) {
            $this->rendi('revocato', $ritorno);
            return;
        }

        // L'algoritmo predefinito di PHP cambia col tempo: si riallinea qui,
        // nell'unico momento in cui la password in chiaro e' disponibile.
        if (password_needs_rehash((string) $utente['password_hash'], PASSWORD_DEFAULT)) {
            UserRepository::aggiornaHash((int) $utente['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        UserRepository::registraAccesso((int) $utente['id']);
        Auth::accedi($utente);

        header('Location: ' . Percorsi::a($ritorno));
    }

    public function esci(): void
    {
        Auth::esci();
        header('Location: ' . Percorsi::a('/accesso') . '?uscita=1');
    }

    private function rendi(?string $errore, string $ritorno): void
    {
        if ($errore !== null) {
            http_response_code(401);
        }

        View::rendi('accesso/index', [
            'titoloPagina' => 'Accesso',
            'errore' => $errore,
            'ritorno' => $ritorno,
            'uscita' => isset($_GET['uscita']),
            'revocato' => isset($_GET['revocato']),
            'minutiBlocco' => UserRepository::MINUTI_BLOCCO,
        ]);
    }
}
