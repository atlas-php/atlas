import { ref, readonly } from 'vue';

export type SessionStatus = 'idle' | 'connecting' | 'active' | 'closed';

export interface TranscriptTurn {
    role: 'user' | 'assistant';
    text: string;
}

interface SessionOptions {
    provider?: string;
    model?: string;
    voice?: string;
    transport?: 'webrtc' | 'websocket';
    turnDetection?: 'server_vad' | 'manual';
    instructions?: string;
    conversation_id?: number | null;
}

interface SessionPayload {
    session_id: string;
    provider: string;
    model: string;
    transport: string;
    ephemeral_token?: string;
    connection_url?: string;
    expires_at?: string;
    transcript_endpoint?: string;
    author_type?: string;
    author_id?: number;
    agent?: string;
}

/**
 * Composable managing the entire realtime voice session lifecycle.
 *
 * Handles session creation, WebRTC peer connection setup,
 * audio capture, transcript streaming, turn persistence, and cleanup.
 */
export function useRealtime() {
    const sessionStatus = ref<SessionStatus>('idle');
    const isListening = ref(false);
    const isSpeaking = ref(false);
    const userTranscript = ref('');
    const assistantTranscript = ref('');
    const audioLevel = ref(0);
    const error = ref<string | null>(null);
    const transcriptHistory = ref<TranscriptTurn[]>([]);

    let peerConnection: RTCPeerConnection | null = null;
    let dataChannel: RTCDataChannel | null = null;
    let localStream: MediaStream | null = null;
    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let levelAnimFrame: number | null = null;

    // Turn tracking for transcript persistence
    let pendingUserTranscript = '';
    let pendingAssistantTranscript = '';
    let currentSessionId: string | null = null;
    let currentConversationId: number | null = null;
    let transcriptEndpoint: string | null = null;
    let authorType: string | null = null;
    let authorId: number | null = null;
    let agentKey: string | null = null;

    async function startSession(options: SessionOptions = {}) {
        if (sessionStatus.value !== 'idle' && sessionStatus.value !== 'closed') return;

        // Check microphone permission first
        try {
            const permissionStatus = await navigator.permissions.query({ name: 'microphone' as PermissionName });
            if (permissionStatus.state === 'denied') {
                error.value = 'Microphone access is blocked. Please enable it in your browser settings and try again.';
                return;
            }
        } catch {
            // permissions API may not be available — proceed and let getUserMedia prompt
        }

        sessionStatus.value = 'connecting';
        error.value = null;
        userTranscript.value = '';
        assistantTranscript.value = '';
        transcriptHistory.value = [];
        pendingUserTranscript = '';
        pendingAssistantTranscript = '';
        currentConversationId = options.conversation_id ?? null;

        try {
            // 1. Create session via backend
            const res = await fetch('/api/realtime/session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(options),
            });

            if (!res.ok) throw new Error(`Session creation failed: ${res.status}`);

            const session: SessionPayload = await res.json();
            currentSessionId = session.session_id;
            transcriptEndpoint = session.transcript_endpoint ?? null;
            authorType = session.author_type ?? null;
            authorId = session.author_id ?? null;
            agentKey = session.agent ?? null;

            if (session.transport === 'webrtc' && session.ephemeral_token) {
                await setupWebRtc(session);
            } else {
                // WebSocket mode — connection URL available for server relay
                sessionStatus.value = 'active';
                isListening.value = true;
            }

            // Register beforeunload to save partial turns on tab close
            window.addEventListener('beforeunload', onBeforeUnload);
        } catch (e) {
            if (e instanceof DOMException && e.name === 'NotAllowedError') {
                error.value = 'Microphone permission denied. Please allow microphone access to use voice chat.';
            } else if (e instanceof DOMException && e.name === 'NotFoundError') {
                error.value = 'No microphone found. Please connect a microphone and try again.';
            } else {
                error.value = e instanceof Error ? e.message : 'Failed to start session';
            }
            window.removeEventListener('beforeunload', onBeforeUnload);
            sessionStatus.value = 'closed';
        }
    }

    async function setupWebRtc(session: SessionPayload) {
        // Get user microphone
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true });

        // Setup audio level monitoring
        setupAudioMonitoring(localStream);

        // Create peer connection
        peerConnection = new RTCPeerConnection();

        // Add mic track
        localStream.getTracks().forEach((track) => {
            peerConnection!.addTrack(track, localStream!);
        });

        // Handle remote audio
        const audioEl = document.createElement('audio');
        audioEl.autoplay = true;
        peerConnection.ontrack = (event) => {
            audioEl.srcObject = event.streams[0];
        };

        // Data channel for events (transcripts, tool calls)
        dataChannel = peerConnection.createDataChannel('oai-events');
        dataChannel.onmessage = handleDataChannelMessage;

        // Create offer
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        // Send SDP to OpenAI's ephemeral endpoint
        const baseUrl = 'https://api.openai.com/v1/realtime';
        const model = session.model;
        const sdpResponse = await fetch(`${baseUrl}?model=${model}`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${session.ephemeral_token}`,
                'Content-Type': 'application/sdp',
            },
            body: offer.sdp,
        });

        if (!sdpResponse.ok) throw new Error(`SDP exchange failed: ${sdpResponse.status}`);

        const answerSdp = await sdpResponse.text();
        await peerConnection.setRemoteDescription({ type: 'answer', sdp: answerSdp });

        sessionStatus.value = 'active';
        isListening.value = true;
    }

    function handleDataChannelMessage(event: MessageEvent) {
        try {
            const data = JSON.parse(event.data);

            switch (data.type) {
                case 'conversation.item.input_audio_transcription.completed':
                    pendingUserTranscript = data.transcript ?? '';
                    userTranscript.value = pendingUserTranscript;
                    break;
                case 'response.audio_transcript.delta':
                    pendingAssistantTranscript += data.delta ?? '';
                    assistantTranscript.value = pendingAssistantTranscript;
                    isSpeaking.value = true;
                    break;
                case 'response.audio_transcript.done':
                    isSpeaking.value = false;
                    break;
                case 'response.done':
                    isSpeaking.value = false;
                    // Turn boundary — delay flush slightly to allow the async
                    // input_audio_transcription.completed event to arrive first
                    setTimeout(() => flushTurns(), 300);
                    break;
            }
        } catch {
            // Ignore non-JSON messages
        }
    }

    /**
     * Save the current pending turn pair to the server and add to history.
     */
    async function flushTurns() {
        const turns: Array<{ role: 'user' | 'assistant'; transcript: string }> = [];

        if (pendingUserTranscript) {
            turns.push({ role: 'user', transcript: pendingUserTranscript });
            transcriptHistory.value.push({ role: 'user', text: pendingUserTranscript });
        }
        if (pendingAssistantTranscript) {
            turns.push({ role: 'assistant', transcript: pendingAssistantTranscript });
            transcriptHistory.value.push({ role: 'assistant', text: pendingAssistantTranscript });
        }

        // Reset accumulators for the next turn
        pendingUserTranscript = '';
        pendingAssistantTranscript = '';
        userTranscript.value = '';
        assistantTranscript.value = '';

        if (turns.length === 0 || !currentSessionId || !currentConversationId || !transcriptEndpoint) return;

        try {
            await fetch(transcriptEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    conversation_id: currentConversationId,
                    turns,
                    agent: agentKey,
                    author_type: authorType,
                    author_id: authorId,
                }),
            });
        } catch (e) {
            console.error('Failed to save transcript turn:', e);
        }
    }

    /**
     * Emergency save on tab close using sendBeacon (fire-and-forget).
     */
    function onBeforeUnload() {
        if (!pendingUserTranscript && !pendingAssistantTranscript) return;
        if (!currentSessionId || !currentConversationId || !transcriptEndpoint) return;

        const turns: Array<{ role: 'user' | 'assistant'; transcript: string }> = [];
        if (pendingUserTranscript) turns.push({ role: 'user', transcript: pendingUserTranscript });
        if (pendingAssistantTranscript) turns.push({ role: 'assistant', transcript: pendingAssistantTranscript });

        navigator.sendBeacon(
            transcriptEndpoint,
            new Blob(
                [JSON.stringify({
                    conversation_id: currentConversationId,
                    turns,
                    agent: agentKey,
                    author_type: authorType,
                    author_id: authorId,
                })],
                { type: 'application/json' },
            ),
        );
    }

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
        // Flush any remaining transcript before closing
        await flushTurns();

        window.removeEventListener('beforeunload', onBeforeUnload);

        if (levelAnimFrame !== null) {
            cancelAnimationFrame(levelAnimFrame);
            levelAnimFrame = null;
        }

        audioContext?.close();
        audioContext = null;
        analyser = null;

        dataChannel?.close();
        dataChannel = null;

        peerConnection?.close();
        peerConnection = null;

        localStream?.getTracks().forEach((track) => track.stop());
        localStream = null;

        sessionStatus.value = 'closed';
        isListening.value = false;
        isSpeaking.value = false;
        audioLevel.value = 0;
        currentSessionId = null;
        currentConversationId = null;
        transcriptEndpoint = null;
        authorType = null;
        authorId = null;
        agentKey = null;
    }

    function mute() {
        localStream?.getAudioTracks().forEach((track) => {
            track.enabled = false;
        });
        isListening.value = false;
    }

    function unmute() {
        localStream?.getAudioTracks().forEach((track) => {
            track.enabled = true;
        });
        isListening.value = true;
    }

    return {
        sessionStatus: readonly(sessionStatus),
        isListening: readonly(isListening),
        isSpeaking: readonly(isSpeaking),
        userTranscript: readonly(userTranscript),
        assistantTranscript: readonly(assistantTranscript),
        audioLevel: readonly(audioLevel),
        error: readonly(error),
        transcriptHistory: readonly(transcriptHistory),
        startSession,
        stopSession,
        mute,
        unmute,
    };
}
