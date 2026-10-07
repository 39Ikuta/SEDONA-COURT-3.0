<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Folio;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_and_owner_can_access_pnl_statement()
    {
        $admin = User::where('email', 'admin1@sedonapms.com')->first();
        $owner = User::where('email', 'owner@sedonapms.com')->first();

        $responseAdmin = $this->actingAs($admin)->get(route('admin.accounting.pnl'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('OPERATING REVENUE STREAMS');
        $responseAdmin->assertSee('OPERATING EXPENSES (OPEX)', false);
        $responseAdmin->assertSee('NET OPERATING PROFIT');

        $responseOwner = $this->actingAs($owner)->get(route('admin.accounting.pnl'));
        $responseOwner->assertStatus(200);
    }

    public function test_operating_expense_lifecycle()
    {
        $admin = User::where('email', 'admin1@sedonapms.com')->first();

        // View expenses ledger
        $response = $this->actingAs($admin)->get(route('admin.accounting.expenses'));
        $response->assertStatus(200);
        $response->assertSee('OPERATING EXPENSE VOUCHER JOURNAL');

        // Store new expense
        $storeResponse = $this->actingAs($admin)->post(route('admin.accounting.expenses.store'), [
            'expense_date'      => now()->toDateString(),
            'category'          => 'utilities',
            'description'       => 'High-speed Fiber Internet Monthly Subscription',
            'amount'            => 2999.00,
            'payment_source'    => 'petty_cash',
            'receipt_reference' => 'PLDT-99120',
        ]);

        $storeResponse->assertRedirect(route('admin.accounting.expenses'));
        $this->assertDatabaseHas('expenses', [
            'description'       => 'High-speed Fiber Internet Monthly Subscription',
            'amount'            => 2999.00,
            'payment_source'    => 'petty_cash',
            'receipt_reference' => 'PLDT-99120',
        ]);

        $expense = Expense::where('receipt_reference', 'PLDT-99120')->first();
        $this->assertNotNull($expense);

        // Delete expense
        $deleteResponse = $this->actingAs($admin)->delete(route('admin.accounting.expenses.delete', $expense->id));
        $deleteResponse->assertRedirect(route('admin.accounting.expenses'));
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_all_printable_documents_generate_cleanly()
    {
        $cashier = User::where('email', 'pau@sedonapms.com')->first();
        $room = Room::where('number', '1')->first();

        // Check in guest to generate active folio
        $this->actingAs($cashier)->post(route('checkin.store'), [
            'room_id'    => $room->id,
            'guest_name' => 'Printable Test Guest',
            'rate_tier'  => '12h',
            'headcount'  => 2,
        ]);

        $folio = Folio::where('room_id', $room->id)->where('status', 'active')->first();
        $this->assertNotNull($folio);
        $folio->update(['security_deposit' => 500.00]);

        // 1. Official Receipt
        $receiptResp = $this->actingAs($cashier)->get(route('folios.receipt', $folio->id));
        $receiptResp->assertStatus(200);
        $receiptResp->assertSee('OFFICIAL SETTLEMENT RECEIPT');
        $receiptResp->assertSee($folio->transaction_id);

        // 2. Security Deposit Slip
        $depositResp = $this->actingAs($cashier)->get(route('folios.deposit_slip', $folio->id));
        $depositResp->assertStatus(200);
        $depositResp->assertSee('SECURITY DEPOSIT RECEIPT');
        $depositResp->assertSee('₱500.00');

        // 3. Statement of Account / Billing Statement
        $billingResp = $this->actingAs($cashier)->get(route('folios.billing', $folio->id));
        $billingResp->assertStatus(200);
        $billingResp->assertSee('STATEMENT OF ACCOUNT / GUEST FOLIO');
        $billingResp->assertSee('Room Lodging Charge');

        // 4. Kitchen Order Slip
        $orderSlipResp = $this->actingAs($cashier)->get(route('folios.orderslip', $folio->id));
        $orderSlipResp->assertStatus(200);
        $orderSlipResp->assertSee('GUEST ORDER SLIP / FOLIO');

        // 5. Loss Slip
        $lossSlipResp = $this->actingAs($cashier)->get(route('folios.loss_slip', $folio->id));
        $lossSlipResp->assertStatus(200);
        $lossSlipResp->assertSee('FORCE CHECKOUT WRITE-OFF LOSS SLIP');
    }

    public function test_csv_export_streams_properly()
    {
        $admin = User::where('email', 'admin1@sedonapms.com')->first();

        $response = $this->actingAs($admin)->get(route('admin.accounting.pnl.csv', ['range' => 'this_month']));
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }
}
