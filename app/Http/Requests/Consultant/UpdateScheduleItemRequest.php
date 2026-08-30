<?php

namespace App\Http\Requests\Consultant;

/**
 * Update uses the exact same payload shape as create, so it simply reuses
 * StoreScheduleItemRequest's rules rather than duplicating them.
 */
class UpdateScheduleItemRequest extends StoreScheduleItemRequest
{
    //
}
