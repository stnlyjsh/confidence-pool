<?php

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'type' => ['required', Rule::in([FeedbackType::Bug->value, FeedbackType::Idea->value])],
            'message' => ['required', 'string', 'max:2000'],
            'screenshot' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
