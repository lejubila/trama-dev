<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\User;
use App\Support\Guide\GuideOffer;
use App\Support\Guide\GuideTours;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Hands the guided tours to the browser (resources/js/guide/guide.js) and
 * renders the "what do you want to do?" menu and the first-visit invite.
 * Re-rendered with the layout on every navigation, so the data is fresh.
 */
class Guide extends Component
{
    /** @var array<string, mixed> */
    public array $config = [];

    /** @var array<string, array<string, mixed>> */
    public array $tours = [];

    public bool $hasTenantTours = false;

    public function __construct(GuideTours $guideTours)
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->canManageData()) {
            return;
        }

        $this->tours = $guideTours->forUser($user);
        $this->hasTenantTours = array_diff(array_keys($this->tours), ['tenant']) !== [];

        $this->config = [
            'tours' => $this->tours,
            'order' => array_values(array_intersect(GuideTours::ORDER, array_keys($this->tours))),
            'routes' => $guideTours->routeTemplates(),
            'lastCreated' => session()->pull(GuideTours::LAST_CREATED_KEY),
            'offer' => GuideOffer::pending($user),
            'dismissUrl' => route('guide.offer.dismiss', absolute: false),
            'strings' => [
                'next' => __('guide.next'),
                'exit' => __('guide.exit'),
                'retry' => __('guide.retry'),
                'goToStep' => __('guide.go_to_step'),
                'stepOf' => __('guide.step_of'),
                'notFoundTitle' => __('guide.not_found_title'),
                'notFoundText' => __('guide.not_found_text'),
                'doneTitle' => __('guide.done_title'),
                'doneText' => __('guide.done_text'),
                'doneLast' => __('guide.done_last'),
                'continueWith' => __('guide.continue_with'),
                'close' => __('guide.close'),
            ],
        ];
    }

    public function shouldRender(): bool
    {
        return $this->tours !== [];
    }

    public function render(): View
    {
        return view('components.guide');
    }
}
