<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Actions\Import\ImportEquipmentCsv;
use App\Models\Equipment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public int $step = 1;

    #[Validate(['file' => 'required|file|mimes:csv,txt|max:5120'])]
    public ?UploadedFile $file = null;

    public bool $ignoreErrors = false;

    /**
     * @var list<string>
     */
    public array $headerPreview = [];

    /**
     * @var list<list<string>>
     */
    public array $rowsPreview = [];

    /**
     * @var array{created: int, skipped: int, errors: list<array{row: int, messages: list<string>}>}|null
     */
    public ?array $summary = null;

    public function mount(): void
    {
        $this->authorize('create', Equipment::class);
    }

    public function toPreview(): void
    {
        $this->validate();

        $absolute = $this->file->getRealPath();

        $csv = Reader::createFromPath($absolute, 'r');
        $csv->setHeaderOffset(0);
        $this->headerPreview = $csv->getHeader();

        $this->rowsPreview = [];
        $count = 0;
        foreach ($csv->getRecords() as $row) {
            $this->rowsPreview[] = array_values($row);
            if (++$count >= 10) {
                break;
            }
        }

        $this->step = 2;
    }

    public function runImport(ImportEquipmentCsv $action): void
    {
        $this->authorize('create', Equipment::class);
        $this->validate();

        // Persist the upload to a stable path under storage/app/imports/ so
        // the audit trail and the Imports model can link back to the source.
        $stored = $this->file->storeAs(
            'imports',
            'equipment-'.now()->format('Ymd-His-').uniqid().'.csv',
            'local',
        );
        $absolute = Storage::disk('local')->path($stored);

        $import = $action->execute(
            absolutePath: $absolute,
            userId: auth()->id(),
            ignoreErrors: $this->ignoreErrors,
        );

        $this->summary = $import->summary;
        $this->step = 3;

        $type = $import->status === 'completed' ? 'success' : 'error';
        $created = $import->summary['created'] ?? 0;
        $errors = count($import->summary['errors'] ?? []);
        $msg = $import->status === 'completed'
            ? "Import completato: {$created} creati, {$errors} errori."
            : "Import fallito: {$errors} errori, transazione annullata.";
        $this->dispatch('toast', type: $type, message: $msg);
    }

    public function reset_(): void
    {
        $this->reset(['file', 'headerPreview', 'rowsPreview', 'summary', 'ignoreErrors']);
        $this->step = 1;
    }

    public function render(): View
    {
        return view('livewire.equipment.import');
    }
}
