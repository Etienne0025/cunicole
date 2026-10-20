<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\OperationSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SynchronisationController extends Controller
{
    public function index(Request $request)
    {
        return view('gestionnaire.synchronisation', [
            'journal' => OperationSync::where('utilisateur_id', $request->user()->id)->latest('id')->limit(30)->get(),
        ]);
    }

    /** Jeton de sécurité à jour : celui d'une page ancienne peut ne plus être valable. */
    public function jeton(): JsonResponse
    {
        return response()->json(['jeton' => csrf_token()])->header('Cache-Control', 'no-store');
    }
}