@extends('layouts.app')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
<div style="max-width:960px;margin:0 auto;padding:48px 24px;">
    <h1 style="font-size:28px;font-weight:600;margin-bottom:24px;">Ingestion Review Queue</h1>

    @if(session('status'))
        <div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:12px 16px;border-radius:6px;margin-bottom:16px;">
            {{ session('status') }}
        </div>
    @endif

    @if($documents->isEmpty())
        <p style="color:#475569;">No documents are waiting for review.</p>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(15,23,42,0.08);border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;">
                        <th style="padding:12px 16px;font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:#475569;">Source</th>
                        <th style="padding:12px 16px;font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:#475569;">Type</th>
                        <th style="padding:12px 16px;font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:#475569;">Submitted</th>
                        <th style="padding:12px 16px;font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:#475569;">Preview</th>
                        <th style="padding:12px 16px;font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:#475569;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($documents as $document)
                    <tr style="border-top:1px solid #e2e8f0;vertical-align:top;">
                        <td style="padding:16px;">
                            <div style="font-weight:600;">{{ $document->source }}</div>
                            <div style="font-size:13px;color:#64748b;">Retention: {{ $document->retention_class ?? 'standard' }}</div>
                            <div style="font-size:13px;color:#64748b;">Consent: {{ $document->consent_scope }}</div>
                        </td>
                        <td style="padding:16px;font-size:14px;">{{ ucfirst($document->type) }}</td>
                        <td style="padding:16px;font-size:14px;color:#475569;">{{ $document->created_at?->diffForHumans() }}</td>
                        <td style="padding:16px;font-size:14px;color:#1e293b;">
                            @if($document->sanitized_content)
                                {{ Str::limit($document->sanitized_content, 180) }}
                            @else
                                <span style="font-style:italic;color:#94a3b8;">Binary asset</span>
                            @endif
                        </td>
                        <td style="padding:16px;">
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <form method="POST" action="{{ route('review.documents.approve', $document) }}" style="display:flex;flex-direction:column;gap:8px;">
                                    @csrf
                                    <label for="notes-{{ $document->id }}" style="font-size:13px;color:#475569;">Notes (optional)</label>
                                    <textarea id="notes-{{ $document->id }}" name="notes" rows="2" style="width:100%;padding:8px 10px;border:1px solid #cbd5f5;border-radius:4px;resize:vertical;">{{ old('notes') }}</textarea>
                                    <button type="submit" style="background:#16a34a;color:#fff;padding:8px 12px;border:none;border-radius:4px;cursor:pointer;">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('review.documents.reject', $document) }}" style="display:flex;flex-direction:column;gap:8px;">
                                    @csrf
                                    <label for="reason-{{ $document->id }}" style="font-size:13px;color:#475569;">Rejection reason</label>
                                    <textarea id="reason-{{ $document->id }}" name="reason" rows="2" required style="width:100%;padding:8px 10px;border:1px solid #cbd5f5;border-radius:4px;resize:vertical;">{{ old('reason') }}</textarea>
                                    <button type="submit" style="background:#dc2626;color:#fff;padding:8px 12px;border:none;border-radius:4px;cursor:pointer;">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            {{ $documents->links() }}
        </div>
    @endif
</div>
@endsection
