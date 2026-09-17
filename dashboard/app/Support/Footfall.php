<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Stima dell'affluenza attesa, giorno per giorno.
 *
 * Porting dei pesi di crawler/src/osservatorio/analysis/footfall.py, che resta
 * il riferimento: se si cambiano i pesi vanno cambiati in entrambi i punti.
 * La parita' fra le due implementazioni e' verificata da dashboard/tests.
 *
 * I pesi sono un'ipotesi di partenza, non una misura: vanno ricalibrati sui
 * dati veri di stall_events (vedi la vista v_resa_punti_vendita).
 */
final class Footfall
{
    public const PESO_TIPOLOGIA = [
        'ipermercato'    => 1.00,
        'superstore'     => 0.85,
        'discount'       => 0.70,
        'supermercato'   => 0.65,
        'cash_and_carry' => 0.40,
        'superette'      => 0.35,
    ];
    public const PESO_TIPOLOGIA_IGNOTA = 0.50;

    /** Indici 1 = lunedi ... 7 = domenica, come DateTime::format('N'). */
    public const PESO_GIORNO = [1 => 0.55, 2 => 0.55, 3 => 0.60, 4 => 0.65, 5 => 0.85, 6 => 1.00, 7 => 0.60];

    public const PESO_LANCIO = 1.00;
    public const PESO_CENTRALE = 0.80;
    public const PESO_CHIUSURA = 0.90;
    public const PESO_SENZA_PROMO = 0.55;

    public const GIORNI_LANCIO = 3;
    public const GIORNI_CHIUSURA = 2;

    private const NOMI_GIORNI = [1 => 'lunedì', 2 => 'martedì', 3 => 'mercoledì',
        4 => 'giovedì', 5 => 'venerdì', 6 => 'sabato', 7 => 'domenica'];

    /** @return array{0: float, 1: string} peso e descrizione della fase */
    private static function fase(
        DateTimeImmutable $giorno,
        ?DateTimeImmutable $inizio,
        ?DateTimeImmutable $fine
    ): array {
        if ($inizio === null || $fine === null || $giorno < $inizio || $giorno > $fine) {
            return [self::PESO_SENZA_PROMO, 'nessuna promozione'];
        }
        if ((int) $inizio->diff($giorno)->days < self::GIORNI_LANCIO) {
            return [self::PESO_LANCIO, 'lancio campagna'];
        }
        if ((int) $giorno->diff($fine)->days < self::GIORNI_CHIUSURA) {
            return [self::PESO_CHIUSURA, 'chiusura campagna'];
        }
        return [self::PESO_CENTRALE, 'campagna in corso'];
    }

    public static function punteggio(
        DateTimeImmutable $giorno,
        ?string $tipologia = null,
        ?DateTimeImmutable $inizio = null,
        ?DateTimeImmutable $fine = null
    ): float {
        $pesoTipo = self::PESO_TIPOLOGIA[$tipologia] ?? self::PESO_TIPOLOGIA_IGNOTA;
        $pesoGiorno = self::PESO_GIORNO[(int) $giorno->format('N')];
        [$pesoFase] = self::fase($giorno, $inizio, $fine);

        return round(100 * $pesoTipo * $pesoGiorno * $pesoFase, 1);
    }

    /**
     * I giorni migliori dentro una finestra promozionale, dal piu' promettente.
     *
     * @return list<array{data: DateTimeImmutable, punteggio: float, fase: string, giorno: string}>
     */
    public static function giorniConsigliati(
        DateTimeImmutable $inizio,
        DateTimeImmutable $fine,
        ?string $tipologia = null,
        int $quanti = 3
    ): array {
        if ($fine < $inizio) {
            return [];
        }

        $candidati = [];
        for ($g = $inizio; $g <= $fine; $g = $g->modify('+1 day')) {
            [, $fase] = self::fase($g, $inizio, $fine);
            $candidati[] = [
                'data' => $g,
                'punteggio' => self::punteggio($g, $tipologia, $inizio, $fine),
                'fase' => $fase,
                'giorno' => self::NOMI_GIORNI[(int) $g->format('N')],
            ];
        }

        usort($candidati, static fn (array $a, array $b): int
            => [$b['punteggio'], $a['data']] <=> [$a['punteggio'], $b['data']]);

        return array_slice($candidati, 0, max(1, $quanti));
    }

    /** Solo per l'evidenza grafica: da che punteggio in su vale la pena andare. */
    public static function livello(float $punteggio): string
    {
        return match (true) {
            $punteggio >= 80 => 'alto',
            $punteggio >= 55 => 'medio',
            default => 'basso',
        };
    }
}
