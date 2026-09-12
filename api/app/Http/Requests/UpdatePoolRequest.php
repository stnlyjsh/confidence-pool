<?php

namespace App\Http\Requests;

use App\Enums\PoolStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Route-level 'commissioner' middleware already restricts access.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'season_year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'buy_in_amount_cents' => ['sometimes', 'integer', 'min:0'],
            'weekly_payout_cents' => ['sometimes', 'integer', 'min:0'],
            'season_payout_cents' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(PoolStatus::class)],
        ];
    }
}
