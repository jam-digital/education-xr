<?php

namespace Tests\Feature;

use App\Enums\LicenseType;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\Accounts\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Accounts\RelationManagers\LicensesRelationManager;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Models\User;
use App\Models\VrLesson;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $email): User
    {
        $u = User::where('email', $email)->firstOrFail();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($u);

        return $u;
    }

    public function test_every_page_opens_for_super_admin(): void
    {
        $this->as('admin@vr-osvita.ua');
        $client = User::clients()->first();
        foreach (['/admin', '/admin/accounts', "/admin/accounts/{$client->id}", '/admin/devices', '/admin/lessons',
            '/admin/lessons/'.VrLesson::first()->id.'/edit', '/admin/promotions', '/admin/payments', '/admin/ticket-logs',
            '/admin/audit-logs', '/admin/staff'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_role_matrix(): void
    {
        $this->as('methodist@vr-osvita.ua');
        $this->get('/admin/accounts')->assertOk();
        $this->get('/admin/payments')->assertForbidden();
        $this->get('/admin/audit-logs')->assertForbidden();

        $this->as('finance@vr-osvita.ua');
        $this->get('/admin/payments')->assertOk();
        $this->get('/admin/devices')->assertForbidden();
        $this->get('/admin/accounts/create')->assertForbidden();

        $client = User::clients()->first();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($client)->get('/admin')->assertForbidden();
    }

    public function test_search_by_device_serial(): void
    {
        $this->as('methodist@vr-osvita.ua');
        $device = Device::whereNotNull('hardware_serial')->with('user')->first();
        Livewire::test(ListAccounts::class)
            ->searchTable(substr($device->hardware_serial, 2, 8))
            ->assertCanSeeTableRecords([$device->user])
            ->assertCountTableRecords(1);
    }

    public function test_bulk_grant_bonus_tickets(): void
    {
        $this->as('methodist@vr-osvita.ua');
        $clients = User::clients()->take(3)->get();
        $lesson = VrLesson::first();
        $before = LessonLicense::where('lesson_id', $lesson->id)->whereIn('user_id', $clients->pluck('id'))->where('license_type', 'ticket_pool')->where('status', 'active')->sum('tickets_remaining');

        Livewire::test(ListAccounts::class)
            ->callTableBulkAction('bulkTickets', $clients, data: ['lesson_ids' => [$lesson->id], 'tickets' => 10, 'reason' => 'Бонус'])
            ->assertHasNoTableBulkActionErrors();

        $after = LessonLicense::where('lesson_id', $lesson->id)->whereIn('user_id', $clients->pluck('id'))->where('license_type', 'ticket_pool')->where('status', 'active')->sum('tickets_remaining');
        $this->assertSame(30, (int) ($after - $before));
    }

    public function test_grant_and_adjust_from_account_card(): void
    {
        $this->as('methodist@vr-osvita.ua');
        $client = User::clients()->first();
        $lesson = VrLesson::orderByDesc('sort')->first();
        LessonLicense::where('user_id', $client->id)->where('lesson_id', $lesson->id)->delete();

        Livewire::test(ViewAccount::class, ['record' => $client->id])
            ->callAction('grant', data: ['lesson_ids' => [$lesson->id], 'license_type' => LicenseType::TicketPool->value, 'tickets' => 100])
            ->assertHasNoActionErrors();
        $license = LessonLicense::where('user_id', $client->id)->where('lesson_id', $lesson->id)->sole();
        $this->assertSame(100, $license->tickets_remaining);

        Livewire::test(LicensesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewAccount::class])
            ->callTableAction('adjust', $license, data: ['direction' => 'sub', 'amount' => 5, 'reason' => 'Тест'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(95, $license->refresh()->tickets_remaining);
    }

    public function test_device_swap_from_account_card(): void
    {
        $this->as('methodist@vr-osvita.ua');
        $device = Device::where('status', 'active')->whereNotNull('hardware_serial')->first();

        Livewire::test(DevicesRelationManager::class, ['ownerRecord' => $device->user, 'pageClass' => ViewAccount::class])
            ->callTableAction('swap', $device, data: ['serial' => 'SWAP-NEW-1', 'reason' => 'Розбито'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('decommissioned_replaced', $device->refresh()->status->value);
        $this->assertDatabaseHas('devices', ['hardware_serial' => 'SWAP-NEW-1', 'user_id' => $device->user_id, 'status' => 'active']);
    }
}
