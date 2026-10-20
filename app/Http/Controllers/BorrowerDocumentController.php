<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\BorrowerDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BorrowerDocumentController extends Controller
{
    public function store(Request $request, Borrower $borrower): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ]);

        $file = $request->file('document');

        $borrower->documents()->create([
            'title' => $validated['title'],
            'file_path' => $file->store("documents/{$borrower->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', __('Document uploaded.'));
    }

    public function show(BorrowerDocument $document): StreamedResponse
    {
        $this->ensureVisible($document);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroy(BorrowerDocument $document): RedirectResponse
    {
        $this->ensureVisible($document);
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', __('Document deleted.'));
    }

    /**
     * Documents aren't branch-scoped themselves, so check their borrower is.
     */
    private function ensureVisible(BorrowerDocument $document): void
    {
        abort_unless(Borrower::query()->whereKey($document->borrower_id)->exists(), 404);
    }
}
