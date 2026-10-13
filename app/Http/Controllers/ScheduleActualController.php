<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActualRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Services\ScheduleBalancingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ScheduleActualController extends Controller
{
    public function __construct(private ScheduleBalancingService $balancing)
    {
    }

    public function update(StoreActualRequest $request, Schedule $schedule, string $date): JsonResponse
    {
        $result = $this->balancing->recordActual(
            $schedule,
            $date,
            (int) $request->validated('actual_qty'),
            (int) $request->validated('version')
        );

        $message = 'Aktual tersimpan.';
        if ($result['finish_delta'] > 0) {
            $message .= " Schedule bertambah {$result['finish_delta']} hari.";
        } elseif ($result['finish_delta'] < 0) {
            $message .= ' Schedule berkurang ' . abs($result['finish_delta']) . ' hari.';
        }
        if ($result['shifted']->isNotEmpty()) {
            $message .= " {$result['shifted']->count()} schedule berikutnya digeser.";
        }

        return ApiResponse::ok([
            'schedule' => (new ScheduleResource($result['schedule']))->resolve(),
            'shifted'  => ScheduleResource::collection($result['shifted'])->resolve(),
        ], $message);
    }
}
