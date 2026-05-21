import './bootstrap';

import rackDnD from './alpine/rack-dnd';
import rackPhotoZoom from './alpine/rack-photo-zoom';
import roomMapDnD from './alpine/room-map-dnd';
import topologyGraph from './alpine/topology-graph';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('rackDnD', rackDnD);
    window.Alpine.data('rackPhotoZoom', rackPhotoZoom);
    window.Alpine.data('roomMapDnD', roomMapDnD);
    window.Alpine.data('topologyGraph', topologyGraph);
});
