const REPOSITION_INTERVAL_MS = 10000;
const MIN_PERCENT = 10;
const MAX_PERCENT = 80;

function randomPercent() {
    return MIN_PERCENT + Math.random() * (MAX_PERCENT - MIN_PERCENT);
}

export default function lessonWatermark() {
    return {
        top: randomPercent(),
        left: randomPercent(),

        init() {
            setInterval(() => {
                this.top = randomPercent();
                this.left = randomPercent();
            }, REPOSITION_INTERVAL_MS);
        },
    };
}
