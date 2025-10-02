<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Models\RfcProposal;
use App\Support\Builds\BuildProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class BuildController extends Controller
{
    public function __construct(private readonly BuildProcessor $processor)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfc_id' => ['required', 'uuid', Rule::exists('rfc_proposals', 'id')],
            'diff' => ['required', 'array'],
            'diff.summary' => ['required', 'string'],
            'diff.files' => ['sometimes', 'array'],
            'diff.files.*.path' => ['required', 'string'],
            'diff.files.*.change' => ['sometimes', 'string'],
            'test_report' => ['required', 'array'],
            'test_report.static_analysis.status' => ['required', 'string'],
            'test_report.static_analysis.summary' => ['sometimes', 'string'],
            'test_report.unit.status' => ['required', 'string'],
            'test_report.unit.summary' => ['sometimes', 'string'],
            'test_report.e2e.status' => ['required', 'string'],
            'test_report.e2e.summary' => ['sometimes', 'string'],
            'test_report.performance.status' => ['required', 'string'],
            'test_report.performance.summary' => ['sometimes', 'string'],
            'rollback_plan' => ['required', 'string'],
            'artefacts' => ['sometimes', 'array'],
            'artefacts.*.name' => ['required', 'string'],
            'artefacts.*.path' => ['required', 'string'],
        ]);

        $rfc = RfcProposal::query()->findOrFail($validated['rfc_id']);
        $build = Build::create([
            'rfc_id' => $rfc->id,
            'status' => 'running',
        ]);

        $build = $this->processor->run($rfc, $build, $validated);

        return response()->json([
            'build_id' => $build->id,
            'rfc_id' => $build->rfc_id,
            'status' => $build->status,
            'status_reason' => $build->status_reason,
            'links' => [
                'diff' => $build->diffUrl(),
                'test_report' => $build->testReportUrl(),
                'artefacts' => $build->artefactsUrl(),
            ],
            'metadata' => $build->metadata,
        ], Response::HTTP_CREATED);
    }

    public function show(Build $build): JsonResponse
    {
        return response()->json([
            'build_id' => $build->id,
            'rfc_id' => $build->rfc_id,
            'status' => $build->status,
            'status_reason' => $build->status_reason,
            'metadata' => $build->metadata,
            'manifest' => $this->readJson(
                data_get($build->metadata, 'manifest_disk'),
                data_get($build->metadata, 'manifest_path'),
            ),
            'links' => [
                'diff' => $build->diffUrl(),
                'test_report' => $build->testReportUrl(),
                'artefacts' => $build->artefactsUrl(),
            ],
            'diff' => $this->readJson($build->diff_disk, $build->diff_path),
            'test_report' => $this->readJson($build->test_report_disk, $build->test_report_path),
            'artefacts' => $this->readJson($build->artefacts_disk, $build->artefacts_path),
        ]);
    }

    private function readJson(?string $disk, ?string $path): mixed
    {
        if (! $disk || ! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $contents = Storage::disk($disk)->get($path);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
