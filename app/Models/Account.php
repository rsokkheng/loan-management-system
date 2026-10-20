<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

#[Fillable(['code', 'name', 'name_km', 'type', 'parent_id', 'system_key', 'is_cash', 'is_active', 'description'])]
class Account extends Model
{
    /**
     * Accounts every installation needs. Entries with a system key are used by automatic postings.
     *
     * @var list<array{code: string, name: string, name_km: string, type: AccountType, system_key?: string, is_cash?: bool}>
     */
    public const DEFAULT_CHART = [
        ['code' => '1000', 'name' => 'Cash on Hand', 'name_km' => 'សាច់ប្រាក់ក្នុងដៃ', 'type' => AccountType::Asset, 'system_key' => 'cash', 'is_cash' => true],
        ['code' => '1010', 'name' => 'Bank Account', 'name_km' => 'គណនីធនាគារ', 'type' => AccountType::Asset, 'system_key' => 'bank', 'is_cash' => true],
        ['code' => '1020', 'name' => 'Mobile Money Wallet', 'name_km' => 'កាបូបលុយអេឡិចត្រូនិក', 'type' => AccountType::Asset, 'system_key' => 'mobile_money', 'is_cash' => true],
        ['code' => '1100', 'name' => 'Loans Receivable', 'name_km' => 'ឥណទានត្រូវទទួល', 'type' => AccountType::Asset, 'system_key' => 'loan_portfolio'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'name_km' => 'គណនីត្រូវសង', 'type' => AccountType::Liability],
        ['code' => '2100', 'name' => 'Borrowings', 'name_km' => 'ប្រាក់កម្ចីពីស្ថាប័ន', 'type' => AccountType::Liability],
        ['code' => '3000', 'name' => "Owner's Capital", 'name_km' => 'ដើមទុនម្ចាស់', 'type' => AccountType::Equity, 'system_key' => 'capital'],
        ['code' => '3100', 'name' => 'Retained Earnings', 'name_km' => 'ប្រាក់ចំណេញរក្សាទុក', 'type' => AccountType::Equity, 'system_key' => 'retained_earnings'],
        ['code' => '4000', 'name' => 'Interest Income', 'name_km' => 'ចំណូលការប្រាក់', 'type' => AccountType::Income, 'system_key' => 'interest_income'],
        ['code' => '4010', 'name' => 'Processing Fee Income', 'name_km' => 'ចំណូលកម្រៃសេវា', 'type' => AccountType::Income, 'system_key' => 'fee_income'],
        ['code' => '4020', 'name' => 'Penalty Income', 'name_km' => 'ចំណូលពិន័យ', 'type' => AccountType::Income, 'system_key' => 'penalty_income'],
        ['code' => '4900', 'name' => 'Other Income', 'name_km' => 'ចំណូលផ្សេងៗ', 'type' => AccountType::Income],
        ['code' => '5000', 'name' => 'Salaries & Wages', 'name_km' => 'ប្រាក់បៀវត្សរ៍', 'type' => AccountType::Expense],
        ['code' => '5010', 'name' => 'Rent', 'name_km' => 'ថ្លៃឈ្នួល', 'type' => AccountType::Expense],
        ['code' => '5020', 'name' => 'Utilities', 'name_km' => 'ថ្លៃទឹកភ្លើង', 'type' => AccountType::Expense],
        ['code' => '5030', 'name' => 'Office Supplies', 'name_km' => 'សម្ភារៈការិយាល័យ', 'type' => AccountType::Expense],
        ['code' => '5040', 'name' => 'Transport', 'name_km' => 'ថ្លៃធ្វើដំណើរ', 'type' => AccountType::Expense],
        ['code' => '5100', 'name' => 'Loan Loss Expense', 'name_km' => 'ចំណាយលើការខាតបង់ឥណទាន', 'type' => AccountType::Expense, 'system_key' => 'loan_loss'],
        ['code' => '5900', 'name' => 'Other Expenses', 'name_km' => 'ចំណាយផ្សេងៗ', 'type' => AccountType::Expense],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Create any missing default accounts. Safe to run repeatedly.
     */
    public static function installDefaults(): void
    {
        foreach (self::DEFAULT_CHART as $account) {
            static::query()->firstOrCreate(['code' => $account['code']], $account);
        }
    }

    /**
     * Find an account used by automatic postings, installing the default chart if needed.
     */
    public static function system(string $key): self
    {
        $account = static::query()->where('system_key', $key)->first();

        if (! $account) {
            static::installDefaults();
            $account = static::query()->where('system_key', $key)->first();
        }

        return $account ?? throw new RuntimeException("System account [{$key}] is missing from the chart of accounts.");
    }

    /**
     * Name in the current language, falling back to English.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'km' && $this->name_km ? $this->name_km : $this->name);
    }

    /**
     * @param  Builder<Account>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Account>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, AccountType ...$types): void
    {
        $query->whereIn('type', $types);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
