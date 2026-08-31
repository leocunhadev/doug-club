import vimeoProgress from './vimeo-progress';
import lessonWatermark from './lesson-watermark';

document.addEventListener('alpine:init', () => {
    Alpine.data('vimeoProgress', vimeoProgress);
    Alpine.data('lessonWatermark', lessonWatermark);
});

document.addEventListener('livewire:navigated', () => {
    document.body.classList.remove('page-enter');
    void document.body.offsetWidth;
    document.body.classList.add('page-enter');
});
