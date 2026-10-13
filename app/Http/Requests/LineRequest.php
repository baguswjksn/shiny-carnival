<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('line')?->id;

        return [
            'code'           => ['required', 'string', 'max:20', Rule::unique('lines', 'code')->ignore($id)],
            'name'           => ['required', 'string', 'max:100'],
            'daily_capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'unique'   => ':attribute sudah dipakai.',
            'integer'  => ':attribute harus berupa angka bulat.',
            'min'      => ':attribute minimal :min.',
            'max'      => ':attribute terlalu besar/panjang.',
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'Kode line', 'name' => 'Nama line', 'daily_capacity' => 'Kapasitas'];
    }
}
