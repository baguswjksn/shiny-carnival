<?php

namespace App\Http\Requests;

class UpdateScheduleRequest extends StoreScheduleRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
