<x-layouts.admin :title="$borrower->full_name" :breadcrumbs="['Borrowers' => route('borrowers.index'), $borrower->full_name]">
    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <x-avatar :borrower="$borrower" size="lg" class="mb-3" />
                    <h4 class="mb-0">{{ $borrower->full_name }}</h4>
                    <div class="text-secondary"><code>{{ $borrower->code }}</code></div>
                    @if ($borrower->is_blacklisted)
                        <span class="badge text-bg-danger mt-2">{{ __('Blacklisted') }}</span>
                    @endif
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        @unless ($borrower->is_blacklisted)
                            <a href="{{ route('loans.create', ['borrower' => $borrower->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> {{ __('New Loan') }}</a>
                        @endunless
                        <a href="{{ route('borrowers.edit', $borrower) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i> {{ __('Edit') }}</a>
                        @can('delete-records')
                            <form method="POST" action="{{ route('borrowers.destroy', $borrower) }}" data-confirm="{{ __('Delete this borrower? This cannot be undone.') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                        @endcan
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ([
                        'Branch' => $borrower->branch?->name ?? '—',
                        'Gender' => $borrower->gender->label(),
                        'Date of birth' => $borrower->date_of_birth ? $borrower->date_of_birth->translatedFormat('d M Y').' ('.__(':age years old', ['age' => $borrower->date_of_birth->age]).')' : '—',
                        'National ID' => $borrower->national_id,
                        'Phone' => $borrower->phone,
                        'Email' => $borrower->email ?: '—',
                        'Address' => $borrower->full_address ?: '—',
                        'Occupation' => $borrower->occupation ?: '—',
                        'Employer' => $borrower->employer ?: '—',
                    ] as $label => $value)
                        <li class="list-group-item d-flex justify-content-between gap-3">
                            <span class="text-secondary">{{ __($label) }}</span>
                            <span class="text-end">{{ $value }}</span>
                        </li>
                    @endforeach
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-secondary">{{ __('Monthly income') }}</span>
                        <span>@money($borrower->monthly_income)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-secondary">{{ __('Registered') }}</span>
                        <span>{{ $borrower->created_at->translatedFormat('d M Y') }} {{ __('by') }} {{ $borrower->creator?->name ?? __('System') }}</span>
                    </li>
                </ul>
                @if ($borrower->notes)
                    <div class="card-footer small"><strong>{{ __('Notes:') }}</strong> {{ $borrower->notes }}</div>
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            @php
                $active = $borrower->loans->where('status', \App\Enums\LoanStatus::Active);
            @endphp
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-primary"><i class="bi bi-bank"></i></span>
                        <div class="info-box-content"><span class="info-box-text">{{ __('Total Loans') }}</span><span class="info-box-number">{{ $borrower->loans->count() }}</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-success"><i class="bi bi-cash-stack"></i></span>
                        <div class="info-box-content"><span class="info-box-text">{{ __('Total Repaid') }}</span><span class="info-box-number">@money($borrower->loans->sum('amount_paid'))</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-warning"><i class="bi bi-wallet2"></i></span>
                        <div class="info-box-content"><span class="info-box-text">{{ __('Outstanding') }}</span><span class="info-box-number">@money($active->sum('outstanding_balance'))</span></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('Loan History') }}</h3></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-nowrap align-middle mb-0">
                            <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Product') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Outstanding') }}</th><th>{{ __('Applied') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse ($borrower->loans as $loan)
                                    <tr>
                                        <td><a href="{{ route('loans.show', $loan) }}">{{ $loan->reference }}</a></td>
                                        <td>{{ $loan->product->name }}</td>
                                        <td class="text-end">@money($loan->principal_amount)</td>
                                        <td class="text-end">@money($loan->status === \App\Enums\LoanStatus::Active ? $loan->outstanding_balance : 0)</td>
                                        <td>{{ $loan->application_date->translatedFormat('d M Y') }}</td>
                                        <td><x-badge :status="$loan->status" /></td>
                                    </tr>
                                @empty
                                    <x-empty-state colspan="6" icon="bank" :title="__('No loans yet')" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">{{ __('KYC Documents') }}</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('borrowers.documents.store', $borrower) }}" enctype="multipart/form-data" class="row g-2 align-items-start mb-3">
                        @csrf
                        <div class="col-md-5">
                            <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror" placeholder="{{ __('Document title (e.g. National ID)') }}" value="{{ old('title') }}" required>
                        </div>
                        <div class="col-md-5">
                            <input type="file" name="document" class="form-control form-control-sm @error('document') is-invalid @enderror" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i> {{ __('Upload') }}</button>
                        </div>
                        <div class="col-12 form-text mt-0">{{ __('PDF, image or Word document up to 5 MB.') }}</div>
                    </form>

                    <ul class="list-group">
                        @forelse ($borrower->documents as $document)
                            <li class="list-group-item d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-text fs-4 text-secondary"></i>
                                <div class="me-auto">
                                    <div class="fw-semibold">{{ $document->title }}</div>
                                    <div class="small text-secondary">{{ $document->original_name }} · {{ \Illuminate\Support\Number::fileSize($document->size) }} · {{ $document->created_at->translatedFormat('d M Y') }} {{ __('by') }} {{ $document->uploader?->name ?? __('System') }}</div>
                                </div>
                                <a href="{{ route('documents.show', $document) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm="{{ __('Delete this document?') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-secondary text-center py-4">{{ __('No documents uploaded.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
