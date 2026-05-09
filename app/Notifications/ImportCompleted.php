<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Import;
use Illuminate\Notifications\Notification;

class ImportCompleted extends Notification
{
    public function __construct(public readonly Import $import) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $created = (int) ($this->import->summary['created'] ?? 0);
        $errors = count((array) ($this->import->summary['errors'] ?? []));

        return [
            'kind' => 'import.completed',
            'import_id' => $this->import->getKey(),
            'status' => $this->import->status,
            'title' => $this->import->status === 'completed'
                ? "Import #{$this->import->getKey()} completato"
                : "Import #{$this->import->getKey()} fallito",
            'message' => $this->import->status === 'completed'
                ? "{$created} dispositivi creati, {$errors} errori."
                : "{$errors} errori, transazione annullata.",
            'url' => route('imports.index'),
        ];
    }
}
