<?php
declare(strict_types=1);

/**
 * Calculate dynamic challenge points using CTFd-style exponential decay.
 */
function calculate_dynamic_points(int $initial, int $floor, int $decay_solves, int $current_solves): int
{
    if ($initial <= 0) {
        return max(1, $floor);
    }

    $floor = max(1, $floor);
    if ($floor > $initial) {
        $floor = $initial;
    }

    $decay_solves = max(1, $decay_solves);

    if ($current_solves <= 0) {
        return $initial;
    }

    $decay = ($initial - $floor) / ($initial * $decay_solves);
    $value = $initial * pow(1 - $decay, $current_solves - 1);

    return max($floor, (int)ceil($value));
}
