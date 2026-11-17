<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Announcement;
use App\Models\CryptoAsset;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\BeneficiaryService;
use App\Services\CryptoService;
use App\Services\Features;
use App\Services\JointService;
use App\Services\Loans\LoanService;
use App\Services\TicketService;
use App\Support\CryptoAmount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Switches the optional services on for a few members and leaves each with something to look at:
 * a loan with autopay, an application waiting for review, a funded crypto wallet, a joint account, beneficiaries, a ticket.
 * Runs after DemoMemberSeeder and only through the same services the app uses.
 */
class DemoFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('email', 'admin@example.com')->first();
        if (! $staff) {
            return;
        }
        $m = fn (string $first, string $last) => User::where('email', strtolower("$first.$last").'@example.com')->first();
        $checking = fn (User $u) => $u->accounts()->whereHas('product', fn ($q) => $q->where('kind', 'share_draft'))->first();
        $saving = fn (User $u) => $u->accounts()->whereHas('product', fn ($q) => $q->where('kind', 'share_savings'))->first();

        $maria = $m('Maria', 'Alvarez');
        $linda = $m('Linda', 'Nguyen');
        $robert = $m('Robert', 'Thompson');
        $priya = $m('Priya', 'Patel');
        $angela = $m('Angela', 'Brooks');
        $james = $m('James', 'Carter');
        $sofia = $m('Sofia', 'Hernandez');

        // A loan that is already paying, and one waiting for a decision.
        foreach ([$maria, $linda] as $u) {
            Features::set($u, 'loans', true, $staff);
        }
        $loans = app(LoanService::class);
        $personal = LoanProduct::where('code', 'PERSONAL')->first();
        if ($personal) {
            $apply = fn (User $u, int $amount, int $term, string $purpose) => $loans->apply($u, $personal, [
                'amount' => $amount, 'term_months' => $term, 'purpose' => $purpose, 'annual_income' => 6_200_000, 'employment' => 'employed', 'deposit_account_id' => $checking($u)->id,
            ]);
            $loan = $loans->approve($apply($maria, 800_000, 24, 'Kitchen remodel'), $staff, 899);
            $loans->setAutopay($loan, $maria, $checking($maria)->id);
            $apply($linda, 1_500_000, 36, 'Debt consolidation');
        }

        // Beneficiaries on Maria's savings.
        app(BeneficiaryService::class)->replace($saving($maria), $maria, [
            ['name' => 'Lucia Alvarez', 'relationship' => 'child', 'dob' => '2012-04-09', 'tier' => 'primary', 'share_percent' => 60],
            ['name' => 'Mateo Alvarez', 'relationship' => 'child', 'dob' => '2015-09-21', 'tier' => 'primary', 'share_percent' => 40],
            ['name' => 'Rosa Alvarez', 'relationship' => 'parent', 'dob' => '1958-01-14', 'tier' => 'contingent', 'share_percent' => 100],
        ]);

        // A joint checking account for James and Sofia.
        $joint = app(JointService::class);
        $req = $joint->invite($james, $checking($james), $sofia->email, 'Sharing the household account.');
        $joint->respondToInvite($req, $sofia, true);
        $joint->decide($req, $staff, true);

        // Crypto: one funded wallet, one deposit waiting for confirmation, international wires for Robert.
        $eth = CryptoAsset::where('symbol', 'ETH')->first();
        if ($eth) {
            $crypto = app(CryptoService::class);
            Features::set($angela, 'crypto', true, $staff);
            $crypto->assignAddress($angela, $eth, '0x'.substr(hash('sha256', 'angela-eth'), 0, 40), null, $staff);
            $crypto->recordFunding($angela, $eth, CryptoAmount::parse('1.25', $eth->decimals), $staff, 'Opening transfer');
            $crypto->declareFunding($angela, $eth, CryptoAmount::parse('0.4', $eth->decimals), 'Second transfer', (string) Str::ulid());
        }
        Features::set($robert, 'wires', true, $staff);
        Features::set($robert, 'wires_international', true, $staff);

        // Something in the support inbox and a notice on the dashboard.
        app(TicketService::class)->open($priya, 'card', 'Card declined online', 'My card was declined at an online store this morning. The card is not frozen and I have enough in checking.');
        Announcement::create([
            'title' => 'Holiday hours', 'body' => 'All branches close at 1 p.m. on Christmas Eve and are closed on Christmas Day. Online banking is always open.',
            'type' => 'info', 'audience' => 'everyone', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(45), 'dismissible' => true, 'created_by' => $staff->id,
        ]);
    }
}
