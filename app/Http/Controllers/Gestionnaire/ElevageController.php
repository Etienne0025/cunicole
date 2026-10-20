<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\StatutSaillie;
use App\Http\Controllers\Controller;
use App\Models\Saillie;
use App\Services\PlanningElevage;

class ElevageController extends Controller
{
    public function __invoke(PlanningElevage $planning)
    {
        return view('gestionnaire.elevage.index', [
            'planning' => $planning->pour(),
            'gestantes' => Saillie::where('statut', StatutSaillie::Gestante->value)->count(),
        ]);
    }
}