<?php

namespace Illuminate\Database\Eloquent\Factories;

use Closure;
use Countable;

class Sequence implements Countable
{
    /**
     * The sequence of return values.
     *
     * @var list<mixed>
     */
    protected $sequence;

    /**
     * The count of the sequence items.
     *
     * @var int
     */
    public $count;

    /**
     * The current index of the sequence iteration.
     *
     * @var int
     */
    public $index = 0;

    /**
     * Create a new sequence instance.
     *
     * @param list<mixed>  $sequence
     * @return void
     */
    public function __construct(...$sequence)
    {
        $this->sequence = $sequence;
        $this->count = count($sequence);
    }

    /**
     * Get the current count of the sequence items.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Get the next value in the sequence.
     *
     * @return mixed
     */
    public function __invoke()
    {
        $value = $this->sequence[$this->index % $this->count];

        if ($value instanceof Closure) {
            $value = $value($this);
        }

        $this->index = $this->index + 1;

        return $value;
    }
}
