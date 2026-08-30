<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesCatalogAccess;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class AulaMateriais extends Component
{
    use ComputesUserInitials;
    use ComputesCatalogAccess;

    public Lesson $lesson;

    public function mount(Lesson $lesson): void
    {
        abort_unless($lesson->isAvailableFor(Auth::user()), 404);

        $this->lesson = $lesson;
    }

    #[Computed]
    public function materials(): Collection
    {
        return $this->lesson->materials()->orderBy('id')->get();
    }

    #[Computed]
    public function catalogIsEmpty(): bool
    {
        return LessonMaterial::query()->count() === 0;
    }

    public function render()
    {
        return view('livewire.membros.aula-materiais');
    }
}
