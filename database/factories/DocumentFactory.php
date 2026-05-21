<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Documentazione '.$this->faker->word(),
            'description' => $this->faker->optional()->paragraph(),
            'document_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'parameters' => [
                'sections' => [
                    'sites' => ['enabled' => false, 'description' => '', 'ids' => []],
                    'rooms' => ['enabled' => false, 'description' => '', 'ids' => []],
                    'racks' => ['enabled' => false, 'description' => '', 'ids' => []],
                    'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                    'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
                ],
                'options' => ['include_cover' => true, 'include_toc' => true],
            ],
        ];
    }
}
