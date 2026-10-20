<x-layouts.admin :title="__('Edit Borrower')" :breadcrumbs="['Borrowers' => route('borrowers.index'), $borrower->full_name => route('borrowers.show', $borrower), 'Edit']">
    <form method="POST" action="{{ route('borrowers.update', $borrower) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('borrowers._form')
    </form>
</x-layouts.admin>
