import './bootstrap';

import rackDnD from './alpine/rack-dnd';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('rackDnD', rackDnD);
});
