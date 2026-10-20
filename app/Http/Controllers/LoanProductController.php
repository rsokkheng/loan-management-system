<?php

namespace App\Http\Controllers;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Http\Requests\LoanProductRequest;
use App\Models\ActivityLog;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    public function index(): View
    {
        $products = LoanProduct::query()->withCount('loans')->orderBy('name')->get();

        return view('loan-products.index', compact('products'));
    }

    public function create(): View
    {
        return view('loan-products.create', $this->formData(new LoanProduct(['is_active' => true])));
    }

    public function store(LoanProductRequest $request): RedirectResponse
    {
        $product = LoanProduct::create($request->validated());

        ActivityLog::record('created', "Loan product {$product->name} created", $product);

        return redirect()->route('loan-products.index')->with('success', __('Loan product created.'));
    }

    public function edit(LoanProduct $loanProduct): View
    {
        return view('loan-products.edit', $this->formData($loanProduct));
    }

    public function update(LoanProductRequest $request, LoanProduct $loanProduct): RedirectResponse
    {
        $loanProduct->update($request->validated());

        ActivityLog::record('updated', "Loan product {$loanProduct->name} updated", $loanProduct);

        return redirect()->route('loan-products.index')->with('success', __('Loan product updated. Existing loans keep their original terms.'));
    }

    public function destroy(LoanProduct $loanProduct): RedirectResponse
    {
        if ($loanProduct->loans()->exists()) {
            return back()->with('error', __('This product has loans attached. Deactivate it instead.'));
        }

        $loanProduct->delete();

        ActivityLog::record('deleted', "Loan product {$loanProduct->name} deleted");

        return redirect()->route('loan-products.index')->with('success', __('Loan product deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(LoanProduct $product): array
    {
        return [
            'product' => $product,
            'interestMethods' => InterestMethod::cases(),
            'frequencies' => RepaymentFrequency::cases(),
        ];
    }
}
