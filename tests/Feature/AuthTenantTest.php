<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $this->seed();

        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'password'])
            ->assertRedirect('/');
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_viewer_can_read_reports_and_history_but_cannot_change_inventory_data(): void
    {
        $this->seedAndSignIn('viewer');

        $this->get('/')->assertOk();
        $this->get('/uploads/history')->assertOk();
        $this->get('/uploads/pos')->assertForbidden();
        $this->post('/brands', [
            'name' => 'Blocked brand',
            'spirit_type' => 'Gin',
            'bottle_size_ml' => 750,
        ])->assertForbidden();
    }

    public function test_user_cannot_select_or_read_another_organisations_outlet(): void
    {
        $this->seedAndSignIn();
        $otherOrganisation = Organisation::create([
            'name' => 'Other Bar',
            'slug' => 'other-bar',
            'timezone' => 'Asia/Kolkata',
        ]);
        $otherOutlet = Outlet::create([
            'organisation_id' => $otherOrganisation->id,
            'name' => 'Private Outlet',
            'code' => 'PRIVATE',
        ]);
        Product::create([
            'outlet_id' => $otherOutlet->id,
            'name' => 'PRIVATE TENANT BRAND',
            'spirit_type' => 'Gin',
            'bottle_size_ml' => 750,
        ]);

        $this->withSession(['active_outlet_id' => $otherOutlet->id])
            ->get('/reports/current')
            ->assertOk()
            ->assertDontSee('PRIVATE TENANT BRAND');

        $this->post('/outlet/switch', ['outlet_id' => $otherOutlet->id])->assertNotFound();
    }

    public function test_authenticated_layout_has_mobile_navigation_controls(): void
    {
        $this->seedAndSignIn();

        $this->get('/')
            ->assertOk()
            ->assertSee('aria-label="Open navigation"', false)
            ->assertSee('Sip Society')
            ->assertSee('Settings');

        $this->get('/settings')
            ->assertOk()
            ->assertSee('Account security')
            ->assertSeeText('Team & access');
    }

    public function test_owner_can_create_an_outlet_team_member(): void
    {
        $this->seedAndSignIn();

        $this->post('/team', [
            'name' => 'Stock Operator',
            'email' => 'operator@example.com',
            'password' => 'SecurePass10',
            'password_confirmation' => 'SecurePass10',
            'role' => 'operator',
        ])->assertRedirect();

        $member = User::query()->where('email', 'operator@example.com')->firstOrFail();
        $this->assertSame('operator', DB::table('outlet_user')->where('user_id', $member->id)->value('role'));
        $this->assertSame(1, $member->organisation_id);
    }

    public function test_manager_cannot_manage_team_accounts(): void
    {
        $this->seedAndSignIn('manager');

        $this->get('/team')->assertForbidden();
        $this->post('/team', [])->assertForbidden();
    }

    public function test_failed_password_validation_does_not_reserve_a_team_email(): void
    {
        $this->seedAndSignIn();
        $details = [
            'name' => 'New Operator',
            'email' => 'new.operator@example.com',
            'role' => 'operator',
        ];

        $this->post('/team', $details + [
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new.operator@example.com']);

        $this->post('/team', $details + [
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new.operator@example.com']);
    }

    public function test_current_login_cannot_be_duplicated_as_another_team_member(): void
    {
        $this->seedAndSignIn();

        $this->post('/team', [
            'name' => 'Duplicate',
            'email' => 'demo@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'role' => 'operator',
        ])->assertSessionHasErrors([
            'email' => 'This is your current owner login. It is already active and cannot be reused for another team member.',
        ]);
    }

    public function test_authenticated_user_can_change_their_password(): void
    {
        $user = $this->seedAndSignIn();

        $this->put('/account/password', [
            'current_password' => 'password',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('abcdefgh', $user->fresh()->password));
    }
}
