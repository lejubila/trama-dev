<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LinkGroupMode;
use App\Models\LinkGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkGroup>
 */
class LinkGroupFactory extends Factory
{
    protected $model = LinkGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Po'.$this->faker->unique()->numberBetween(1, 99),
            'mode' => LinkGroupMode::Lacp,
            'notes' => null,
        ];
    }
}
