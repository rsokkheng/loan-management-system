<x-layouts.admin :title="__('New Borrower')" :breadcrumbs="['Borrowers' => route('borrowers.index'), 'New']">
    <form method="POST" action="{{ route('borrowers.store') }}" enctype="multipart/form-data">
        @csrf
        @include('borrowers._form')
    </form>
</x-layouts.admin>
