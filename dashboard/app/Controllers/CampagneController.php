<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\CampaignRepository;

final class CampagneController
{
    public function index(): void
    {
        View::rendi('campagne/index', [
            'titoloPagina' => 'Campagne promozionali',
            'campagne' => CampaignRepository::elenco(),
            'catene' => CampaignRepository::catene(),
            'errore' => $_GET['errore'] ?? null,
        ]);
    }

    public function crea(): void
    {
        $chainId = (int) ($_POST['chain_id'] ?? 0);
        $titolo = trim((string) ($_POST['title'] ?? ''));
        $dal = (string) ($_POST['valid_from'] ?? '');
        $al = (string) ($_POST['valid_to'] ?? '');

        if ($chainId <= 0 || $titolo === '' || $dal === '' || $al === '') {
            header('Location: /campagne?errore=campi-mancanti');
            return;
        }
        if ($al < $dal) {
            header('Location: /campagne?errore=date-invertite');
            return;
        }

        try {
            CampaignRepository::crea($chainId, $titolo, $dal, $al);
        } catch (\PDOException $e) {
            // Violazione della chiave di deduplica: campagna gia' inserita.
            $codice = $e->errorInfo[1] ?? 0;
            header('Location: /campagne?errore=' . ($codice === 1062 ? 'gia-presente' : 'errore-db'));
            return;
        }

        header('Location: /campagne');
    }
}
