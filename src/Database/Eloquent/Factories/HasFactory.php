<?php

namespace Illuminate\Database\Eloquent\Factories;

trait HasFactory
{
    /**
     * Get a new factory instance for the model.
     *
     * @param  mixed  $parameters
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public static function factory()
    {
        $parameters = func_get_args();

        $factory = static::newFactory() ?: Factory::factoryForModel(get_called_class());

        $count = isset($parameters[0]) && is_numeric($parameters[0])
            ? $parameters[0]
            : null;

        $state = isset($parameters[0]) && is_array($parameters[0])
            ? $parameters[0]
            : (isset($parameters[1]) ? $parameters[1] : []);

        return $factory->count($count)->state($state);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory|null
     */
    protected static function newFactory()
    {
        return null;
    }
}
