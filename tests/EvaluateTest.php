<?php

use Conquest\Evaluate\Evaluate;

it('evaluates a callable', function () {
    dd(
        Evaluate::new(fn () => range(1, 10000000))
    );
});

// it('calculates metrics for an object', function () {
//     $object = new class {
//         public array $range;
//         public function __construct()
//         {
//             $this->range = range(1, 10000000);
//         }
//     };
//     dd(Evaluate::new($object));
// });

// it('calculates metrics for a array', function () {
//     $primitive = range(1, 1000000);
//     dd(Evaluate::new([$primitive]));
// });

// it('calculates metrics for a primitive', function () {
//     $primitive = 1;
//     dd(Evaluate::new($primitive));
// });