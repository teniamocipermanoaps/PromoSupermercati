<?php

declare(strict_types=1);

namespace App\Core;

/** Router minimo: metodo + percorso esatto, con segnaposto {id} numerici. */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $rotte = ['GET' => [], 'POST' => []];

    public function get(string $percorso, callable $azione): void
    {
        $this->rotte['GET'][$percorso] = $azione;
    }

    public function post(string $percorso, callable $azione): void
    {
        $this->rotte['POST'][$percorso] = $azione;
    }

    public function esegui(string $metodo, string $uri): void
    {
        $percorso = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
        $metodo = strtoupper($metodo);

        foreach ($this->rotte[$metodo] ?? [] as $schema => $azione) {
            $regex = '#^' . preg_replace('#\{id\}#', '(\d+)', $schema) . '$#';
            if (preg_match($regex, $percorso, $trovati)) {
                array_shift($trovati);
                $azione(...array_map('intval', $trovati));
                return;
            }
        }

        http_response_code(404);
        View::rendi('errore', ['titolo' => 'Pagina non trovata', 'messaggio' => 'Il percorso ' . $percorso . ' non esiste.']);
    }
}
