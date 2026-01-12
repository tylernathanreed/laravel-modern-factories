<?php

namespace Illuminate\Database\Eloquent\Factories;

class CrossJoinSequence extends Sequence
{
    /**
     * Create a new cross join sequence instance.
     *
     * @param  list<mixed>  $sequences
     * @return void
     */
    public function __construct()
    {
        $sequences = func_get_args();

        $crossJoined = array_map(
            function ($a) {
                $m = [];

                foreach ($a as $_a) {
                    $m = array_merge($m, $_a);
                }

                return $m;
            },
            $this->crossJoin($sequences)
        );

        $this->sequence = $crossJoined;
        $this->count = count($crossJoined);
    }

    /**
     * @param list<mixed> $arrays
     * @return list<mixed>
     */
    private function crossJoin($arrays)
    {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;

                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $results;
    }
}
