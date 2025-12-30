<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'email' => [
                'required',
                'email',
                // Check not already a member
                function ($attribute, $value, $fail) use ($tenant) {
                    $user = User::where('email', $value)->first();
                    if ($user && $tenant->hasUser($user)) {
                        $fail('This user is already a member of this organization.');
                    }
                },
                // Check no pending invitation
                Rule::unique('tenant_invitations')->where(function ($query) use ($tenant) {
                    return $query->where('tenant_id', $tenant->id);
                }),
            ],
            'role' => ['required', 'string', Rule::in(['member', 'admin', 'owner'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'An invitation has already been sent to this email.',
        ];
    }
}
