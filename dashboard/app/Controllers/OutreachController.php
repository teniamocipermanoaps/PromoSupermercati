<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Percorsi;

use App\Models\OutreachRepository;

final class OutreachController
{
    public function crea(): void
    {
        $storeId = (int) ($_POST['store_id'] ?? 0);
        $dal = (string) ($_POST['target_date_from'] ?? '');
        $al = (string) ($_POST['target_date_to'] ?? '');

        // Il modulo ha gia' i campi obbligatori, ma una POST puo' arrivare da
        // qualunque parte: quello che finisce nel database va validato qui.
        if ($storeId <= 0 || trim((string) ($_POST['requested_by'] ?? '')) === ''
            || $dal === '' || $al === '' || $al < $dal) {
            self::tornaAllaScheda($storeId, 'dati-non-validi');
            return;
        }

        OutreachRepository::crea($_POST);
        self::tornaAllaScheda($storeId, 'richiesta-creata');
    }

    public function aggiorna(int $id): void
    {
        $richiesta = OutreachRepository::trova($id);
        if ($richiesta === null) {
            http_response_code(404);
            echo 'Richiesta non trovata';
            return;
        }

        OutreachRepository::aggiorna($id, $_POST);
        self::tornaAllaScheda((int) $richiesta['store_id'], 'richiesta-aggiornata');
    }

    private static function tornaAllaScheda(int $storeId, string $esito): void
    {
        header('Location: ' . Percorsi::a('/punti-vendita/' . $storeId) . '?esito=' . $esito);
    }
}
