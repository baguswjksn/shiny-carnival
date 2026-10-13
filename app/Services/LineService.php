<?php

namespace App\Services;

use App\Exceptions\ScheduleConflictException;
use App\Models\Line;
use Illuminate\Database\Eloquent\Collection;

class LineService
{
    public function list(): Collection
    {
        return Line::withCount('schedules')->orderBy('code')->get();
    }

    public function create(array $data): Line
    {
        return Line::create($data)->refresh()->loadCount('schedules');
    }

    public function update(Line $line, array $data): Line
    {
        $line->update($data);

        return $line->loadCount('schedules');
    }

    public function delete(Line $line): void
    {
        if ($line->schedules()->exists()) {
            throw new ScheduleConflictException('Line masih dipakai schedule. Hapus schedule-nya dulu atau nonaktifkan line.');
        }

        $line->delete();
    }
}
