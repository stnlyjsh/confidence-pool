<?php

namespace App\Http\Requests;

use App\Enums\GameStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideGameRequest extends FormRequest
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
            // Voiding a game already has its own dedicated endpoint — this
            // one is for standing in when ESPN itself is down or wrong.
            'status' => ['required', Rule::in([
                GameStatus::Final->value,
                GameStatus::Postponed->value,
                GameStatus::Canceled->value,
            ])],
            'home_score' => ['required_if:status,final', 'integer', 'min:0'],
            'away_score' => ['required_if:status,final', 'integer', 'min:0'],
        ];
    }
}
