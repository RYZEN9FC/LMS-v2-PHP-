<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CurrentOutlet
{
    private ?Outlet $outlet = null;

    public function get(): Outlet
    {
        if ($this->outlet) {
            return $this->outlet;
        }

        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user?->is_active, 403, 'This account is inactive.');

        $query = $user->outlets()->wherePivot('is_active', true)->where('outlets.organisation_id', $user->organisation_id);
        $selected = (int) session('active_outlet_id', 0);
        $outlet = $selected ? (clone $query)->whereKey($selected)->first() : null;
        $outlet ??= $query->orderBy('outlets.name')->first();
        abort_unless($outlet, 403, 'No active outlet is assigned to this account.');

        session(['active_outlet_id' => $outlet->id]);

        return $this->outlet = $outlet;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    public function role(): string
    {
        return (string) $this->get()->pivot->role;
    }

    public function allows(string $permission): bool
    {
        $permissions = [
            'owner' => ['reports.view', 'imports.view', 'imports.manage', 'catalogue.view', 'catalogue.manage', 'team.manage'],
            'manager' => ['reports.view', 'imports.view', 'imports.manage', 'catalogue.view', 'catalogue.manage'],
            'operator' => ['reports.view', 'imports.view', 'imports.manage', 'catalogue.view'],
            'viewer' => ['reports.view', 'imports.view'],
        ];

        return in_array($permission, $permissions[$this->role()] ?? [], true);
    }

    public function forget(): void
    {
        $this->outlet = null;
    }
}
