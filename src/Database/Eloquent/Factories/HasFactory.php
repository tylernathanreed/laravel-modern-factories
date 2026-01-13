<?php

namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @template TFactory of Factory */
trait HasFactory
{
    /**
     * Get a new factory instance for the model.
     *
     * @return TFactory
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

        // @phpstan-ignore-next-line return.type
        return $factory->count($count)->state($state);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return ?TFactory
     */
    protected static function newFactory()
    {
        return null;
    }
}
