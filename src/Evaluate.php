<?php

namespace Conquest\Evaluate;

use Closure;
use Conquest\Core\Concerns\HasName;
use Illuminate\Support\Arr;
use ReflectionClass;
use Traversable;

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
     *
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
     * @param  array<int, \Closure|object|array|callable>  $evaluations
     * @param  int  $metrics
     * @param  string|null  $name
     * @param  int  $times
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
     * @param  array<int, \Closure|object|array|callable>  $evaluations
     * @param  int  $metrics
     * @param  string|null  $name
     * @param  int  $times
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
        $results = collect(Arr::wrap($this->evaluations))->map(fn ($evaluation) => collect(range(1, $this->times))->map(fn () => is_callable($evaluation) ?
                $this->evaluateCallable($evaluation)
                : $this->evaluateDataType($evaluation)
        )
        );

        // Compute the averages for the array of results

    }

    /**
     * Evaluate the performance of the application
     *
     * @internal
     */
    protected function evaluateApplication()
    {
        // Use peak memory to be measure of the application as
        // it indicates the worst case memory usage
        $this->memory = round(memory_get_peak_usage() / (1024 * 1024), 2);

        // The start of the application uses the application time which is in microseconds
        // we need to convert this from microseconds to milliseconds to align with the other metrics
        $this->duration = round((microtime(true) - LARAVEL_START) * 1e3, 3);
    }

    /**
     * Evaluate the performance of a given callable
     *
     * @internal
     *
     * @return array<int, int|float>
     */
    protected function evaluateCallable($evaluation)
    {
        gc_collect_cycles();
        $startTime = hrtime(true);
        $startingMemory = $this->computeMemory();
        $evaluation();
        $memory = $this->computeMemory() - $startingMemory;
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
     *
     * @return array<int, int|float>
     */
    protected function evaluateDataType($evaluation)
    {
        gc_collect_cycles();

        // Timing is done in nanoseconds
        $startTime = hrtime(true);
        $startingMemory = $this->computeMemory();

        // Must mutate the object to create new memory allocation
        $tmp = json_decode(json_encode($evaluation));

        // This does not include the memory allocated to the variable
        // This is assumed redundant as we are only concerned with memory
        // allocations in the mega bytes range
        $memory = $this->computeMemory() - $startingMemory;
        $duration = $this->getDuration($startTime);

        $properties = $methods = $count = null;

        if (is_object($evaluation)) {
            [$properties, $methods] = $this->evaluateReflection($tmp);
        }

        if (is_array($evaluation) || $evaluation instanceof Traversable) {
            $count = count($evaluation);
        }

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
     *
     * @internal
     *
     * @param  object|array  $evaluation
     * @return array<int, int>
     */
    protected function evaluateReflection($evaluation)
    {
        $reflection = new ReflectionClass($evaluation);
        $properties = count($reflection->getProperties());
        $methods = count($reflection->getMethods());

        return [
            $properties,
            $methods,
        ];
    }

    /**
     * Get the memory usage of the evaluation in MB
     *
     * @return float
     */
    protected function computeMemory()
    {
        return round(memory_get_usage() / (1024 * 1024), 2);
    }

    /**
     * Get the duration of the evaluation in milliseconds
     *
     * @param  float  $startTime  in nanoseconds
     * @return float
     */
    protected function getDuration($startTime)
    {
        return round((hrtime(true) - $startTime) / 1e6, 3);
    }

    /**
     * Formatting of the table results
     *
     * @internal
     *
     * @return string
     */
    protected function print($results)
    {
        //
    }
}
