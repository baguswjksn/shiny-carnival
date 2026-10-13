<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('order')?->id;

        return [
            'order_no'  => ['required', 'string', 'max:50', Rule::unique('orders', 'order_no')->ignore($id)],
            'buyer'     => ['required', 'string', 'max:100'],
            'style'     => ['required', 'string', 'max:100'],
            'total_qty' => ['required', 'integer', 'min:1', 'max:100000000'],
            'due_date'  => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'required'    => ':attribute wajib diisi.',
            'unique'      => ':attribute sudah dipakai.',
            'integer'     => ':attribute harus berupa angka bulat.',
            'min'         => ':attribute minimal :min.',
            'date_format' => ':attribute harus berformat tanggal valid.',
        ];
    }

    public function attributes(): array
    {
        return ['order_no' => 'No. order', 'buyer' => 'Buyer', 'style' => 'Style', 'total_qty' => 'Qty order', 'due_date' => 'Due date'];
    }
}
