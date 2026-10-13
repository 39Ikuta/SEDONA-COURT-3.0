<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\PosItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImmediateStockUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['cashier'])]
    #[TestWith(['front_desk'])]
    #[TestWith(['manager'])]
    #[TestWith(['admin'])]
    #[TestWith(['owner'])]
    public function test_staff_stock_adjustments_apply_immediately_and_restore_availability(string $role): void
    {
        $this->withoutVite();
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate($role, 'web'));
        $item = PosItem::create(['name' => 'Tracked stock item', 'price' => 50, 'category' => 'Beverages', 'is_tracked' => true, 'stock_quantity' => 0, 'is_available' => false]);
        $data = ['operation_key' => (string) Str::uuid(), 'stock_quantity' => 20, 'reason' => 'restock', 'notes' => 'Received 20 units'];

        $this->actingAs($actor)->post(route('inventory.update-stock', $item), $data)->assertRedirect(route('inventory.index'))->assertSessionHasNoErrors();
        $this->post(route('inventory.update-stock', $item), $data)->assertSessionHasNoErrors();

        $this->assertSame(20, $item->fresh()->stock_quantity);
        $this->assertTrue((bool) $item->fresh()->getRawOriginal('is_available'));
        $this->assertDatabaseCount('inventory_events', 1);
        $this->assertDatabaseHas('inventory_events', ['pos_item_id' => $item->id, 'quantity_change' => 20, 'balance_after' => 20, 'user_id' => $actor->id]);
        $this->assertSame('executed', ApprovalRequest::sole()->status);
        $this->assertNull(ApprovalRequest::sole()->reviewed_by);
        $this->get(route('inventory.index'))->assertSee('Stock count saved immediately. No approval is required.');
    }

    public function test_every_catalog_item_accepts_stock_changes_without_a_pending_approval(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $cashier = User::where('email', 'pau@sedonapms.com')->firstOrFail();
        $items = PosItem::notRetired()->orderBy('id')->get();
        $this->actingAs($cashier);

        foreach ($items as $item) {
            $newCount = $item->stock_quantity + 7;
            $this->post(route('inventory.update-stock', $item), ['operation_key' => (string) Str::uuid(), 'stock_quantity' => $newCount, 'reason' => 'restock'])->assertSessionHasNoErrors();
            $this->assertSame($newCount, $item->fresh()->stock_quantity, $item->name);
        }

        $this->assertSame(0, ApprovalRequest::whereIn('status', ['pending', 'approved'])->count());
        $this->assertDatabaseCount('inventory_events', $items->count());
    }

    public function test_invalid_batch_count_rolls_back_every_stock_change(): void
    {
        $this->seed(DatabaseSeeder::class);
        $cashier = User::where('email', 'pau@sedonapms.com')->firstOrFail();
        $item = PosItem::where('is_tracked', true)->firstOrFail();
        $before = $item->stock_quantity;

        $this->actingAs($cashier)->post(route('inventory.recount'), ['operation_key' => (string) Str::uuid(), 'counts' => [$item->id => 17, 999999 => 20]])->assertSessionHasErrors('counts');

        $this->assertSame($before, $item->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_events', 0);
        $this->assertDatabaseCount('approval_requests', 0);
    }

    public function test_old_pending_stock_update_can_be_applied_without_review_but_stale_counts_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $cashier = User::where('email', 'pau@sedonapms.com')->firstOrFail();
        $approval = ApprovalRequest::factory()->create(['requested_by' => $cashier->id]);

        $this->actingAs($cashier)->post(route('approvals.execute', $approval), ['operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $this->assertSame(20, $approval->source->stock_quantity);
        $this->assertSame('executed', $approval->fresh()->status);
        $this->assertNull($approval->fresh()->reviewed_by);
        $stale = ApprovalRequest::factory()->create(['requested_by' => $cashier->id]);
        $stale->source->update(['stock_quantity' => 9]);
        $this->post(route('approvals.execute', $stale), ['operation_key' => (string) Str::uuid()])->assertSessionHasErrors('approval');
        $this->assertSame(9, $stale->source->fresh()->stock_quantity);
        $this->assertSame('pending', $stale->fresh()->status);
    }
}
