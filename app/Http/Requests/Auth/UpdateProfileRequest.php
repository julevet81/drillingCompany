<?php namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateProfileRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'full_name' => ['sometimes','string','max:255'],
            'phone'     => ['nullable','string','max:20'],
            'email'     => ['sometimes','email', Rule::unique('users')->ignore($this->user()->id)],
            'photo'     => ['nullable','image','max:2048', 'mimes:jpg,jpeg,png,gif'],
        ];
    }
}