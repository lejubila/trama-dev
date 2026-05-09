import './bootstrap';

import rackDnD from './alpine/rack-dnd';
import topologyGraph from './alpine/topology-graph';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('rackDnD', rackDnD);
    window.Alpine.data('topologyGraph', topologyGraph);
});
