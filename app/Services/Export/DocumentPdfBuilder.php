<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\TopologySnapshot;
use App\Models\VpnRemoteAccess;
use App\Models\VpnSiteToSite;
use App\Models\WifiNetwork;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

class DocumentPdfBuilder
{
    /**
     * Render the document to a PDF on the public disk and update the
     * Document with the resulting path + timestamp. Returns the relative
     * disk path so the caller can use Storage::url() / response()->download().
     */
    public function build(Document $document): string
    {
        $tenantId = (int) $document->tenant_id;
        $data = $this->loadData($document);

        $parameters = is_array($document->parameters) ? $document->parameters : [];
        $options = is_array($parameters['options'] ?? null) ? $parameters['options'] : [];

        $html = View::make('exports.document-print', [
            'document' => $document,
            'data' => $data,
            'options' => $options,
        ])->render();

        $relative = "documents/{$tenantId}/document-{$document->id}.pdf";
        Storage::disk('public')->makeDirectory("documents/{$tenantId}");
        $absolute = Storage::disk('public')->path($relative);

        $this->renderPdf($html, $absolute, $document);

        $document->update([
            'pdf_path' => $relative,
            'generated_at' => now(),
        ]);

        return $relative;
    }

    /**
     * Drive Browsershot. Extracted so tests can override / mock when
     * Chromium isn't available in the test environment.
     */
    protected function renderPdf(string $html, string $absolutePath, ?Document $document = null): void
    {
        // Browsershot's header/footer templates are rendered by Chromium
        // independently of the page CSS. The `.pageNumber` and `.totalPages`
        // spans are placeholders that Chromium fills at print time.
        //
        // Note: Chromium applies the same header/footer template to ALL pages,
        // including cover/TOC. The header is a single thin 8pt line so the
        // visual cost there is negligible.
        $titleEsc = htmlspecialchars($document?->title ?? '', ENT_QUOTES);
        $dateEsc = htmlspecialchars($document?->document_date?->format('d/m/Y') ?? '', ENT_QUOTES);
        $header = <<<HTML
            <div style="font-size:8pt; width:100%; padding:0 14mm; color:#6b7280; -webkit-print-color-adjust:exact; display:flex; justify-content:space-between;">
                <span>{$titleEsc}</span>
                <span>{$dateEsc}</span>
            </div>
        HTML;

        $footer = <<<'HTML'
            <div style="font-size:8pt; width:100%; padding:0 14mm; text-align:right; color:#6b7280; -webkit-print-color-adjust:exact;">
                Pagina <span class="pageNumber"></span> / <span class="totalPages"></span>
            </div>
        HTML;

        Browsershot::html($html)
            ->noSandbox()
            ->emulateMedia('print')
            ->showBackground()
            ->format('A4')
            ->showBrowserHeaderAndFooter()
            ->headerHtml($header)
            ->footerHtml($footer)
            ->save($absolutePath);
    }

    /**
     * Load all the model collections the template needs based on the
     * document's parameters. Returns null entries when a section is
     * disabled, so the Blade can simply check `@if ($data['sites'])`.
     *
     * @return array<string, Collection<int, mixed>|null>
     */
    public function loadData(Document $document): array
    {
        $parameters = is_array($document->parameters) ? $document->parameters : [];
        $sections = is_array($parameters['sections'] ?? null) ? $parameters['sections'] : [];

        // The `ids` arrays are order-significant (the order chosen in the
        // editor = the print order), so we sort each collection by the
        // position of its id in the saved list rather than by name.
        $data = [
            'sites' => $this->loadSection(
                $sections['sites'] ?? null,
                fn (array $ids) => $this->orderByIdList(
                    Site::query()->whereIn('id', $ids)->get(), $ids,
                ),
            ),
            'rooms' => $this->loadSection(
                $sections['rooms'] ?? null,
                fn (array $ids) => $this->orderByIdList(
                    Room::query()
                        ->with(['site', 'racks' => fn ($q) => $q->orderBy('name')])
                        ->whereIn('id', $ids)
                        ->get(),
                    $ids,
                ),
            ),
            'racks' => $this->loadSection(
                $sections['racks'] ?? null,
                fn (array $ids) => $this->orderByIdList(
                    Rack::query()
                        ->with(['room.site', 'equipment.interfaces', 'photos'])
                        ->whereIn('id', $ids)
                        ->get(),
                    $ids,
                ),
            ),
            'equipment' => $this->loadSection(
                $sections['equipment'] ?? null,
                fn (array $ids) => $this->orderByIdList(
                    Equipment::query()
                        ->with([
                            'rack.room.site', 'room.site',
                            'interfaces.outgoingConnections.toInterface.equipment',
                            'interfaces.incomingConnections.fromInterface.equipment',
                        ])
                        ->whereIn('id', $ids)
                        ->get(),
                    $ids,
                ),
            ),
            'topologies' => $this->loadTopologies($sections['topologies'] ?? null),
            'wifi' => $this->loadSection(
                $sections['wifi'] ?? null,
                fn (array $ids) => $this->orderByIdList(
                    WifiNetwork::query()
                        ->with(['broadcasters.equipment'])
                        ->withCount('associations')
                        ->whereIn('id', $ids)
                        ->get(),
                    $ids,
                ),
            ),
            'vpn' => $this->loadVpn($sections['vpn'] ?? null),
        ];

        $data['hierarchy'] = $this->buildHierarchy($document, $data);

        return $data;
    }

    /**
     * Build the Sede → Locale → (Rack | Unracked Equipment) tree from the
     * already-loaded collections, applying STRICT inclusion: an entity is
     * rendered only if it AND all of its ancestors are in their respective
     * `ids` selection lists.
     *
     * @param  array<string, Collection<int, mixed>|null>  $data
     * @return Collection<int, object>
     */
    private function buildHierarchy(Document $document, array $data): Collection
    {
        $sites = $data['sites'] instanceof Collection ? $data['sites'] : collect();
        $rooms = $data['rooms'] instanceof Collection ? $data['rooms'] : collect();
        $racks = $data['racks'] instanceof Collection ? $data['racks'] : collect();
        $equipment = $data['equipment'] instanceof Collection ? $data['equipment'] : collect();

        $parameters = is_array($document->parameters) ? $document->parameters : [];
        $sections = is_array($parameters['sections'] ?? null) ? $parameters['sections'] : [];
        $siteDescription = (string) ($sections['sites']['description'] ?? '');
        $roomDescription = (string) ($sections['rooms']['description'] ?? '');

        // Ports are reported by default; the editor only persists the
        // exceptions. Tag each device so the template knows whether to render
        // its interface table.
        $portsExcluded = array_flip(array_map('intval', $sections['equipment']['ports_excluded'] ?? []));
        $equipment->each(function ($eq) use ($portsExcluded): void {
            $eq->report_ports = ! isset($portsExcluded[$eq->getKey()]);
        });

        // The collections arrive already ordered (loadData sorted them by the
        // saved id order); groupBy preserves that order within each group, so
        // we must NOT re-sort by name here.
        $roomsBySite = $rooms->groupBy('site_id');
        $racksByRoom = $racks->groupBy('room_id');
        $racked = $equipment->filter(fn ($eq) => $eq->rack_id !== null);
        $unracked = $equipment->filter(fn ($eq) => $eq->rack_id === null);
        $rackedByRack = $racked->groupBy('rack_id');
        $unrackedByRoom = $unracked->filter(fn ($eq) => $eq->room_id !== null)->groupBy('room_id');

        return $sites->map(function (Site $site) use (
            $roomsBySite,
            $racksByRoom,
            $rackedByRack,
            $unrackedByRoom,
            $siteDescription,
            $roomDescription,
        ) {
            $siteRooms = ($roomsBySite[$site->getKey()] ?? collect())
                ->map(function (Room $room) use ($racksByRoom, $rackedByRack, $unrackedByRoom, $roomDescription) {
                    $rackNodes = ($racksByRoom[$room->getKey()] ?? collect())
                        ->map(fn (Rack $rack) => (object) [
                            'rack' => $rack,
                            // Only the selected racked equipment, in saved order.
                            // The elevation SVG still draws the full physical rack.
                            'equipment' => ($rackedByRack[$rack->getKey()] ?? collect())->values(),
                        ])
                        ->values();

                    return (object) [
                        'room' => $room,
                        'description' => $roomDescription,
                        'racks' => $rackNodes,
                        'unracked' => ($unrackedByRoom[$room->getKey()] ?? collect())->values(),
                    ];
                })
                ->values();

            return (object) [
                'site' => $site,
                'description' => $siteDescription,
                'rooms' => $siteRooms,
            ];
        })->values();
    }

    /**
     * Reorder a collection of models to match the order of ids in $ids.
     * Ids not present in the collection are skipped; models whose id is not
     * in $ids (shouldn't happen given the whereIn) are appended at the end.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $models
     * @param  array<int, int>  $ids
     * @return Collection<int, TModel>
     */
    private function orderByIdList(Collection $models, array $ids): Collection
    {
        $position = array_flip(array_values($ids));

        return $models
            ->sortBy(fn ($m) => $position[$m->getKey()] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @param  callable(array<int, int>): Collection<int, mixed>  $loader
     */
    private function loadSection(?array $section, callable $loader): ?Collection
    {
        if ($section === null || empty($section['enabled'])) {
            return null;
        }
        $ids = array_values(array_filter(
            array_map('intval', $section['ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
        if ($ids === []) {
            return collect();
        }

        return $loader($ids);
    }

    /**
     * Load the two VPN flavours into a single payload so the template can
     * render them with a shared heading. Returns null when the section is
     * disabled (so the print template can early-out with @if).
     *
     * @return array{remote: Collection<int, VpnRemoteAccess>, site: Collection<int, VpnSiteToSite>}|null
     */
    private function loadVpn(?array $section): ?array
    {
        if ($section === null || empty($section['enabled'])) {
            return null;
        }
        $remoteIds = array_values(array_filter(
            array_map('intval', $section['remote_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
        $siteIds = array_values(array_filter(
            array_map('intval', $section['site_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));

        $remote = $remoteIds === []
            ? collect()
            : $this->orderByIdList(
                VpnRemoteAccess::query()
                    ->with(['firewallInterface.equipment', 'clients.clientInterface.equipment'])
                    ->whereIn('id', $remoteIds)
                    ->get(),
                $remoteIds,
            );

        $site = $siteIds === []
            ? collect()
            : $this->orderByIdList(
                VpnSiteToSite::query()
                    ->with(['endpointAInterface.equipment', 'endpointBInterface.equipment'])
                    ->whereIn('id', $siteIds)
                    ->get(),
                $siteIds,
            );

        return ['remote' => $remote, 'site' => $site];
    }

    /**
     * Returns an ordered collection of {snapshot, orientation, description}
     * entries; preserves the order chosen by the user.
     *
     * @return Collection<int, object{snapshot: TopologySnapshot, orientation: string}>|null
     */
    private function loadTopologies(?array $section): ?Collection
    {
        if ($section === null || empty($section['enabled'])) {
            return null;
        }
        $items = is_array($section['items'] ?? null) ? $section['items'] : [];
        if ($items === []) {
            return collect();
        }

        $ids = array_values(array_filter(
            array_map(fn ($i) => (int) ($i['id'] ?? 0), $items),
            fn (int $id) => $id > 0,
        ));
        $byId = TopologySnapshot::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($items)
            ->map(function (array $entry) use ($byId) {
                $id = (int) ($entry['id'] ?? 0);
                $snap = $byId->get($id);
                if ($snap === null) {
                    return null;
                }

                return (object) [
                    'snapshot' => $snap,
                    'orientation' => ($entry['orientation'] ?? 'portrait') === 'landscape'
                        ? 'landscape'
                        : 'portrait',
                ];
            })
            ->filter()
            ->values();
    }
}
