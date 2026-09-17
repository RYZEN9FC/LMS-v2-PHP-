<?php

namespace Tests;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests refuse to run outside isolated SQLite memory storage.');
        }

        return $app;
    }

    protected function seedAndSignIn(string $role = 'owner'): User
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $outlet = Outlet::query()->firstOrFail();
        $user->outlets()->syncWithoutDetaching([
            $outlet->id => ['role' => $role, 'is_active' => true],
        ]);

        $this->actingAs($user);

        return $user;
    }
}
