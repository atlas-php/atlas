import { ref, readonly } from 'vue';

export type SessionStatus = 'idle' | 'connecting' | 'active' | 'closing' | 'closed';

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
    close_endpoint?: string;
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

    // Live transcript text — reactive, updated as provider sends deltas.
    // The chat UI can display these as optimistic bubbles during the voice session.
    const liveUserTranscript = ref('');
    const liveAssistantTranscript = ref('');

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
    let unloadHandler: (() => void) | null = null;

    // Transcript accumulation — stored on session end only.
    // Within a single "exchange" (user speaks → AI responds), the provider may
    // send multiple response cycles if VAD splits long speech into segments.
    // We keep only the latest user transcript (it's progressive — each one
    // supersedes the previous) and concatenate assistant responses.
    // A new exchange starts when speech_started fires after a response.done.
    let currentUserTranscript = '';
    let currentAssistantTranscript = '';
    let completedTurns: Array<{ role: string; transcript: string }> = [];
    let lastResponseDone = false; // tracks whether we're between response.done and next speech_started
    let onTranscriptFlushedCallback: (() => void) | null = null;

    // Audio playback
    let playbackContext: AudioContext | null = null;
    let nextPlayTime = 0;
    const SAMPLE_RATE = 24000;
    let audioQueue: string[] = [];
    let processingAudio = false;
    let playbackCompleteTimer: ReturnType<typeof setTimeout> | null = null;

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
        currentUserTranscript = '';
        currentAssistantTranscript = '';
        completedTurns = [];
        lastResponseDone = false;
        liveUserTranscript.value = '';
        liveAssistantTranscript.value = '';

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
            } else if (currentSession!.transport === 'websocket' && currentSession!.connection_url) {
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
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        });
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

            // Register beforeunload to checkpoint transcript on page close/refresh
            registerUnloadHandler();
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
            // Audio output — queue for playback
            case 'response.output_audio.delta':
            case 'response.audio.delta':
                queueAudioChunk((data.delta as string) ?? '');
                isSpeaking.value = true;
                // Cancel any pending playback-complete timer since we got more audio
                if (playbackCompleteTimer !== null) {
                    clearTimeout(playbackCompleteTimer);
                    playbackCompleteTimer = null;
                }
                break;

            // User transcript — progressive: each completed event supersedes the previous
            case 'conversation.item.input_audio_transcription.completed':
                currentUserTranscript = (data.transcript as string) ?? '';
                liveUserTranscript.value = currentUserTranscript;
                break;

            // Assistant transcript — accumulate deltas across the full exchange
            case 'response.output_audio_transcript.delta':
            case 'response.audio_transcript.delta':
                currentAssistantTranscript += (data.delta as string) ?? '';
                liveAssistantTranscript.value = currentAssistantTranscript;
                break;

            // Tool call
            case 'response.function_call_arguments.done':
                handleToolCall(data);
                break;

            // Response complete — mark that the AI finished this cycle.
            // Don't seal yet — more VAD segments may follow for the same exchange.
            case 'response.done':
                schedulePlaybackComplete();
                lastResponseDone = true;
                // Checkpoint: seal and save whatever we have so far
                sealCurrentTurn();
                checkpointTranscript();
                break;

            // Speech detection — handle interruption
            case 'input_audio_buffer.speech_started':
                isListening.value = true;
                if (isSpeaking.value) {
                    cancelCurrentResponse();
                }
                lastResponseDone = false;
                break;
            case 'input_audio_buffer.speech_stopped':
                isListening.value = false;
                break;
        }
    }

    /**
     * Cancel the current AI response — user is interrupting.
     * Stops audio playback and tells the provider to stop generating.
     */
    function cancelCurrentResponse() {
        // Kill all in-flight audio by closing the playback context.
        // Clearing the queue alone doesn't stop already-scheduled AudioBufferSourceNodes.
        if (playbackContext) {
            playbackContext.close().catch(() => {});
            playbackContext = null;
        }

        audioQueue = [];
        nextPlayTime = 0;
        isSpeaking.value = false;

        if (playbackCompleteTimer !== null) {
            clearTimeout(playbackCompleteTimer);
            playbackCompleteTimer = null;
        }

        // Tell provider to stop generating
        sendToProvider(JSON.stringify({ type: 'response.cancel' }));
    }

    /**
     * Schedule isSpeaking = false after queued audio finishes playing.
     * The response.done event means the provider is done GENERATING,
     * but there may still be audio buffers queued for playback.
     */
    function schedulePlaybackComplete() {
        if (!playbackContext || nextPlayTime <= playbackContext.currentTime) {
            finishSpeaking();
            return;
        }

        const remainingMs = (nextPlayTime - playbackContext.currentTime) * 1000;

        if (playbackCompleteTimer !== null) {
            clearTimeout(playbackCompleteTimer);
        }

        playbackCompleteTimer = setTimeout(() => {
            playbackCompleteTimer = null;
            finishSpeaking();
        }, remainingMs + 100); // Small buffer to avoid cutting off tail
    }

    function finishSpeaking() {
        isSpeaking.value = false;
        nextPlayTime = 0;
        audioQueue = [];
    }

    /**
     * Checkpoint the current completedTurns to the server.
     * Replaces the VoiceCall transcript atomically — no duplicates.
     */
    function checkpointTranscript() {
        if (!currentSession?.transcript_endpoint || completedTurns.length === 0) return;

        // Fire and forget — don't block the voice session
        const turns = completedTurns.map(t => ({ role: t.role, content: t.transcript }));

        fetch(currentSession.transcript_endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ turns }),
        }).catch(() => {
            console.error('[Voice] Transcript checkpoint failed');
        });
    }

    /**
     * Seal the current exchange into completedTurns.
     * Called when a new exchange starts (speech_started after response.done)
     * or on session end. The user transcript is the final progressive version;
     * the assistant transcript is the full concatenation of all deltas.
     */
    function sealCurrentTurn() {
        if (currentUserTranscript) {
            completedTurns.push({ role: 'user', transcript: currentUserTranscript });
            currentUserTranscript = '';
        }
        if (currentAssistantTranscript) {
            completedTurns.push({ role: 'assistant', transcript: currentAssistantTranscript });
            currentAssistantTranscript = '';
        }
        // Reset live refs for the new exchange
        liveUserTranscript.value = '';
        liveAssistantTranscript.value = '';
    }

    function sendToProvider(msg: string) {
        if (providerSocket && providerSocket.readyState === WebSocket.OPEN) {
            providerSocket.send(msg);
        } else if (dataChannel && dataChannel.readyState === 'open') {
            dataChannel.send(msg);
        }
    }

    /**
     * Capture PCM16 audio from mic and send over WebSocket.
     * Audio flows at all times — server VAD handles interruption detection.
     * Browser echo cancellation prevents feedback loops.
     */
    function startWebSocketAudioCapture() {
        if (!localStream || !audioContext || !providerSocket) return;

        const targetRate = SAMPLE_RATE;
        const nativeRate = audioContext.sampleRate;
        const ratio = nativeRate / targetRate;

        const source = audioContext.createMediaStreamSource(localStream);
        const processor = audioContext.createScriptProcessor(4096, 1, 1);

        processor.onaudioprocess = (e) => {
            if (!providerSocket || providerSocket.readyState !== WebSocket.OPEN) return;

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
                body: JSON.stringify({ name, arguments: args, call_id: callId }),
            });

            const result = await res.json();
            const output = result.output ?? JSON.stringify({ error: 'Tool failed' });

            sendToProvider(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: callId, output },
            }));
            sendToProvider(JSON.stringify({ type: 'response.create' }));
        } catch (e) {
            console.error('[Voice] Tool call failed:', e);
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
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
        });
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
        if (sessionStatus.value === 'closing' || sessionStatus.value === 'closed') return;

        sessionStatus.value = 'closing';
        removeUnloadHandler();

        // Seal any in-progress turn
        sealCurrentTurn();

        // Close the session on the server with the final transcript
        if (currentSession?.close_endpoint) {
            try {
                const turns = completedTurns.map(t => ({ role: t.role, content: t.transcript }));
                await fetch(currentSession.close_endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ turns }),
                });
                onTranscriptFlushedCallback?.();
            } catch { /* best effort */ }
        }

        if (playbackCompleteTimer !== null) {
            clearTimeout(playbackCompleteTimer);
            playbackCompleteTimer = null;
        }

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

    function registerUnloadHandler() {
        unloadHandler = () => {
            if (!currentSession?.close_endpoint) return;

            // Seal any in-progress turn
            sealCurrentTurn();

            const turns = completedTurns.map(t => ({ role: t.role, content: t.transcript }));
            const body = JSON.stringify({ turns });

            // sendBeacon is the only reliable way to send data on page unload
            navigator.sendBeacon(currentSession.close_endpoint, new Blob([body], { type: 'application/json' }));
        };

        window.addEventListener('beforeunload', unloadHandler);
    }

    function removeUnloadHandler() {
        if (unloadHandler) {
            window.removeEventListener('beforeunload', unloadHandler);
            unloadHandler = null;
        }
    }

    function onTranscriptFlushed(cb: () => void) {
        onTranscriptFlushedCallback = cb;
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
        liveUserTranscript: readonly(liveUserTranscript),
        liveAssistantTranscript: readonly(liveAssistantTranscript),
        startSession,
        stopSession,
        mute,
        unmute,
        onTranscriptFlushed,
    };
}
