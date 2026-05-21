<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RackPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RackPhoto>
 */
class RackPhotoFactory extends Factory
{
    protected $model = RackPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo_path' => 'rack-photos/test/'.$this->faker->uuid().'.jpg',
            'caption' => $this->faker->optional()->sentence(3),
        ];
    }
}
