<?php

namespace Conquest\Evaluate;

use BadMethodCallException;
use Closure;
use Conquest\Core\Concerns\HasName;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Traversable;

/**
 * Measure the performance of your code.
 *
 * @method static void dd(array|Closure|object|array[] $evaluations, int $metrics = self::Basic, string|null $name = null, int $times = 5)
 * @method static void log(array|Closure|object|array[] $evaluations, int $metrics = self::Basic, string|null $name = null, int $times = 5)
 * @method static void dump(array|Closure|object|array[] $evaluations, int $metrics = self::Basic, string|null $name = null, int $times = 5)
 * @method void dd(array|Closure|object|array[] $evaluations, int $metrics = self::Basic, string|null $name = null, int $times = 5)
 * @method void log(array|Closure|object|array[] $evaluations, int $metrics = self::Basic, string|null $name = null, int $times = 5)
 * @method void dump(array|Closure|object|array[] $evaluations, int $metrics = self::Basic, string|null $name = null, int $times = 5)
 */
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
     * @var float|array<int, float>|null
     */
    protected $memory = null;

    /**
     * The computed execution time in ms
     *
     * @var float|array<int, float>|null
     */
    protected $duration = null;

    /**
     * The number of properties
     *
     * @var array<int, int>|null
     */
    protected $properties = null;

    /**
     * The number of methods
     *
     * @var array<int, int>|null
     */
    protected $methods = null;

    /**
     * The count of the number of items
     *
     * @var array<int, int>|null
     */
    protected $count = null;

    /**
     * Whether the case has had a terminating method called
     *
     * @var bool
     */
    protected $terminated = false;

    /**
     * Create a new evaluation instance
     *
     * @param  array<\Closure|object|array|callable>  $evaluations
     */
    public function __construct($evaluations = [], int $metrics = self::Basic, ?string $name = null, int $times = 5)
    {
        $this->evaluations = $evaluations;
        $this->metrics = $metrics;
        $this->name = $name;
        $this->times = $times;
    }

    /**
     * Create a new evaluation instance
     *
     * @param  array<int, \Closure|object|array|callable>  $evaluations
     * @param  int  $metrics
     * @param  string|null  $name
     * @param  int  $times
     */
    public static function measure($evaluations = [], $metrics = self::Basic, $name = null, $times = 5)
    {
        return resolve(static::class, compact('evaluations', 'metrics', 'name', 'times'));
    }

    /**
     * Set the number of times to execute the evaluation
     *
     * @return $this
     */
    public function times(int $times)
    {
        $this->times = $times;

        return $this;
    }

    /**
     * Set the metrics to be used for this evaluation
     *
     * @return $this
     */
    public function metrics(int $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * @internal
     */
    public function __call($name, $arguments)
    {
        return $this->handle($name);
    }

    /**
     * Ensure that the results are printed when destructing if not already terminated
     *
     * @internal
     */
    public function __destruct()
    {
        if (! $this->terminated) {
            dd($this->print());
        }
    }

    /**
     * @internal
     */
    public static function __callStatic($name, $arguments)
    {
        $evaluator = static::measure(...$arguments);

        return $evaluator->handle($name);
    }

    /**
     * Handle the evaluation results
     *
     * @param  string  $name
     *
     * @internal
     */
    protected function handle($name)
    {
        $this->terminate();

        return match ($name) {
            'dd' => dd($this->print()),
            'log' => Log::info($this->print()),
            'dump' => dump($this->print()),
            default => throw new BadMethodCallException("Method {$name} does not exist."),
        };
    }

    /**
     * Forcefully terminate the evaluation, reserved for testing purposes generally
     *
     * @internal
     *
     * @return $this
     */
    public function terminate()
    {
        $this->evaluate();
        $this->terminated = true;

        return $this;
    }

    /**
     * Get the memory usage(s) of the evaluation
     *
     * @internal
     *
     * @return array<int|float>|int|float|null
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * Get the execution time(s) of the evaluation
     *
     * @internal
     *
     * @return array<int|float>|int|float|null
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * Get the execution cost of the evaluation
     *
     * @internal
     *
     * @return array<int|float>|int|float|null
     */
    public function getCost()
    {
        return round(($this->memory * $this->duration) / 1e3, 3);
    }

    /**
     * Get the count of the evaluation
     *
     * @internal
     *
     * @return array<int>|int|null
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Get the number of properties of the evaluation
     *
     * @internal
     *
     * @return array<int>|int|null
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Get the number of methods of the evaluation
     *
     * @internal
     *
     * @return array<int>|int|null
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Evaluate the performance of the provided evaluations
     *
     * @internal
     */
    protected function evaluate()
    {
        if ($this->assessApplication()) {
            $this->evaluateApplication();

            return;
        }

        collect(Arr::wrap($this->evaluations))->map(fn ($evaluation) => collect(range(1, $this->times))->map(fn () => is_callable($evaluation) ?
                $this->evaluateCallable($evaluation)
                : $this->evaluateDataType($evaluation)
        )
        )->map(function (Collection $eval) {
            $summed = $eval->reduce(function ($carry, $item) {
                foreach ($item as $key => $value) {
                    if (is_null($value)) {
                        $carry[$key] = null;

                        continue;
                    }
                    if (! isset($carry[$key])) {
                        $carry[$key] = 0;
                    }
                    $carry[$key] += $value;
                }

                return $carry;
            }, []);

            return array_map(fn ($value) => is_null($value) ? null : $value / $this->times, $summed);
        })->each(function (array $result) {
            foreach ($result as $key => $value) {
                $this->{$key} = $value;
            }
        });
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
        $this->memory = $this->formatMemory(memory_get_peak_usage(true));

        // The start of the application uses the application time which is in microseconds
        // we need to convert this from microseconds to milliseconds to align with the other metrics
        $this->duration = round((microtime(true) - LARAVEL_START) * 1e3, 3);
    }

    /**
     * Evaluate the performance of a given callable
     *
     * @internal
     *
     * @return array<string, int|float>
     */
    protected function evaluateCallable($evaluation)
    {
        gc_collect_cycles();

        // Reset the peak memory usage to zero in case a previous evaluation has left it high
        memory_reset_peak_usage();
        $startMemory = memory_get_peak_usage(true);

        // Disable garbage collection to prevent freeing during function execution
        gc_disable();

        // Couple timer
        $startTime = hrtime(true);
        $evaluation();
        $duration = $this->formatDuration($startTime);

        // Get the memory usage
        $consumedMemory = memory_get_peak_usage(true);

        gc_enable();

        return [
            'memory' => $this->formatMemory($consumedMemory - $startMemory),
            'duration' => $duration,
        ];
    }

    /**
     * Evaluate the memory, time, properties, methods and count of a given data type
     *
     * @internal
     *
     * @return array<string, int|float>
     */
    protected function evaluateDataType($evaluation)
    {
        gc_collect_cycles();

        // Timing is done in nanoseconds
        $startMemory = memory_get_usage(true);

        // Couple timer
        $startTime = hrtime(true);
        // Must mutate the object to create new memory allocation
        $tmp = json_decode(json_encode($evaluation));
        $duration = $this->formatDuration($startTime);

        // This does not include the memory allocated to the variable
        // This is assumed redundant as we are only concerned with memory
        // allocations in the mega bytes range
        $consumedMemory = memory_get_usage(true);

        $reflection = is_object($evaluation) ? $this->evaluateReflection($tmp) : [
            'properties' => null,
            'methods' => null,
        ];

        $count = is_array($evaluation) || $evaluation instanceof Traversable ? count($evaluation) : null;

        return array_merge([
            'memory' => $this->formatMemory($consumedMemory - $startMemory),
            'duration' => $duration,
            'count' => $count,
        ], $reflection);
    }

    /**
     * Evaluate the
     *
     * @internal
     *
     * @param  object|array  $evaluation
     * @return array<string, int>
     */
    protected function evaluateReflection($evaluation)
    {
        $reflection = new ReflectionClass($evaluation);

        return [
            'properties' => count($reflection->getProperties()),
            'methods' => count($reflection->getMethods()),
        ];
    }

    /**
     * Determine if the evaluation is application layer
     *
     * @internal
     *
     * @return bool
     */
    protected function assessApplication()
    {
        return empty($this->evaluations);
    }

    /**
     * Get the memory usage of the evaluation in MB
     *
     * @internal
     *
     * @param  int  $memory  in bytes
     * @return float
     */
    protected function formatMemory($memory)
    {
        return round($memory / (1024 * 1024), 2);
    }

    /**
     * Get the duration of the evaluation in milliseconds
     *
     * @internal
     *
     * @param  float  $startTime  in nanoseconds
     * @return float
     */
    protected function formatDuration($startTime)
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
    protected function print()
    {
        return collect([
            sprintf('%s', $this->hasName() ? 'Evaluation for '.$this->name : 'Evaluation'),
            '----------------------------------------',
            sprintf('Memory Usage: %s', $this->memory),
            sprintf('Execution Time: %s', $this->duration),
            sprintf('Execution Cost: %s', $this->memory * $this->duration),
            sprintf('Class Properties: %s', $this->properties),
            sprintf('Class Methods: %s', $this->methods),
            sprintf('Count: %s', $this->count),
        ])->implode(PHP_EOL);
    }
}
