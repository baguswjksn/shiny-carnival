<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Services\ScheduleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $service)
    {
    }

    public function index(): View
    {
        return view('schedules.index');
    }

    public function list(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from'    => ['nullable', 'date_format:Y-m-d'],
            'to'      => ['nullable', 'date_format:Y-m-d'],
            'line_id' => ['nullable', 'integer'],
            'status'  => ['nullable', 'in:planned,running,done'],
        ]);

        return ApiResponse::ok(ScheduleResource::collection($this->service->list($filters))->resolve());
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->create($request->validated());

        return ApiResponse::ok($this->payload($schedule), 'Schedule berhasil dibuat.', 201);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $schedule = $this->service->update($schedule, $request->validated());

        return ApiResponse::ok($this->payload($schedule), 'Schedule berhasil diperbarui.');
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        $this->service->delete($schedule);

        return ApiResponse::ok(null, 'Schedule berhasil dihapus.');
    }

    private function payload(Schedule $schedule): array
    {
        return [
            'schedule' => (new ScheduleResource($schedule))->resolve(),
            'shifted'  => [],
        ];
    }
}
