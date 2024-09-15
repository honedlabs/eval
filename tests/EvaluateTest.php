<?php

use Conquest\Evaluate\Evaluate;

it('tests', function () {
    dd(Evaluate::new(fn () => sleep(1)));
});