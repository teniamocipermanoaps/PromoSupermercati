<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Percorsi;
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
            'province' => CampaignRepository::province(),
            'negozi' => isset($_GET['negozi']) ? (int) $_GET['negozi'] : null,
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
            header('Location: ' . Percorsi::a('/campagne') . '?errore=campi-mancanti');
            return;
        }
        if ($al < $dal) {
            header('Location: ' . Percorsi::a('/campagne') . '?errore=date-invertite');
            return;
        }

        // Solo sigle di due lettere maiuscole: quello che arriva dal modulo
        // non finisce mai in una query senza essere ripulito prima.
        $province = array_values(array_filter(
            array_map(
                static fn ($sigla): string => strtoupper(trim((string) $sigla)),
                (array) ($_POST['province'] ?? [])
            ),
            static fn (string $sigla): bool => preg_match('/^[A-Z]{2}$/', $sigla) === 1
        ));

        try {
            $flyerId = CampaignRepository::crea($chainId, $titolo, $dal, $al, [], $province);
        } catch (\PDOException $e) {
            // Violazione della chiave di deduplica: campagna gia' inserita.
            $codice = $e->errorInfo[1] ?? 0;
            header('Location: ' . Percorsi::a('/campagne') . '?errore=' . ($codice === 1062 ? 'gia-presente' : 'errore-db'));
            return;
        }

        $collegati = CampaignRepository::puntiCollegati($flyerId);
        header('Location: ' . Percorsi::a('/campagne') . '?esito=creata&negozi=' . $collegati);
    }
}
