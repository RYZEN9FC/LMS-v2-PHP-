<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentOutlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function __construct(private readonly CurrentOutlet $currentOutlet) {}

    public function index()
    {
        $outlet = $this->currentOutlet->get();
        $members = $outlet->users()->orderBy('users.name')->get();

        return view('team.index', compact('outlet', 'members'));
    }

    public function store(Request $request)
    {
        $outlet = $this->currentOutlet->get();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(['owner', 'manager', 'operator', 'viewer'])],
        ]);

        $existing = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();
        if ($existing) {
            $message = $existing->is($request->user())
                ? 'This is your current owner login. It is already active and cannot be reused for another team member.'
                : 'This email already belongs to an account. Every team member must use a different email address.';
            throw ValidationException::withMessages(['email' => $message]);
        }

        DB::transaction(function () use ($data, $outlet): void {
            $user = User::create([
                'organisation_id' => $outlet->organisation_id,
                'name' => $data['name'],
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->outlets()->attach($outlet->id, ['role' => $data['role'], 'is_active' => true]);
        });

        return back()->with('success', 'Team member created. They can sign in with the password you provided.');
    }

    public function update(Request $request, int $id)
    {
        $outlet = $this->currentOutlet->get();
        $member = $outlet->users()->findOrFail($id);
        abort_if($member->is($request->user()), 422, 'You cannot change your own outlet access.');

        $data = $request->validate([
            'role' => ['required', Rule::in(['owner', 'manager', 'operator', 'viewer'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $active = $request->boolean('is_active');

        if ($member->pivot->role === 'owner' && $member->pivot->is_active && (! $active || $data['role'] !== 'owner')) {
            $otherOwners = DB::table('outlet_user')
                ->where('outlet_id', $outlet->id)
                ->where('user_id', '!=', $member->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->exists();
            abort_unless($otherOwners, 422, 'Assign another active owner before changing this owner.');
        }

        $member->outlets()->updateExistingPivot($outlet->id, [
            'role' => $data['role'],
            'is_active' => $active,
        ]);

        return back()->with('success', 'Team access updated.');
    }
}
