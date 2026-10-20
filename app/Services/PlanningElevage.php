<?php

namespace App\Services;

use App\Enums\StatutSaillie;
use App\Models\Saillie;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PlanningElevage
{
    /** @return array{palpations: Collection, boites_nids: Collection, mises_bas: Collection, sevrages: Collection} */
    public function pour(?CarbonInterface $jour = null): array
    {
        $jour = ($jour ?? today())->copy()->startOfDay();
        $c = config('cunicole.reproduction');

        $requete = fn (StatutSaillie $statut, int $jours) => Saillie::with(['femelle.cage', 'male'])
            ->where('statut', $statut->value)
            ->whereDate('date_saillie', '<=', $jour->copy()->subDays($jours))
            ->orderBy('date_saillie');

        return [
            'palpations' => $requete(StatutSaillie::EnAttente, $c['jours_palpation'])->get(),
            'boites_nids' => $requete(StatutSaillie::Gestante, $c['jours_boite_nids'])->whereNull('boite_nids_le')->get(),
            // Surveillance à partir de 3 jours avant la date prévue
            'mises_bas' => $requete(StatutSaillie::Gestante, $c['jours_mise_bas'] - 3)->get(),
            'sevrages' => $requete(StatutSaillie::MiseBas, $c['jours_sevrage'])->with('portee')->get(),
        ];
    }

    public function total(?CarbonInterface $jour = null): int
    {
        return collect($this->pour($jour))->sum(fn (Collection $liste) => $liste->count());
    }

    /** Femelles gestantes dont la mise bas est prévue dans les $jours prochains jours (ou déjà dépassée). */
    public function misesBasAttendues(int $jours = 7): Collection
    {
        $limite = today()->addDays($jours)->subDays(config('cunicole.reproduction.jours_mise_bas'));

        return Saillie::with(['femelle.cage', 'male'])
            ->where('statut', StatutSaillie::Gestante->value)
            ->whereDate('date_saillie', '<=', $limite)
            ->orderBy('date_saillie')
            ->get();
    }


    /** Taux d'échec des saillies sur les 90 derniers jours (saillies palpées, hors interrompues). */
public function tauxEchecSaillies(): array
{
    $depuis = today()->subDays(90)->toDateString();

    $palpees = Saillie::whereDate('date_saillie', '>=', $depuis)
        ->whereNotIn('statut', [StatutSaillie::EnAttente->value, StatutSaillie::Interrompue->value])
        ->count();

    $echecs = Saillie::whereDate('date_saillie', '>=', $depuis)
        ->where('statut', StatutSaillie::NonGestante->value)
        ->count();

    $taux = $palpees > 0 ? round($echecs / $palpees * 100, 1) : null;
    $seuil = (float) config('cunicole.reproduction.seuil_echec_saillies');

    return [
        'taux' => $taux,
        'palpees' => $palpees,
        'seuil' => $seuil,
        'alerte' => $taux !== null
            && $palpees >= config('cunicole.reproduction.echantillon_min_echec')
            && $taux > $seuil,
    ];
}
}