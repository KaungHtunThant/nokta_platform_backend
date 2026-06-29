<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Enums\RolesAndPermissions\UiPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by op:manage.roles
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'guard' => ['required', Rule::in([OperationPermission::GUARD, UiPermission::GUARD])],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }

    public function name(): string
    {
        return (string) $this->input('name');
    }

    public function guard(): string
    {
        return (string) $this->input('guard');
    }

    /** @return list<string> */
    public function permissions(): array
    {
        /** @var list<string> $permissions */
        $permissions = $this->input('permissions', []);

        return $permissions;
    }
}
