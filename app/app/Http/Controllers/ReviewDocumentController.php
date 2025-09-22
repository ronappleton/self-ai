<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReviewDocumentController extends Controller
{
    /**
     * Display the review queue.
     */
    public function index(Request $request): View
    {
        $documents = Document::where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('review.documents.index', [
            'documents' => $documents,
        ]);
    }

    /**
     * Approve a document.
     */
    public function approve(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeReview($document);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $document->status = 'approved';
        $document->approved_at = now();
        $document->rejected_at = null;
        $document->rejection_reason = null;
        $document->reviewed_by = $request->user()?->id;
        $document->save();

        $document->consents()->update([
            'status' => 'approved',
            'notes' => $validated['notes'] ?? null,
            'granted_at' => now(),
        ]);

        return redirect()
            ->route('review.documents.index')
            ->with('status', 'Document approved.');
    }

    /**
     * Reject a document.
     */
    public function reject(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeReview($document);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $document->status = 'rejected';
        $document->approved_at = null;
        $document->rejected_at = now();
        $document->rejection_reason = $validated['reason'];
        $document->reviewed_by = $request->user()?->id;
        $document->save();

        $document->consents()->update([
            'status' => 'rejected',
            'notes' => $validated['reason'],
            'granted_at' => null,
        ]);

        return redirect()
            ->route('review.documents.index')
            ->with('status', 'Document rejected.');
    }

    private function authorizeReview(Document $document): void
    {
        if ($document->status !== 'pending') {
            abort(Response::HTTP_CONFLICT, 'Document is not pending review.');
        }
    }
}
