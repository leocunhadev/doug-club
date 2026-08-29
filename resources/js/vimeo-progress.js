import Player from '@vimeo/player';

const COMPLETED_THRESHOLD = 0.9;
const RESUME_SAFETY_MARGIN_SECONDS = 5;

export default function vimeoProgress({ lessonId, provider, initialSeconds, hasFeedback }) {
    return {
        player: null,
        completedSent: false,

        init() {
            if (provider !== 'vimeo') {
                return;
            }

            this.player = new Player(this.$refs.iframe);

            this.player.on('loaded', () => this.startPlayback());
            this.player.on('timeupdate', (data) => this.checkCompleted(data));
            this.player.on('pause', () => this.saveProgress());
            this.player.on('ended', () => this.saveProgress());
        },

        async startPlayback() {
            await this.resumeIfNeeded();

            // Browsers block unmuted autoplay without a user gesture; muting first
            // is the Vimeo SDK's own documented way to make play() succeed reliably.
            await this.player.setMuted(true).catch(() => {});
            this.player.play().catch(() => {});
        },

        async resumeIfNeeded() {
            if (initialSeconds <= 0) {
                return;
            }

            const duration = await this.player.getDuration();

            if (initialSeconds < duration - RESUME_SAFETY_MARGIN_SECONDS) {
                await this.player.setCurrentTime(initialSeconds);
            }
        },

        checkCompleted({ percent }) {
            if (this.completedSent || percent < COMPLETED_THRESHOLD) {
                return;
            }

            this.completedSent = true;
            this.$wire.markCompleted(lessonId);

            if (!hasFeedback) {
                window.dispatchEvent(new CustomEvent('open-nps-modal', {
                    detail: {
                        action: 'submitNpsScore',
                        subjectId: lessonId,
                        subtitle: 'De 0 a 10, o quanto essa aula te ajudou a decidir melhor?',
                    },
                }));
            }
        },

        async saveProgress() {
            const seconds = await this.player.getCurrentTime();
            this.$wire.updateProgress(lessonId, Math.floor(seconds));
        },
    };
}
