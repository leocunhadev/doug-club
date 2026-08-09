import vimeoProgress from './vimeo-progress';

document.addEventListener('alpine:init', () => {
    Alpine.data('vimeoProgress', vimeoProgress);
});
