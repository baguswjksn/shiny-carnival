<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id'    => ['required', 'integer', Rule::exists('orders', 'id')->whereNull('deleted_at')],
            'line_id'     => ['required', 'integer', Rule::exists('lines', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'start_date'  => ['required', 'date_format:Y-m-d'],
            'finish_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'target_qty'  => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required'            => ':attribute wajib diisi.',
            'exists'              => ':attribute tidak valid.',
            'integer'             => ':attribute harus berupa angka bulat.',
            'min'                 => ':attribute minimal :min.',
            'date_format'         => ':attribute harus berformat tanggal valid.',
            'after_or_equal'      => ':attribute tidak boleh sebelum start sewing.',
        ];
    }

    public function attributes(): array
    {
        return [
            'order_id'    => 'Order',
            'line_id'     => 'Line',
            'start_date'  => 'Start sewing',
            'finish_date' => 'Finish sewing',
            'target_qty'  => 'Qty target',
        ];
    }
}
