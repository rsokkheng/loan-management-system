<?php

namespace Database\Seeders;

use App\Enums\InterestMethod;
use App\Enums\JournalType;
use App\Enums\PaymentMethod;
use App\Enums\RepaymentFrequency;
use App\Models\Account;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\LoanService;
use App\Services\PenaltyService;
use App\Services\RepaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with staff accounts, products and a year of demo lending activity.
     */
    public function run(LoanService $loans, RepaymentService $repayments, PenaltyService $penalties, LedgerService $ledger): void
    {
        Setting::setMany(Setting::DEFAULTS);
        Account::installDefaults();

        $branches = collect([
            ['name' => 'Head Office – Phnom Penh', 'code' => 'PP', 'phone' => '023 888 111', 'address' => 'Street 271, Toul Kork, Phnom Penh'],
            ['name' => 'Siem Reap Branch', 'code' => 'SR', 'phone' => '063 777 222', 'address' => 'Sivatha Blvd, Siem Reap'],
            ['name' => 'Battambang Branch', 'code' => 'BB', 'phone' => '053 666 333', 'address' => 'Street 3, Battambang'],
        ])->map(fn (array $branch) => Branch::create($branch));
        [$headOffice, $siemReap, $battambang] = $branches->all();

        User::factory()->admin()->create(['name' => 'System Admin', 'email' => 'admin@loanpro.test']);
        User::factory()->accountant()->create(['name' => 'Chanthy Ly', 'email' => 'accountant@loanpro.test', 'locale' => 'km']);
        $manager = User::factory()->manager()->for($headOffice)->create(['name' => 'Sophea Chan', 'email' => 'manager@loanpro.test']);
        $officer = User::factory()->for($headOffice)->create(['name' => 'Dara Kim', 'email' => 'officer@loanpro.test']);

        /** @var array<int, array{manager: User, officers: Collection<int, User>}> $staff */
        $staff = [
            $headOffice->id => ['manager' => $manager, 'officers' => collect([$officer, User::factory()->for($headOffice)->create(['name' => 'Vanna Sok', 'email' => 'vanna@loanpro.test'])])],
            $siemReap->id => [
                'manager' => User::factory()->manager()->for($siemReap)->create(['name' => 'Rithy Pen', 'email' => 'sr.manager@loanpro.test', 'locale' => 'km']),
                'officers' => collect([User::factory()->for($siemReap)->create(['name' => 'Bopha Heng', 'email' => 'sr.officer@loanpro.test', 'locale' => 'km'])]),
            ],
            $battambang->id => [
                'manager' => User::factory()->manager()->for($battambang)->create(['name' => 'Sokha Meas', 'email' => 'bb.manager@loanpro.test']),
                'officers' => collect([User::factory()->for($battambang)->create(['name' => 'Kosal Nhem', 'email' => 'bb.officer@loanpro.test'])]),
            ],
        ];

        foreach ($branches as $branch) {
            $branch->update(['manager_id' => $staff[$branch->id]['manager']->id]);
            $this->seedOpeningCapital($ledger, $branch);
        }

        $products = collect([
            ['name' => 'Micro Business Loan', 'code' => 'MBL', 'interest_rate' => 18, 'interest_method' => InterestMethod::ReducingBalance, 'repayment_frequency' => RepaymentFrequency::Monthly, 'min_amount' => 500, 'max_amount' => 10000, 'min_term' => 6, 'max_term' => 24, 'processing_fee_percent' => 1.5, 'late_penalty_percent' => 5, 'grace_period_days' => 3, 'description' => 'Working capital for small shops and market vendors.'],
            ['name' => 'Personal Loan', 'code' => 'PL', 'interest_rate' => 24, 'interest_method' => InterestMethod::Flat, 'repayment_frequency' => RepaymentFrequency::Monthly, 'min_amount' => 200, 'max_amount' => 5000, 'min_term' => 3, 'max_term' => 12, 'processing_fee_percent' => 2, 'late_penalty_percent' => 5, 'grace_period_days' => 5, 'description' => 'Unsecured consumer loan for salaried borrowers.'],
            ['name' => 'Agriculture Loan', 'code' => 'AGRI', 'interest_rate' => 15, 'interest_method' => InterestMethod::ReducingBalance, 'repayment_frequency' => RepaymentFrequency::Monthly, 'min_amount' => 1000, 'max_amount' => 20000, 'min_term' => 6, 'max_term' => 36, 'processing_fee_percent' => 1, 'late_penalty_percent' => 3, 'grace_period_days' => 7, 'description' => 'Seasonal financing for farmers.'],
            ['name' => 'Weekly Group Loan', 'code' => 'WGL', 'interest_rate' => 30, 'interest_method' => InterestMethod::Flat, 'repayment_frequency' => RepaymentFrequency::Weekly, 'min_amount' => 100, 'max_amount' => 2000, 'min_term' => 8, 'max_term' => 52, 'processing_fee_percent' => 1, 'late_penalty_percent' => 2, 'grace_period_days' => 2, 'description' => 'Small weekly-repayment loans for solidarity groups.'],
        ])->map(fn (array $product) => LoanProduct::create($product));

        $borrowers = collect([[$headOffice, 21], [$siemReap, 14], [$battambang, 10]])
            ->flatMap(fn (array $pair) => Borrower::factory()->count($pair[1])->create([
                'branch_id' => $pair[0]->id,
                'created_by' => $staff[$pair[0]->id]['officers']->first()->id,
            ]));
        Borrower::factory()->blacklisted()->count(2)->create(['branch_id' => $headOffice->id, 'created_by' => $officer->id]);

        foreach ($borrowers->values() as $index => $borrower) {
            $branchStaff = $staff[$borrower->branch_id];
            $manager = $branchStaff['manager'];
            $officers = $branchStaff['officers'];

            $product = $products->random();
            $term = fake()->numberBetween($product->min_term, min($product->max_term, 18));
            $amount = round(fake()->numberBetween((int) $product->min_amount, (int) min($product->max_amount, 8000)) / 50) * 50;
            $appliedAt = Carbon::today()->subDays(fake()->numberBetween(5, 360));

            $loan = $loans->apply([
                'borrower_id' => $borrower->id,
                'loan_product_id' => $product->id,
                'principal_amount' => $amount,
                'term' => $term,
                'loan_officer_id' => $officers->random()->id,
                'purpose' => fake()->randomElement(['Expand shop inventory', 'Buy fertiliser and seeds', 'School fees', 'Motorbike repair', 'Home improvement', 'Buy livestock']),
                'application_date' => $appliedAt->toDateString(),
            ], $officers->first());

            // Leave a handful of applications in the pipeline.
            $scenario = match ($index % 15) {
                0 => 'pending',
                1 => 'approved',
                2 => $index < 15 ? 'rejected' : 'disbursed',
                default => 'disbursed',
            };

            if ($scenario === 'pending') {
                continue;
            }

            if ($scenario === 'rejected') {
                $loans->reject($loan, $manager, 'Insufficient repayment capacity.');

                continue;
            }

            $loans->approve($loan, $manager);

            if ($scenario === 'approved') {
                continue;
            }

            $disbursedAt = $appliedAt->copy()->addDays(fake()->numberBetween(1, 4))->min(today());
            $loans->disburse(
                $loan,
                $manager,
                $disbursedAt,
                $product->repayment_frequency->addPeriods($disbursedAt, 1),
                fake()->randomElement(PaymentMethod::cases()),
            );

            // ~70% of borrowers pay on time; the rest miss recent installments to create arrears.
            $isGoodPayer = fake()->boolean(70);

            foreach ($loan->installments()->get() as $installment) {
                $isRecent = $installment->due_date->gt(today()->subDays(45));

                if ($installment->due_date->isFuture() || (! $isGoodPayer && $isRecent)) {
                    break;
                }

                $repayments->record(
                    $loan->fresh(),
                    $installment->total_due,
                    $installment->due_date->copy()->subDays(fake()->numberBetween(0, 2)),
                    fake()->randomElement([PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::MobileMoney, PaymentMethod::BankTransfer]),
                    $officers->random(),
                );
            }
        }

        $penalties->applyLatePenalties();

        foreach ($branches as $branch) {
            $this->seedOperatingExpenses($ledger, $branch);
        }

        $this->command?->info('Demo data ready. Sign in as admin@loanpro.test (or accountant@ / manager@ / officer@) with password: password');
    }

    /**
     * Owner's capital injected into each branch a year ago, split across cash, bank and mobile money.
     */
    private function seedOpeningCapital(LedgerService $ledger, Branch $branch): void
    {
        $total = $branch->code === 'PP' ? 150000 : 60000;

        $ledger->post(
            JournalType::Manual,
            today()->subMonths(13)->startOfMonth(),
            "Opening capital – {$branch->name}",
            [
                ['account' => Account::system('cash'), 'debit' => $total * 0.4],
                ['account' => Account::system('bank'), 'debit' => $total * 0.5],
                ['account' => Account::system('mobile_money'), 'debit' => $total * 0.1],
                ['account' => Account::system('capital'), 'credit' => $total],
            ],
            $branch->id,
        );
    }

    /**
     * Monthly salaries, rent and utilities for the last 12 months.
     */
    private function seedOperatingExpenses(LedgerService $ledger, Branch $branch): void
    {
        $scale = $branch->code === 'PP' ? 1.0 : 0.5;
        $expenses = Account::query()->whereIn('code', ['5000', '5010', '5020'])->get()->keyBy('code');

        for ($month = 12; $month >= 1; $month--) {
            $date = today()->subMonths($month)->endOfMonth();

            foreach ([['5000', 180, 'bank'], ['5010', 80, 'bank'], ['5020', 25, 'cash']] as [$code, $amount, $paidFrom]) {
                $value = round($amount * $scale * fake()->randomFloat(2, 0.9, 1.1), 2);

                $ledger->post(
                    JournalType::Expense,
                    $date,
                    "{$expenses[$code]->name} – {$date->format('F Y')}",
                    [
                        ['account' => $expenses[$code], 'debit' => $value],
                        ['account' => Account::system($paidFrom), 'credit' => $value],
                    ],
                    $branch->id,
                );
            }
        }
    }
}
