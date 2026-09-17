<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\OutreachRepository;
use App\Models\StoreRepository;
use App\Support\Footfall;
use DateTimeImmutable;

final class StoreController
{
    public function elenco(): void
    {
        View::rendi('store/index', [
            'titoloPagina' => 'Punti vendita',
            'punti' => StoreRepository::elenco(),
        ]);
    }

    public function scheda(int $id): void
    {
        $punto = StoreRepository::trova($id);
        if ($punto === null) {
            http_response_code(404);
            View::rendi('errore', [
                'titoloPagina' => 'Non trovato',
                'titolo' => 'Punto vendita non trovato',
                'messaggio' => 'Nessun punto vendita con identificativo ' . $id . '.',
            ]);
            return;
        }

        $campagne = StoreRepository::campagne($id);
        foreach ($campagne as &$campagna) {
            $campagna['giorni'] = Footfall::giorniConsigliati(
                new DateTimeImmutable((string) $campagna['inizio']),
                new DateTimeImmutable((string) $campagna['fine']),
                (string) $punto['tipologia'],
                3
            );
        }
        unset($campagna);

        View::rendi('store/show', [
            'titoloPagina' => (string) $punto['name'],
            'punto' => $punto,
            'contatti' => StoreRepository::contatti($id),
            'campagne' => $campagne,
            'richieste' => StoreRepository::richieste($id),
            'banchetti' => StoreRepository::banchetti($id),
            'resa' => StoreRepository::resa($id),
            'stati' => OutreachRepository::STATI,
            'canali' => OutreachRepository::CANALI,
        ]);
    }
}
