<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TopologySnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopologySnapshot>
 */
class TopologySnapshotFactory extends Factory
{
    protected $model = TopologySnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'snapshot_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'image_path' => 'topology-snapshots/test/'.$this->faker->uuid().'.png',
            'view_state' => [
                'siteId' => 0,
                'statusFilter' => '',
                'vlanFilter' => 0,
                'layout' => 'cose-bilkent',
                'filterTypes' => [],
            ],
        ];
    }
}
