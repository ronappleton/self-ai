<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Document;
use App\Support\Ingestion\PiiScrubber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IngestionController extends Controller
{
    /**
     * Ingest a text document.
     */
    public function ingestText(Request $request, PiiScrubber $scrubber): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'consent_scope' => ['required', 'string', 'max:255'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:100'],
            'metadata' => ['sometimes', 'array'],
            'retention_class' => ['nullable', 'string', 'max:255'],
            'bypass_pii_scrub' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $bypass = (bool) ($validated['bypass_pii_scrub'] ?? false);

        if ($bypass && (! $user || ! $user->hasRole('owner'))) {
            abort(Response::HTTP_FORBIDDEN, 'Only owners may bypass PII scrubbing.');
        }

        $text = $validated['text'];
        $sanitized = $bypass ? $text : $scrubber->scrub($text);

        $document = $this->storeDocument(
            type: 'text',
            source: $validated['source'],
            consentScope: $validated['consent_scope'],
            retentionClass: $validated['retention_class'] ?? null,
            tags: $validated['tags'] ?? [],
            metadata: $validated['metadata'] ?? [],
            mimeType: 'text/plain',
            originalFilename: null,
            originalContents: $text,
            sanitizedContent: $sanitized,
            scrubbed: ! $bypass,
            submittedBy: $user?->id
        );

        return response()->json([
            'document_id' => $document->id,
            'status' => $document->status,
            'pii_scrubbed' => $document->pii_scrubbed,
            'sanitized_preview' => $document->sanitized_content !== null
                ? Str::limit($document->sanitized_content, 400)
                : null,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Ingest a file document (PDF or audio).
     */
    public function ingestFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/pdf,audio/mpeg,audio/wav,audio/wave,audio/x-wav,audio/flac,audio/x-flac,audio/ogg,audio/webm,audio/opus'],
            'consent_scope' => ['required', 'string', 'max:255'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:100'],
            'metadata' => ['sometimes', 'array'],
            'retention_class' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $validated['file'];
        $user = $request->user();
        $metadata = $validated['metadata'] ?? [];
        $metadata['size'] = $file->getSize();

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to read uploaded file.');
        }

        $document = $this->storeDocument(
            type: 'file',
            source: $validated['source'],
            consentScope: $validated['consent_scope'],
            retentionClass: $validated['retention_class'] ?? null,
            tags: $validated['tags'] ?? [],
            metadata: $metadata,
            mimeType: $file->getClientMimeType(),
            originalFilename: $file->getClientOriginalName(),
            originalContents: $contents,
            sanitizedContent: null,
            scrubbed: false,
            submittedBy: $user?->id
        );

        return response()->json([
            'document_id' => $document->id,
            'status' => $document->status,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Delete a document and revoke its consent.
     */
    public function destroyDocument(Document $document): JsonResponse
    {
        $this->removeDocumentFromStorage($document);

        $document->status = 'deleted';
        $document->approved_at = null;
        $document->rejected_at = null;
        $document->rejection_reason = null;
        $document->reviewed_by = null;
        $document->save();
        $document->delete();

        $document->consents()->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return response()->json([
            'status' => 'deleted',
            'document_id' => $document->id,
        ]);
    }

    /**
     * Delete all documents by source.
     */
    public function destroyBySource(string $source): JsonResponse
    {
        $documents = Document::where('source', $source)->get();
        $count = 0;

        foreach ($documents as $document) {
            $this->removeDocumentFromStorage($document);
            $document->status = 'deleted';
            $document->approved_at = null;
            $document->rejected_at = null;
            $document->rejection_reason = null;
            $document->reviewed_by = null;
            $document->save();
            $document->delete();
            $document->consents()->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);
            $count++;
        }

        return response()->json([
            'status' => 'deleted',
            'source' => $source,
            'documents_removed' => $count,
        ]);
    }

    private function storeDocument(
        string $type,
        string $source,
        string $consentScope,
        ?string $retentionClass,
        array $tags,
        array $metadata,
        ?string $mimeType,
        ?string $originalFilename,
        string $originalContents,
        ?string $sanitizedContent,
        bool $scrubbed,
        ?int $submittedBy
    ): Document {
        $documentId = (string) Str::uuid();
        $datePrefix = now()->format('Y/m/d');
        $directory = "sources/{$source}/{$datePrefix}/{$documentId}";
        $extension = $this->guessExtension($type, $mimeType, $originalFilename);
        $filename = 'original'.($extension ? ".{$extension}" : '');
        $storagePath = "{$directory}/{$filename}";

        Storage::disk('minio')->put($storagePath, $originalContents);

        $document = Document::create([
            'id' => $documentId,
            'type' => $type,
            'source' => $source,
            'status' => 'pending',
            'sha256' => hash('sha256', $originalContents),
            'storage_disk' => 'minio',
            'storage_path' => $storagePath,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'metadata' => $metadata,
            'tags' => $tags,
            'retention_class' => $retentionClass,
            'consent_scope' => $consentScope,
            'pii_scrubbed' => $scrubbed,
            'sanitized_content' => $sanitizedContent,
            'submitted_by' => $submittedBy,
        ]);

        Consent::updateOrCreate(
            ['source' => $source, 'document_id' => $document->id],
            [
                'user_id' => $submittedBy,
                'scope' => $consentScope,
                'status' => 'pending',
            ]
        );

        return $document;
    }

    private function guessExtension(string $type, ?string $mime, ?string $originalFilename): ?string
    {
        if ($originalFilename) {
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            if ($extension) {
                return strtolower($extension);
            }
        }

        if ($type === 'text') {
            return 'txt';
        }

        return match ($mime) {
            'application/pdf' => 'pdf',
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/wave', 'audio/x-wav' => 'wav',
            'audio/flac', 'audio/x-flac' => 'flac',
            'audio/ogg', 'audio/webm', 'audio/opus' => 'ogg',
            default => null,
        };
    }

    private function removeDocumentFromStorage(Document $document): void
    {
        if ($document->storage_path) {
            Storage::disk($document->storage_disk)->delete($document->storage_path);
        }
    }
}
