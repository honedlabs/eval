<?php

namespace Conquest\Evaluate;

use Closure;
use Illuminate\Support\Arr;
use Conquest\Core\Concerns\HasName;

class Evaluate 
{
    use HasName;

    /**
     * Display memory consumption
     * 
     * @var int
     */
    const Memory = 1;

    /**
     * Display execution time
     * 
     * @var int
     */
    const Time = 2;

    /**
     * Display execution cost
     * 
     * @var int
     */
    const Cost = 4;

    /**
     * Display class properties and methods count
     * 
     * @var int
     */
    const Object = 8;

    /**
     * Display basic metrics
     * 
     * @var int
     */
    const Basic = self::Memory | self::Time | self::Cost;

    /**
     * Display all metrics
     * 
     * @var int
     */
    const All = self::Memory | self::Time | self::Cost | self::Object;

    /**
     * Code to be benchmarked
     * 
     * @var array<int, mixed>
     */
    protected $evaluations = [];

    /**
     * The metrics to be used for this evaluation
     * @var int
     */
    protected $metrics = self::Memory | self::Time | self::Cost;

    /**
     * Number of times to execute the evaluation
     * 
     * @var int
     */
    protected $times = 5;

    /**
     * Whether to display the peak memory usage
     * 
     * @var boolean
     */
    protected $peak = true;

    /**
     * The computed memory usage
     * 
     * @var float|null
     */
    protected $memory = null;

    /**
     * The computed execution time
     * 
     * @var float|null
     */
    protected $duration = null;

    public function __construct(mixed $evaluations = [], $metrics = self::Basic, $name = null, $times = 5, $peak = true)
    {
        $this->evaluations = $evaluations;
        $this->metrics = $metrics;
        $this->name = $name;
        $this->times = $times;
        $this->peak = $peak;
    }

    public static function new()
    {
        return resolve(static::class);
    }

    // public static function measure(Closure|array $benchmarkables, int $iterations = 1)
    // public static function measure(mixed $evaluations)
    // public static function dd
    // public static function log()
    // public static function dump()
    // public static function cost()
    // public static function memory()
    // public static function time()
    // public static function class() -> use reflection to compute number of properties and methods

    /**
     * @internal
     */
    protected function evaluate(Closure|array $benchmarkables, int $iterations = 1): array|float
    {
        return collect(Arr::wrap($benchmarkables))->map(function ($callback) use ($iterations) {
            return collect(range(1, $iterations))->map(function () use ($callback) {
                gc_collect_cycles();

                $start = hrtime(true);

                $callback();

                return (hrtime(true) - $start) / 1000000;
            })->average();
        });
    }

    public function __destruct()
    {
        return $this->print();
    }
    // public function count(iterable $iterable): int
    // {
    //     return count($iterable);
    // }

    /**
     * 
     */
    protected function getMemory($evaluation)
    {
        return $this->memory ??= $this->computeMemory($evaluation);
    }

    protected function computeMemory($evaluation)
    {
        return memory_get_usage(true);
    }

    protected function print()
    {
        return '';

    }


}
