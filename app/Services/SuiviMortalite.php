<?php

namespace App\Services;

use App\Enums\CauseMortalite;
use App\Enums\CibleMortalite;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Models\LotEngraissement;
use App\Models\Mortalite;
use App\Models\Portee;
use App\Models\Reproducteur;
use App\Models\Utilisateur;
use App\Notifications\AlerteMortalite;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SuiviMortalite
{
    public function __construct(
        private PlacementCages $placement,
        private CycleReproduction $cycle,
    ) {}

    public function declarer(
        Model $cible,
        int $nombre,
        CarbonInterface $date,
        CauseMortalite $cause,
        ?string $precisions,
        Utilisateur $par,
    ): Mortalite {
        if ($date->isFuture()) {
            $this->refuser('date_deces', 'La date du décès ne peut pas être dans le futur.');
        }

        $mortalite = DB::transaction(fn () => match (true) {
            $cible instanceof Reproducteur => $this->reproducteur($cible, $date, $cause, $precisions, $par),
            $cible instanceof LotEngraissement => $this->lot($cible, $nombre, $date, $cause, $precisions, $par),
            $cible instanceof Portee => $this->portee($cible, $nombre, $date, $cause, $precisions, $par),
            default => $this->refuser('action', 'Élément inconnu.'),
        });

        $this->verifierSeuil();

        return $mortalite;
    }

    private function reproducteur(Reproducteur $cible, CarbonInterface $date, CauseMortalite $cause, ?string $precisions, Utilisateur $par): Mortalite
    {
        $animal = Reproducteur::lockForUpdate()->findOrFail($cible->id);

        if (! $animal->estActif()) {
            $this->refuser('action', "Cet animal n'est plus dans le cheptel actif.");
        }
        if ($date->lt($animal->date_entree)) {
            $this->refuser('date_deces', "La date ne peut pas précéder l'entrée de l'animal dans le cheptel.");
        }

        // Libère la cage (elle passera « à nettoyer ») et interrompt les saillies en cours
        $this->placement->retirer($animal, $par, 'Décès');
        $this->cycle->interrompreSaillies($animal);

        $animal->update([
            'statut' => StatutReproducteur::Mort,
            'date_sortie' => $date,
            'motif_sortie' => 'Décès : '.$cause->libelle(),
        ]);

        return Mortalite::create([
            'cible' => CibleMortalite::Reproducteur,
            'reproducteur_id' => $animal->id,
            'code_cible' => $animal->matricule,
            'nombre' => 1,
            'date_deces' => $date,
            'cause' => $cause,
            'precisions' => $precisions,
            'utilisateur_id' => $par->id,
        ]);
    }

    private function lot(LotEngraissement $cible, int $nombre, CarbonInterface $date, CauseMortalite $cause, ?string $precisions, Utilisateur $par): Mortalite
    {
        $lot = LotEngraissement::lockForUpdate()->findOrFail($cible->id);

        if ($lot->statut !== StatutLot::EnCours) {
            $this->refuser('action', 'Ce lot est terminé.');
        }
        if ($nombre > $lot->effectif_actuel) {
            $this->refuser('nombre', "Le lot ne compte que {$lot->effectif_actuel} lapin(s).");
        }
        if ($date->lt($lot->date_debut)) {
            $this->refuser('date_deces', 'La date ne peut pas précéder la création du lot.');
        }

        $reste = $lot->effectif_actuel - $nombre;

        $lot->update([
            'effectif_actuel' => $reste,
            'statut' => $reste === 0 ? StatutLot::Termine : StatutLot::EnCours,
        ]);

        // Lot vide : la cage passe « à nettoyer »
        if ($reste === 0 && $lot->cage) {
            $this->placement->actualiser($lot->cage);
        }

        return Mortalite::create([
            'cible' => CibleMortalite::Lot,
            'lot_id' => $lot->id,
            'code_cible' => $lot->code,
            'nombre' => $nombre,
            'date_deces' => $date,
            'cause' => $cause,
            'precisions' => $precisions,
            'utilisateur_id' => $par->id,
        ]);
    }

    private function portee(Portee $cible, int $nombre, CarbonInterface $date, CauseMortalite $cause, ?string $precisions, Utilisateur $par): Mortalite
    {
        $portee = Portee::with('saillie.femelle')->lockForUpdate()->findOrFail($cible->id);

        if ($portee->saillie->statut !== StatutSaillie::MiseBas) {
            $this->refuser('action', 'Les décès avant sevrage ne concernent que les portées non encore sevrées.');
        }

        $vivants = $portee->vivantsActuels();

        if ($nombre > $vivants) {
            $this->refuser('nombre', "La portée ne compte que {$vivants} lapereau(x) vivant(s).");
        }
        if ($date->lt($portee->date_mise_bas)) {
            $this->refuser('date_deces', 'La date ne peut pas précéder la mise bas.');
        }

        $portee->update(['morts_avant_sevrage' => $portee->morts_avant_sevrage + $nombre]);

        return Mortalite::create([
            'cible' => CibleMortalite::Portee,
            'portee_id' => $portee->id,
            'code_cible' => 'Portée de '.$portee->saillie->femelle->matricule,
            'nombre' => $nombre,
            'date_deces' => $date,
            'cause' => $cause,
            'precisions' => $precisions,
            'utilisateur_id' => $par->id,
        ]);
    }

    /**
     * Taux = décès de la période ÷ (effectif vivant + décès de la période).
     *
     * @return array{deces: int, effectif: int, taux: ?float, seuil: float, depasse: bool, jours: int}
     */
    public function taux(?int $jours = null): array
    {
        $jours ??= (int) config('cunicole.sante.periode_mortalite_jours', 30);
        $seuil = (float) config('cunicole.sante.seuil_mortalite_pct', 5);
        $depuis = today()->subDays($jours - 1)->toDateString();

        $deces = (int) Mortalite::where('date_deces', '>=', $depuis)->sum('nombre');
        $vivants = $this->effectifVivant();
        $base = $vivants + $deces;
        $taux = $base > 0 ? round($deces / $base * 100, 1) : null;

        return [
            'deces' => $deces,
            'effectif' => $vivants,
            'taux' => $taux,
            'seuil' => $seuil,
            'depasse' => $taux !== null && $taux > $seuil,
            'jours' => $jours,
        ];
    }

    /** Retourne le détail du taux uniquement si le seuil critique est dépassé. */
    public function alerte(): ?array
    {
        $etat = $this->taux();

        return $etat['depasse'] ? $etat : null;
    }

    public function effectifVivant(): int
    {
        $reproducteurs = Reproducteur::where('statut', StatutReproducteur::Actif->value)->count();
        $lots = (int) LotEngraissement::where('statut', StatutLot::EnCours->value)->sum('effectif_actuel');
        $lapereaux = Portee::whereHas('saillie', fn ($q) => $q->where('statut', StatutSaillie::MiseBas->value))
            ->get()
            ->sum(fn (Portee $p) => $p->vivantsActuels());

        return $reproducteurs + $lots + $lapereaux;
    }

    /** Prévient les propriétaires, au plus une fois toutes les 24 heures. */
    private function verifierSeuil(): void
    {
        $etat = $this->alerte();

        if ($etat === null || ! Cache::add('alerte_mortalite', true, now()->addHours(24))) {
            return;
        }

        $proprietaires = Utilisateur::where('role', 'proprietaire')->where('actif', true)->get();

        Notification::send($proprietaires, new AlerteMortalite($etat));
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}