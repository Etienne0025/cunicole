<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batiment extends Model
{
    use HasFactory;

    protected $table = 'batiments';

    protected $fillable = ['nom', 'description'];

    public function cages(): HasMany
    {
        return $this->hasMany(Cage::class);
    }
}