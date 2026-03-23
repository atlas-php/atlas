import { ref, readonly } from 'vue';

export type SessionStatus = 'idle' | 'connecting' | 'active' | 'closed';

interface SessionOptions {
    conversation_id?: number | null;
}

interface SessionPayload {
    session_id: string;
    provider: string;
    model: string;
    transport: string;
    ephemeral_token?: string;
    connection_url?: string;
    session_config?: Record<string, unknown>;
    tool_endpoint?: string;
    transcript_endpoint?: string;
}

/**
 * Composable for browser-direct voice sessions.
 *
 * OpenAI: WebRTC (RTCPeerConnection + data channel)
 * xAI: WebSocket (direct connection with ephemeral token)
 *
 * Tool calls are relayed to the server's tool endpoint.
 * Transcripts are POSTed to the server on turn boundary.
 */
export function useVoice() {
    const sessionStatus = ref<SessionStatus>('idle');
    const isListening = ref(false);
    const isSpeaking = ref(false);
    const audioLevel = ref(0);
    const error = ref<string | null>(null);

    // Connection state
    let peerConnection: RTCPeerConnection | null = null;
    let dataChannel: RTCDataChannel | null = null;
    let providerSocket: WebSocket | null = null;
    let localStream: MediaStream | null = null;
    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let levelAnimFrame: number | null = null;

    // Session context
    let currentSession: SessionPayload | null = null;
    let currentConversationId: number | null = null;

    // Transcript accumulation
    let pendingUserTranscript = '';
    let pendingAssistantTranscript = '';

    // Audio playback
    let playbackContext: AudioContext | null = null;
    let nextPlayTime = 0;
    const SAMPLE_RATE = 24000;
    let audioQueue: string[] = [];
    let processingAudio = false;

    async function startSession(options: SessionOptions = {}) {
        if (sessionStatus.value !== 'idle' && sessionStatus.value !== 'closed') return;

        try {
            const perm = await navigator.permissions.query({ name: 'microphone' as PermissionName });
            if (perm.state === 'denied') {
                error.value = 'Microphone access blocked. Enable it in browser settings.';
                return;
            }
        } catch { /* permissions API may not be available */ }

        sessionStatus.value = 'connecting';
        error.value = null;
        currentConversationId = options.conversation_id ?? null;
        pendingUserTranscript = '';
        pendingAssistantTranscript = '';

        try {
            const res = await fetch('/api/voice/session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(options),
            });

            if (!res.ok) throw new Error(`Session creation failed: ${res.status}`);

            currentSession = await res.json();

            if (currentSession!.transport === 'webrtc' && currentSession!.ephemeral_token) {
                await setupWebRtc(currentSession!);
            } else if (currentSession!.transport === 'websocket' && currentSession!.ephemeral_token) {
                await setupWebSocket(currentSession!);
            } else {
                throw new Error(`Unsupported transport: ${currentSession!.transport}`);
            }
        } catch (e) {
            if (e instanceof DOMException && e.name === 'NotAllowedError') {
                error.value = 'Microphone permission denied.';
            } else {
                error.value = e instanceof Error ? e.message : 'Failed to start session';
            }
            sessionStatus.value = 'closed';
        }
    }

    // ─── xAI WebSocket Mode ────────────────────────────

    async function setupWebSocket(session: SessionPayload) {
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        setupAudioMonitoring(localStream);

        // Connect directly to xAI with ephemeral token
        providerSocket = new WebSocket(session.connection_url!, [
            'realtime',
            `xai-client-secret.${session.ephemeral_token}`,
        ]);

        providerSocket.onopen = () => {
            // Send session config
            if (session.session_config) {
                providerSocket!.send(JSON.stringify({
                    type: 'session.update',
                    session: session.session_config,
                }));
            }

            // Start sending mic audio
            startWebSocketAudioCapture();

            sessionStatus.value = 'active';
            isListening.value = true;
        };

        providerSocket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                handleProviderEvent(data);
            } catch { /* ignore non-JSON */ }
        };

        providerSocket.onclose = () => {
            if (sessionStatus.value === 'active') {
                stopSession();
            }
        };

        providerSocket.onerror = () => {
            error.value = 'WebSocket connection error';
            stopSession();
        };
    }

    function handleProviderEvent(data: Record<string, unknown>) {
        const type = data.type as string;

        switch (type) {
            // Audio output — mute mic to prevent echo, then queue playback
            case 'response.output_audio.delta':
            case 'response.audio.delta':
                if (!isSpeaking.value) {
                    // Mute mic on first audio chunk to prevent echo feedback loop
                    localStream?.getAudioTracks().forEach(t => { t.enabled = false; });
                }
                queueAudioChunk((data.delta as string) ?? '');
                isSpeaking.value = true;
                break;

            // User transcript
            case 'conversation.item.input_audio_transcription.completed':
                pendingUserTranscript = (data.transcript as string) ?? '';
                break;

            // Assistant transcript
            case 'response.output_audio_transcript.delta':
            case 'response.audio_transcript.delta':
                pendingAssistantTranscript += (data.delta as string) ?? '';
                break;

            // Tool call
            case 'response.function_call_arguments.done':
                handleToolCall(data);
                break;

            // Turn complete — unmute mic for next turn
            case 'response.done':
                isSpeaking.value = false;
                nextPlayTime = 0;
                audioQueue = [];
                // Re-enable mic after AI finishes (small delay to avoid capturing tail audio)
                setTimeout(() => {
                    localStream?.getAudioTracks().forEach(t => { t.enabled = true; });
                }, 500);
                setTimeout(() => flushTranscripts(), 300);
                break;

            // Speech detection
            case 'input_audio_buffer.speech_started':
                isListening.value = true;
                break;
            case 'input_audio_buffer.speech_stopped':
                isListening.value = false;
                break;
        }
    }

    /**
     * Capture PCM16 audio from mic and send over WebSocket.
     */
    function startWebSocketAudioCapture() {
        if (!localStream || !audioContext || !providerSocket) return;

        const targetRate = SAMPLE_RATE;
        const nativeRate = audioContext.sampleRate;
        const ratio = nativeRate / targetRate;

        const source = audioContext.createMediaStreamSource(localStream);
        const processor = audioContext.createScriptProcessor(4096, 1, 1);

        processor.onaudioprocess = (e) => {
            if (isSpeaking.value || !providerSocket || providerSocket.readyState !== WebSocket.OPEN) return;

            const float32 = e.inputBuffer.getChannelData(0);
            const outLen = Math.floor(float32.length / ratio);
            const int16 = new Int16Array(outLen);

            for (let i = 0; i < outLen; i++) {
                const s = Math.max(-1, Math.min(1, float32[Math.floor(i * ratio)]));
                int16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
            }

            // Base64 encode
            const bytes = new Uint8Array(int16.buffer);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
            }

            providerSocket.send(JSON.stringify({
                type: 'input_audio_buffer.append',
                audio: btoa(binary),
            }));
        };

        source.connect(processor);
        // Connect to a silent gain node (ScriptProcessor needs a destination to fire, but we don't want mic playback)
        const silentGain = audioContext.createGain();
        silentGain.gain.value = 0;
        processor.connect(silentGain);
        silentGain.connect(audioContext.destination);
    }

    // ─── Tool Execution ────────────────────────────────

    async function handleToolCall(data: Record<string, unknown>) {
        if (!currentSession?.tool_endpoint) return;

        const callId = data.call_id as string;
        const name = data.name as string;
        const args = data.arguments as string;

        try {
            const res = await fetch(currentSession.tool_endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ name, arguments: args }),
            });

            const result = await res.json();
            const output = result.output ?? JSON.stringify({ error: 'Tool failed' });

            // Send result back to provider
            const socket = providerSocket ?? null;
            const dc = dataChannel ?? null;
            const channel = socket || dc;

            if (channel) {
                const send = (msg: string) => {
                    if (socket && socket.readyState === WebSocket.OPEN) socket.send(msg);
                    else if (dc && dc.readyState === 'open') dc.send(msg);
                };

                send(JSON.stringify({
                    type: 'conversation.item.create',
                    item: { type: 'function_call_output', call_id: callId, output },
                }));
                send(JSON.stringify({ type: 'response.create' }));
            }
        } catch (e) {
            console.error('[Voice] Tool call failed:', e);
        }
    }

    // ─── Transcript Persistence ────────────────────────

    async function flushTranscripts() {
        if (!currentSession?.transcript_endpoint || !currentConversationId) return;

        const turns: Array<{ role: string; transcript: string }> = [];
        if (pendingUserTranscript) turns.push({ role: 'user', transcript: pendingUserTranscript });
        if (pendingAssistantTranscript) turns.push({ role: 'assistant', transcript: pendingAssistantTranscript });

        pendingUserTranscript = '';
        pendingAssistantTranscript = '';

        if (turns.length === 0) return;

        try {
            await fetch(currentSession.transcript_endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ conversation_id: currentConversationId, turns }),
            });
        } catch {
            console.error('[Voice] Transcript save failed');
        }
    }

    // ─── Audio Playback ────────────────────────────────

    function queueAudioChunk(base64: string) {
        if (!base64) return;
        audioQueue.push(base64);
        if (!processingAudio) processAudioQueue();
    }

    function processAudioQueue() {
        processingAudio = true;
        while (audioQueue.length > 0) {
            const chunk = audioQueue.shift()!;
            playAudioChunk(chunk);
        }
        processingAudio = false;
    }

    function playAudioChunk(base64Audio: string) {
        if (!base64Audio) return;

        try {
            if (!playbackContext) {
                playbackContext = new AudioContext({ sampleRate: SAMPLE_RATE });
                nextPlayTime = 0;
            }

            const binary = atob(base64Audio);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            // PCM16 little-endian → float32
            const int16 = new Int16Array(bytes.buffer);
            const float32 = new Float32Array(int16.length);
            for (let i = 0; i < int16.length; i++) {
                float32[i] = int16[i] / 32768;
            }

            const audioBuffer = playbackContext.createBuffer(1, float32.length, SAMPLE_RATE);
            audioBuffer.getChannelData(0).set(float32);

            const source = playbackContext.createBufferSource();
            source.buffer = audioBuffer;
            source.connect(playbackContext.destination);

            const now = playbackContext.currentTime;
            const startTime = Math.max(now, nextPlayTime);
            source.start(startTime);
            nextPlayTime = startTime + audioBuffer.duration;
        } catch (e) {
            console.error('[Voice] Audio playback error:', e);
        }
    }

    // ─── OpenAI WebRTC Mode ────────────────────────────

    async function setupWebRtc(session: SessionPayload) {
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        setupAudioMonitoring(localStream);

        peerConnection = new RTCPeerConnection();
        localStream.getTracks().forEach((track) => {
            peerConnection!.addTrack(track, localStream!);
        });

        const audioEl = document.createElement('audio');
        audioEl.autoplay = true;
        peerConnection.ontrack = (event) => {
            audioEl.srcObject = event.streams[0];
        };

        dataChannel = peerConnection.createDataChannel('oai-events');
        dataChannel.onmessage = (event) => {
            try {
                handleProviderEvent(JSON.parse(event.data));
            } catch { /* ignore */ }
        };

        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        const sdpRes = await fetch(
            `https://api.openai.com/v1/realtime?model=${session.model}`,
            {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${session.ephemeral_token}`,
                    'Content-Type': 'application/sdp',
                },
                body: offer.sdp,
            },
        );

        if (!sdpRes.ok) throw new Error(`SDP exchange failed: ${sdpRes.status}`);

        await peerConnection.setRemoteDescription({
            type: 'answer',
            sdp: await sdpRes.text(),
        });

        sessionStatus.value = 'active';
        isListening.value = true;
    }

    // ─── Shared ────────────────────────────────────────

    function setupAudioMonitoring(stream: MediaStream) {
        audioContext = new AudioContext();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;

        const source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);

        const dataArray = new Uint8Array(analyser.frequencyBinCount);

        function updateLevel() {
            if (!analyser) return;
            analyser.getByteFrequencyData(dataArray);
            const avg = dataArray.reduce((sum, v) => sum + v, 0) / dataArray.length;
            audioLevel.value = Math.min(avg / 128, 1);
            levelAnimFrame = requestAnimationFrame(updateLevel);
        }

        updateLevel();
    }

    async function stopSession() {
        // Flush any remaining transcripts
        await flushTranscripts();

        if (levelAnimFrame !== null) {
            cancelAnimationFrame(levelAnimFrame);
            levelAnimFrame = null;
        }

        audioContext?.close();
        audioContext = null;
        analyser = null;

        playbackContext?.close();
        playbackContext = null;
        nextPlayTime = 0;

        providerSocket?.close();
        providerSocket = null;

        dataChannel?.close();
        dataChannel = null;

        peerConnection?.close();
        peerConnection = null;

        localStream?.getTracks().forEach((track) => track.stop());
        localStream = null;

        currentSession = null;
        currentConversationId = null;

        sessionStatus.value = 'closed';
        isListening.value = false;
        isSpeaking.value = false;
        audioLevel.value = 0;
    }

    function mute() {
        localStream?.getAudioTracks().forEach((t) => { t.enabled = false; });
        isListening.value = false;
    }

    function unmute() {
        localStream?.getAudioTracks().forEach((t) => { t.enabled = true; });
        isListening.value = true;
    }

    return {
        sessionStatus: readonly(sessionStatus),
        isListening: readonly(isListening),
        isSpeaking: readonly(isSpeaking),
        audioLevel: readonly(audioLevel),
        error: readonly(error),
        startSession,
        stopSession,
        mute,
        unmute,
    };
}
