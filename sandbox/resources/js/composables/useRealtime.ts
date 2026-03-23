import { ref, readonly } from 'vue';

export type SessionStatus = 'idle' | 'connecting' | 'active' | 'closed';

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
}

/**
 * Composable managing the entire realtime voice session lifecycle.
 *
 * Handles session creation, WebRTC peer connection setup,
 * audio capture, transcript streaming, and cleanup.
 */
export function useRealtime() {
    const sessionStatus = ref<SessionStatus>('idle');
    const isListening = ref(false);
    const isSpeaking = ref(false);
    const userTranscript = ref('');
    const assistantTranscript = ref('');
    const audioLevel = ref(0);
    const error = ref<string | null>(null);

    let peerConnection: RTCPeerConnection | null = null;
    let dataChannel: RTCDataChannel | null = null;
    let localStream: MediaStream | null = null;
    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let levelAnimFrame: number | null = null;

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

        try {
            // 1. Create session via backend
            const res = await fetch('/api/realtime/session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(options),
            });

            if (!res.ok) throw new Error(`Session creation failed: ${res.status}`);

            const session: SessionPayload = await res.json();

            if (session.transport === 'webrtc' && session.ephemeral_token) {
                await setupWebRtc(session);
            } else {
                // WebSocket mode — connection URL available for server relay
                sessionStatus.value = 'active';
                isListening.value = true;
            }
        } catch (e) {
            if (e instanceof DOMException && e.name === 'NotAllowedError') {
                error.value = 'Microphone permission denied. Please allow microphone access to use voice chat.';
            } else if (e instanceof DOMException && e.name === 'NotFoundError') {
                error.value = 'No microphone found. Please connect a microphone and try again.';
            } else {
                error.value = e instanceof Error ? e.message : 'Failed to start session';
            }
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
                case 'response.audio_transcript.delta':
                    assistantTranscript.value += data.delta ?? '';
                    isSpeaking.value = true;
                    break;
                case 'response.audio_transcript.done':
                    isSpeaking.value = false;
                    break;
                case 'conversation.item.input_audio_transcription.completed':
                    userTranscript.value = data.transcript ?? '';
                    break;
                case 'response.done':
                    isSpeaking.value = false;
                    break;
            }
        } catch {
            // Ignore non-JSON messages
        }
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

    function stopSession() {
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
        startSession,
        stopSession,
        mute,
        unmute,
    };
}
