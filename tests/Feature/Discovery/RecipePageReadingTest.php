<?php

use App\Discovery\RecipePageReading;

it('can only be made through a named constructor, so a reading with neither candidate nor refusal cannot exist', function () {
    $constructor = (new ReflectionClass(RecipePageReading::class))->getConstructor();

    expect($constructor->isPrivate())->toBeTrue();
});
