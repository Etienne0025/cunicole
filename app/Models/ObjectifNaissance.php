<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObjectifNaissance extends Model
{
    protected $table = 'objectifs_naissances';

    protected $fillable = ['annee', 'mois', 'objectif'];
}