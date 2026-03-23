import { ref, onUnmounted, readonly } from 'vue';

/**
 * Composable for monitoring audio level from a MediaStream.
 *
 * Returns a reactive `level` value (0-1) driven by AnalyserNode,
 * suitable for driving mic button glow intensity.
 */
export function useAudioVisualizer() {
    const level = ref(0);
    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let animFrame: number | null = null;

    function start(stream: MediaStream) {
        stop();

        audioContext = new AudioContext();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;

        const source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);

        const dataArray = new Uint8Array(analyser.frequencyBinCount);

        function update() {
            if (!analyser) return;
            analyser.getByteFrequencyData(dataArray);
            const avg = dataArray.reduce((sum, v) => sum + v, 0) / dataArray.length;
            level.value = Math.min(avg / 128, 1);
            animFrame = requestAnimationFrame(update);
        }

        update();
    }

    function stop() {
        if (animFrame !== null) {
            cancelAnimationFrame(animFrame);
            animFrame = null;
        }

        audioContext?.close();
        audioContext = null;
        analyser = null;
        level.value = 0;
    }

    onUnmounted(stop);

    return {
        level: readonly(level),
        start,
        stop,
    };
}
