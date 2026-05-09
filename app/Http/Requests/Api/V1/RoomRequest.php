<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\Room;
use App\Validation\RoomRules;
use Illuminate\Foundation\Http\FormRequest;

class RoomRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', Room::class) ?? false;
        }

        $room = $this->route('room');

        return $room instanceof Room
            ? ($this->user()?->can('update', $room) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = RoomRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
