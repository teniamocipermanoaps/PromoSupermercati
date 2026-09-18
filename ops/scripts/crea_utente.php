<?php

/**
 * Crea o reimposta un accesso alla dashboard.
 *
 *   php ops/scripts/crea_utente.php maria@esempio.it "Maria Rossi"
 *   php ops/scripts/crea_utente.php maria@esempio.it "Maria Rossi" amministratrice
 *
 * La password si digita a schermo spento. Per uso non interattivo (demo.sh):
 *
 *   UTENTE_PASSWORD='...' php ops/scripts/crea_utente.php ...
 *
 * Su un pannello senza accesso SSH (Plesk, Attivita' pianificate) la password
 * non va scritta nella definizione dell'attivita', perche' resta li'. Si mette
 * in un file, si esegue, si cancella il file:
 *
 *   UTENTE_PASSWORD_FILE=/percorso/segreto.txt php ops/scripts/crea_utente.php ...
 *
 * Se l'email esiste gia' la password viene reimpostata e l'accesso riattivato:
 * e' la via di recupero, perche' non esiste un "password dimenticata" via email.
 *
 * Non esiste un utente predefinito nei seed, di proposito: l'hash di una
 * password nota dentro un file versionato e' una password nota in produzione.
 */

declare(strict_types=1);

use App\Core\Config;
use App\Models\UserRepository;

$radice = dirname(__DIR__, 2);
$dashboard = $radice . '/dashboard';

spl_autoload_register(static function (string $classe) use ($dashboard): void {
    if (!str_starts_with($classe, 'App\\')) {
        return;
    }
    $file = $dashboard . '/app/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

Config::carica($radice . '/.env');

/** Lunghezza minima: contro gli elenchi di password comuni conta questa. */
const LUNGHEZZA_MINIMA = 12;

const RUOLI = ['segretaria', 'amministratrice'];

function esci_con_errore(string $messaggio): never
{
    fwrite(STDERR, 'Errore: ' . $messaggio . PHP_EOL);
    exit(1);
}

function password_non_interattiva(): ?string
{
    $daAmbiente = getenv('UTENTE_PASSWORD');
    if (is_string($daAmbiente) && $daAmbiente !== '') {
        return $daAmbiente;
    }

    $file = getenv('UTENTE_PASSWORD_FILE');
    if (!is_string($file) || $file === '') {
        return null;
    }
    if (!is_readable($file)) {
        esci_con_errore("il file della password non si legge: {$file}");
    }

    // Un file di password leggibile da tutti sul server e' una password
    // leggibile da tutti: meglio fermarsi che procedere in silenzio.
    $permessi = fileperms($file) & 0o077;
    if ($permessi !== 0) {
        esci_con_errore(sprintf(
            "il file %s e' leggibile da altri utenti (permessi %o). "
            . 'Dagli 600 e riprova.',
            $file,
            fileperms($file) & 0o777
        ));
    }

    $password = rtrim((string) file_get_contents($file), "\r\n");
    if ($password === '') {
        esci_con_errore("il file della password e' vuoto: {$file}");
    }
    return $password;
}

/** Legge una password senza mostrarla a schermo. */
function chiedi_password(string $richiesta): string
{
    $fuoriDalTerminale = password_non_interattiva();
    if ($fuoriDalTerminale !== null) {
        return $fuoriDalTerminale;
    }

    if (!stream_isatty(STDIN)) {
        esci_con_errore(
            'nessun terminale per digitare la password. Usa una di queste:' . PHP_EOL
            . "  UTENTE_PASSWORD='...' php ops/scripts/crea_utente.php ..." . PHP_EOL
            . '  UTENTE_PASSWORD_FILE=/percorso/segreto.txt php ops/scripts/crea_utente.php ...'
        );
    }

    fwrite(STDOUT, $richiesta);
    shell_exec('stty -echo');
    $password = rtrim((string) fgets(STDIN), "\r\n");
    shell_exec('stty echo');
    fwrite(STDOUT, PHP_EOL);

    return $password;
}

// ----------------------------------------------------------------------

$email = trim((string) ($argv[1] ?? ''));
$nome = trim((string) ($argv[2] ?? ''));
$ruolo = trim((string) ($argv[3] ?? 'segretaria'));

if ($email === '' || $nome === '') {
    fwrite(STDERR, "Uso: php ops/scripts/crea_utente.php <email> <nome e cognome> [ruolo]\n");
    fwrite(STDERR, 'Ruoli: ' . implode(', ', RUOLI) . " (predefinito: segretaria)\n");
    exit(1);
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    esci_con_errore("'{$email}' non e' un indirizzo email valido.");
}
if (!in_array($ruolo, RUOLI, true)) {
    esci_con_errore("ruolo '{$ruolo}' sconosciuto. Ruoli: " . implode(', ', RUOLI) . '.');
}

$esistente = UserRepository::trovaPerEmail($email);
$azione = $esistente === null ? 'Creo' : 'Reimposto';
fwrite(STDOUT, "{$azione} l'accesso di {$email}" . PHP_EOL);

$password = chiedi_password('Password (non viene mostrata): ');
if (strlen($password) < LUNGHEZZA_MINIMA) {
    esci_con_errore('la password deve essere lunga almeno ' . LUNGHEZZA_MINIMA . ' caratteri.');
}

// La conferma si chiede solo a chi sta digitando davvero.
if (password_non_interattiva() === null) {
    if (chiedi_password('Ripeti la password: ') !== $password) {
        esci_con_errore('le due password non coincidono.');
    }
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    if ($esistente === null) {
        UserRepository::crea($email, $nome, $hash, $ruolo);
        fwrite(STDOUT, "Accesso creato per {$nome} ({$ruolo}).\n");
    } else {
        UserRepository::reimpostaPassword((int) $esistente['id'], $hash);
        fwrite(STDOUT, "Password reimpostata per {$esistente['full_name']}; accesso riattivato e sbloccato.\n");
    }
} catch (Throwable $e) {
    esci_con_errore($e->getMessage());
}

fwrite(STDOUT, "La password non e' stata scritta da nessuna parte: consegnala a voce.\n");

if (getenv('UTENTE_PASSWORD_FILE')) {
    fwrite(STDOUT, 'Ora cancella ' . getenv('UTENTE_PASSWORD_FILE') . ": ha finito il suo lavoro.\n");
}
