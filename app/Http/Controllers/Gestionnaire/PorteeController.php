<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\Cage;
use App\Models\Saillie;
use App\Services\CycleReproduction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PorteeController extends Controller
{
    public function miseBas(Request $request, Saillie $saillie, CycleReproduction $cycle)
    {
        $donnees = $request->validate([
            'date_mise_bas' => ['required', 'date', 'before_or_equal:today'],
            'nes_vivants' => ['required', 'integer', 'min:0', 'max:30'],
            'morts_nes' => ['required', 'integer', 'min:0', 'max:30'],
        ], [], [
            'date_mise_bas' => 'date de mise bas',
            'nes_vivants' => 'nés vivants',
            'morts_nes' => 'morts-nés',
        ]);

        $cycle->enregistrerMiseBas(
            $saillie,
            Carbon::parse($donnees['date_mise_bas']),
            (int) $donnees['nes_vivants'],
            (int) $donnees['morts_nes'],
            $request->user(),
        );

        return back()->with('status', 'Mise bas enregistrée. Sevrage prévu le '.$saillie->datePrevue('sevrage')->format('d/m/Y').'.');
    }

    public function sevrage(Request $request, Saillie $saillie, CycleReproduction $cycle)
    {
        $donnees = $request->validate([
            'date_sevrage' => ['required', 'date', 'before_or_equal:today'],
            'nombre_sevres' => ['required', 'integer', 'min:0', 'max:30'],
            'cage_id' => ['nullable', 'required_unless:nombre_sevres,0', 'exists:cages,id'],
        ], [], [
            'date_sevrage' => 'date de sevrage',
            'nombre_sevres' => 'nombre de sevrés',
            'cage_id' => 'cage',
        ]);

        $lot = $cycle->sevrer(
            $saillie,
            Carbon::parse($donnees['date_sevrage']),
            (int) $donnees['nombre_sevres'],
            ! empty($donnees['cage_id']) ? Cage::findOrFail($donnees['cage_id']) : null,
        );

        return back()->with('status', $lot
            ? "Sevrage enregistré. Lot {$lot->code} créé dans la cage {$lot->cage->code}."
            : 'Portée clôturée sans lot (aucun lapereau sevré).');
    }
}