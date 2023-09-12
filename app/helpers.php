<?php

function generate_linear_random_data($minValue, $maxValue, $count)
{
    $data = [];
    for ($i = 0; $i < $count; $i++) {
        $value = mt_rand($minValue, $maxValue);
        $data[] = $value;
    }
    return $data;
}
