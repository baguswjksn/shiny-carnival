<?php

namespace App\Http\Controllers;

use App\Http\Requests\LineRequest;
use App\Http\Resources\LineResource;
use App\Models\Line;
use App\Services\LineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LineController extends Controller
{
    public function __construct(private LineService $service)
    {
    }

    public function index(): View
    {
        return view('lines.index');
    }

    public function list(): JsonResponse
    {
        return ApiResponse::ok(LineResource::collection($this->service->list())->resolve());
    }

    public function store(LineRequest $request): JsonResponse
    {
        $line = $this->service->create($request->validated());

        return ApiResponse::ok((new LineResource($line))->resolve(), 'Line berhasil ditambahkan.', 201);
    }

    public function update(LineRequest $request, Line $line): JsonResponse
    {
        $line = $this->service->update($line, $request->validated());

        return ApiResponse::ok((new LineResource($line))->resolve(), 'Line berhasil diperbarui.');
    }

    public function destroy(Line $line): JsonResponse
    {
        $this->service->delete($line);

        return ApiResponse::ok(null, 'Line berhasil dihapus.');
    }
}
