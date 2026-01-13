<?php

namespace Illuminate\Tests\Database\Fixtures\Factories\Money;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Tests\Database\Fixtures\Models\Money\Price;

/** @extends Factory<Price> */
class PriceFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
