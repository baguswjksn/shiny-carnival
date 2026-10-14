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
        $data = $request->validated();
        $schedule = $this->service->create($data);

        return ApiResponse::ok(
            $this->payload($schedule),
            $this->message('Schedule berhasil dibuat.', $schedule, $data['start_date']),
            201
        );
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validated();
        $schedule = $this->service->update($schedule, $data);

        return ApiResponse::ok(
            $this->payload($schedule),
            $this->message('Schedule berhasil diperbarui.', $schedule, $data['start_date'])
        );
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        $this->service->delete($schedule);

        return ApiResponse::ok(null, 'Schedule berhasil dihapus.');
    }

    /** Tell the admin when the start date was moved because the line was already taken. */
    private function message(string $base, Schedule $schedule, string $requestedStart): string
    {
        $actual = $schedule->start_date->toDateString();

        return $actual === $requestedStart
            ? $base
            : "{$base} Start bentrok dengan schedule lain di line ini, otomatis digeser ke {$actual}.";
    }

    private function payload(Schedule $schedule): array
    {
        return [
            'schedule' => (new ScheduleResource($schedule))->resolve(),
            'shifted'  => [],
        ];
    }
}
