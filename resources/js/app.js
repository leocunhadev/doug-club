import vimeoProgress from './vimeo-progress';
import lessonWatermark from './lesson-watermark';

document.addEventListener('alpine:init', () => {
    Alpine.data('vimeoProgress', vimeoProgress);
    Alpine.data('lessonWatermark', lessonWatermark);
});
