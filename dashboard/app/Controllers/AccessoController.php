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
    /**
     * Hash di una stringa casuale che nessuno conosce, verificato quando
     * l'email non esiste: cosi' il tentativo costa quanto quello su un'email
     * vera. Senza, il tempo di risposta direbbe chi ha un account.
     */
    private const HASH_FITTIZIO = '$2y$12$Xae37gWKjcKvVT16SkUpy.obt8k7JKbkgCdGkzhIvM0iQw9zUqaym';

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
            // Si verifica comunque, per non finire prima quando l'email non c'e'.
            password_verify($password, self::HASH_FITTIZIO);
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
            'minutiBlocco' => UserRepository::MINUTI_BLOCCO,
        ]);
    }
}
