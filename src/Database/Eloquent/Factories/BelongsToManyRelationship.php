<?php

namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @template TModel of Model */
class BelongsToManyRelationship
{
    /**
     * The related factory instance.
     *
     * @var Factory<TModel>|Collection<int,TModel>|TModel
     */
    protected $factory;

    /**
     * The pivot attributes / attribute resolver.
     *
     * @var callable|array<string,mixed>
     */
    protected $pivot;

    /**
     * The relationship name.
     *
     * @var string
     */
    protected $relationship;

    /**
     * Create a new attached relationship definition.
     *
     * @param  Factory<TModel>|Collection<int,TModel>|TModel  $factory
     * @param  callable|array<string,mixed>  $pivot
     * @param  string  $relationship
     * @return void
     */
    public function __construct($factory, $pivot, $relationship)
    {
        $this->factory = $factory;
        $this->pivot = $pivot;
        $this->relationship = $relationship;
    }

    /**
     * Create the attached relationship for the given model.
     *
     * @param  Model  $model
     * @return void
     */
    public function createFor(Model $model)
    {
        $this->wrap($this->factory instanceof Factory ? $this->factory->create([], $model) : $this->factory)->each(function ($attachable) use ($model) {
            $model->{$this->relationship}()->attach(
                $attachable,
                is_callable($this->pivot) ? call_user_func($this->pivot, $model) : $this->pivot
            );
        });
    }

    /**
     * @param mixed $value
     * @return Collection<int|string,mixed>
     */
    private function wrap($value)
    {
        if ($value instanceof Collection) {
            return new Collection($value);
        } elseif (is_array($value)) {
            return new Collection($value);
        } else {
            return new Collection([$value]);
        }
    }
}
