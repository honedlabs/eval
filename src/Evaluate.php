<?php

namespace Conquest\Evaluate;

use Closure;
use Traversable;
use ReflectionClass;
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
     * @var array<int, \Closure|object|array|callable>
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
     * The computed memory usage in MB
     * 
     * @var float|null
     */
    protected $memory = null;

    /**
     * The computed execution time in ms
     * 
     * @var float|null
     */
    protected $duration = null;

    /**
     * The number of properties
     * 
     * @var int|null    
     */
    protected $properties = null;

    /**
     * The number of methods
     * 
     * @var int|null
     */
    protected $methods = null;

    /**
     * The count of the number of items
     * 
     * @var int|null
     */
    protected $count = null;

    /**
     * Create a new evaluation instance
     * 
     * @param array<int, \Closure|object|array|callable> $evaluations
     * @param int $metrics
     * @param string|null $name
     * @param int $times
     */
    public function __construct($evaluations = [], $metrics = self::Basic, $name = null, $times = 5)
    {
        $this->evaluations = $evaluations;
        $this->metrics = $metrics;
        $this->name = $name;
        $this->times = $times;

        $this->evaluate();
    }

    /**
     * Create a new evaluation instance
     * 
     * @param array<int, \Closure|object|array|callable> $evaluations
     * @param int $metrics
     * @param string|null $name
     * @param int $times
     */
    public static function new($evaluations = [], $metrics = self::Basic, $name = null, $times = 5)
    {
        return resolve(static::class, compact('evaluations', 'metrics', 'name', 'times'));
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
     * Evaluate the performance of the provided evaluations
     * 
     * @internal
     */
    protected function evaluate()
    {
        if (empty($this->evaluations)) {
            $this->evaluateApplication();
            return;
        }

        /** @var array<int, array<int|float>> */
        $results = collect(Arr::wrap($this->evaluations))->map(fn ($evaluation) => 
            collect(range(1, $this->times))->map(fn () => is_callable($evaluation) ? 
                $this->evaluateCallable($evaluation) 
                : $this->evaluateDataType($evaluation)
            )
        );

        // Compute 

        dd($results);
    }

    /**
     * Evaluate the performance of the application
     * 
     * @internal
     */
    protected function evaluateApplication()
    {
        $this->memory = $this->getMemory();
        // The start of the application uses the application time which is in microseconds
        // we need to convert this from microseconds to milliseconds to align with the other metrics
        $this->duration = round((microtime(true) - LARAVEL_START) * 1e3, 3);
    }

    /**
     * Evaluate the performance of a given callable
     * 
     * @internal
     * @return array<int, int|float>
     */
    protected function evaluateCallable($evaluation)
    {
        gc_collect_cycles();
        $startingMemory = $this->getMemory();
        $startTime = hrtime(true);

        $evaluation();

        $memory = $this->getMemory() - $startingMemory;
        $duration = $this->getDuration($startTime);

        return [
            $memory,
            $duration,
        ];
    }

    /**
     * Evaluate the memory, time, properties, methods and count of a given data type
     * 
     * @internal
     * @return array<int, int|float>
     */
    protected function evaluateDataType($evaluation)
    {
        gc_collect_cycles();
        $startingMemory = $this->getMemory();
        $startTime = hrtime(true);

        $copy = clone $evaluation;

        $memory = $this->getMemory() - $startingMemory;
        $duration = $this->getDuration($startTime);

        // Compute the number of properties, methods, traits, count (if traversable)
        [$properties, $methods, $count] = $this->evaluateReflection($copy);

        return [
            $memory,
            $duration,
            $properties,
            $methods,
            $count,
        ];
    }

    /**
     * Evaluate the 
     * @internal
     * @param object|array $evaluation
     * @return array<int, int>
     */
    protected function evaluateReflection($evaluation)
    {
        $reflection = new ReflectionClass($evaluation);
        $properties = count($reflection->getProperties());
        $methods = count($reflection->getMethods());

        if (is_array($evaluation) || $evaluation instanceof Traversable) {
            $count = count($evaluation);
        }

        return [
            $properties,
            $methods,
            $count,
        ];
    }

    /**
     * @return float
     */
    protected function getMemory()
    {
        return round(memory_get_peak_usage() / (1024 * 1024), 2);
    }

    /**
     * Get the duration of the evaluation in milliseconds
     * 
     * @param float $startTime in nanoseconds
     * @return float
     */
    protected function getDuration($startTime)
    {
        return round((hrtime(true) - $startTime) / 1e6, 3);
    }
}