<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;

class PlusController extends Controller
{
    public function __invoke()
    {
        return view('gestionnaire.plus');
    }
}