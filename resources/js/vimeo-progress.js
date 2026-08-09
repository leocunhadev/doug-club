import Player from '@vimeo/player';

const COMPLETED_THRESHOLD = 0.9;
const RESUME_SAFETY_MARGIN_SECONDS = 5;

export default function vimeoProgress({ lessonId, provider, initialSeconds }) {
    return {
        player: null,
        completedSent: false,

        init() {
            if (provider !== 'vimeo') {
                return;
            }

            this.player = new Player(this.$refs.iframe);

            this.player.on('loaded', () => this.resumeIfNeeded());
            this.player.on('timeupdate', (data) => this.checkCompleted(data));
            this.player.on('pause', () => this.saveProgress());
            this.player.on('ended', () => this.saveProgress());
        },

        async resumeIfNeeded() {
            if (initialSeconds <= 0) {
                return;
            }

            const duration = await this.player.getDuration();

            if (initialSeconds < duration - RESUME_SAFETY_MARGIN_SECONDS) {
                this.player.setCurrentTime(initialSeconds);
            }
        },

        checkCompleted({ percent }) {
            if (this.completedSent || percent < COMPLETED_THRESHOLD) {
                return;
            }

            this.completedSent = true;
            this.$wire.markCompleted(lessonId);
        },

        async saveProgress() {
            const seconds = await this.player.getCurrentTime();
            this.$wire.updateProgress(lessonId, Math.floor(seconds));
        },
    };
}
