<?php

return [
    // true  = actual output above target reduces the next day's target
    // false = surplus is ignored (only shortfall carries over)
    'apply_surplus' => env('SCHEDULE_APPLY_SURPLUS', true),

    // Maximum length of one schedule range (days)
    'max_range_days' => 366,
];
