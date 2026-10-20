<?php

use App\Models\Reproducteur;
use App\Services\Consanguinite;

function lienParente(Reproducteur $femelle, Reproducteur $male): ?string
{
    return app(Consanguinite::class)->lien($femelle, $male);
}

it('bloque père et fille', function () {
    $pere = Reproducteur::factory()->male()->create();
    $fille = Reproducteur::factory()->create(['pere_id' => $pere->id]);

    expect(lienParente($fille, $pere))->toBe('père et fille');
});

it('bloque mère et fils', function () {
    $mere = Reproducteur::factory()->create();
    $fils = Reproducteur::factory()->male()->create(['mere_id' => $mere->id]);

    expect(lienParente($mere, $fils))->toBe('mère et fils');
});

it('bloque frère et sœur', function () {
    $mere = Reproducteur::factory()->create();
    $pere = Reproducteur::factory()->male()->create();
    $soeur = Reproducteur::factory()->create(['mere_id' => $mere->id, 'pere_id' => $pere->id]);
    $frere = Reproducteur::factory()->male()->create(['mere_id' => $mere->id, 'pere_id' => $pere->id]);

    expect(lienParente($soeur, $frere))->toBe('frère et sœur');
});

it('bloque demi-frère et demi-sœur', function () {
    $mere = Reproducteur::factory()->create();
    $soeur = Reproducteur::factory()->create(['mere_id' => $mere->id]);
    $demiFrere = Reproducteur::factory()->male()->create(['mere_id' => $mere->id]);

    expect(lienParente($soeur, $demiFrere))->toBe('demi-frère et demi-sœur');
});

it('autorise deux animaux sans lien connu', function () {
    expect(lienParente(Reproducteur::factory()->create(), Reproducteur::factory()->male()->create()))->toBeNull();
});

it("n'étend pas le blocage aux grands-parents par défaut", function () {
    $grandPere = Reproducteur::factory()->male()->create();
    $pere = Reproducteur::factory()->male()->create(['pere_id' => $grandPere->id]);
    $petiteFille = Reproducteur::factory()->create(['pere_id' => $pere->id]);

    expect(lienParente($petiteFille, $grandPere))->toBeNull();
});

it('bloque les grands-parents quand le mode étendu est activé', function () {
    config(['cunicole.reproduction.consanguinite_etendue' => true]);

    $grandPere = Reproducteur::factory()->male()->create();
    $pere = Reproducteur::factory()->male()->create(['pere_id' => $grandPere->id]);
    $petiteFille = Reproducteur::factory()->create(['pere_id' => $pere->id]);

    expect(lienParente($petiteFille, $grandPere))->toBe('grand-parent et petit-enfant');
});

it('bloque les cousins quand le mode étendu est activé', function () {
    config(['cunicole.reproduction.consanguinite_etendue' => true]);

    $grandMere = Reproducteur::factory()->create();
    $mereA = Reproducteur::factory()->create(['mere_id' => $grandMere->id]);
    $mereB = Reproducteur::factory()->create(['mere_id' => $grandMere->id]);
    $cousine = Reproducteur::factory()->create(['mere_id' => $mereA->id]);
    $cousin = Reproducteur::factory()->male()->create(['mere_id' => $mereB->id]);

    expect(lienParente($cousine, $cousin))->toBe('cousins');
});