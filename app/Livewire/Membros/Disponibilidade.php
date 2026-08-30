<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\MentorAvailability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Disponibilidade extends Component
{
    use ComputesUserInitials;

    public string $dayOfWeek = '1';

    public string $startTime = '';

    public string $endTime = '';

    #[Computed]
    public function blocks(): Collection
    {
        return MentorAvailability::query()
            ->where('mentor_id', Auth::id())
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function addBlock(): void
    {
        $this->validate([
            'dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
        ]);

        $overlaps = MentorAvailability::query()
            ->where('mentor_id', Auth::id())
            ->where('day_of_week', $this->dayOfWeek)
            ->get()
            ->contains(fn (MentorAvailability $block) => $this->startTime < $block->end_time->format('H:i')
                && $this->endTime > $block->start_time->format('H:i'));

        if ($overlaps) {
            $this->addError('startTime', 'Esse horário sobrepõe um bloco já cadastrado.');

            return;
        }

        MentorAvailability::create([
            'mentor_id' => Auth::id(),
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ]);

        $this->reset('startTime', 'endTime');
        unset($this->blocks);
    }

    public function removeBlock(int $blockId): void
    {
        MentorAvailability::query()
            ->where('id', $blockId)
            ->where('mentor_id', Auth::id())
            ->delete();

        unset($this->blocks);
    }

    public function render()
    {
        return view('livewire.membros.disponibilidade');
    }
}
