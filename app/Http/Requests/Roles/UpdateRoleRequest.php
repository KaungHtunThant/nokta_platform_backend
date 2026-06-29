<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by op:manage.roles; tenant ownership checked in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    public function nameOrNull(): ?string
    {
        $name = $this->input('name');

        return is_string($name) ? $name : null;
    }

    /** @return list<string>|null */
    public function permissionsOrNull(): ?array
    {
        if (! $this->has('permissions')) {
            return null;
        }

        /** @var list<string> $permissions */
        $permissions = $this->input('permissions', []);

        return $permissions;
    }
}
