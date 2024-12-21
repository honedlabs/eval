<?php

use Conquest\Evaluate\Evaluate;

it('evaluates the application', function () {
    $evaluation = Evaluate::measure()->terminate();
    expect($evaluation->getMemory())->toBeFloat();
    expect($evaluation->getDuration())->toBeFloat();
    expect($evaluation->getCost())->toBeFloat();
    expect($evaluation->getCount())->toBeNull();
    expect($evaluation->getProperties())->toBeNull();
    expect($evaluation->getMethods())->toBeNull();
});

it('evaluates an object', function () {
    $object = new class
    {
        public array $range;

        public function __construct()
        {
            $this->range = range(1, 10);
        }

        public function getRange(): array
        {
            return $this->range;
        }
    };
    $evaluation = Evaluate::measure($object)->terminate();
    expect($evaluation->getMemory())->toBeFloat();
    expect($evaluation->getDuration())->toBeFloat();
    expect($evaluation->getCost())->toBeFloat();
    expect($evaluation->getCount())->toBeNull();
    expect($evaluation->getProperties())->toBe(1);
    expect($evaluation->getMethods())->toBe(1);
});

it('evaluates an array', function () {
    $arr = range(1, 100);
    $evaluation = Evaluate::measure($arr)->terminate();
    expect($evaluation->getMemory())->toBeFloat();
    expect($evaluation->getDuration())->toBeFloat();
    expect($evaluation->getCost())->toBeFloat();
    expect($evaluation->getCount())->toBe(100);
    expect($evaluation->getProperties())->toBeNull();
    expect($evaluation->getMethods())->toBeNull();
});

it('evaluates a string', function () {
    $primitive = 'Hello, World!';
    $evaluation = Evaluate::measure($primitive)->terminate();
    expect($evaluation->getMemory())->toBeFloat();
    expect($evaluation->getDuration())->toBeFloat();
    expect($evaluation->getCost())->toBeFloat();
    expect($evaluation->getCount())->toBeNull();
    expect($evaluation->getProperties())->toBeNull();
    expect($evaluation->getMethods())->toBeNull();
});
