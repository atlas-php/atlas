import { ref, computed } from 'vue';

export interface Attachment {
    base64: string;
    mime: string;
    name: string;
    previewUrl: string | null;
}

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const DOC_TYPES = [
    'application/pdf',
    'text/plain',
    'text/markdown',
    'text/csv',
];

export const ACCEPTED_TYPES = [...IMAGE_TYPES, ...DOC_TYPES].join(',');
export const MAX_FILES = 5;

const EXTENSION_MIME_MAP: Record<string, string> = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    gif: 'image/gif',
    webp: 'image/webp',
    pdf: 'application/pdf',
    txt: 'text/plain',
    md: 'text/markdown',
    csv: 'text/csv',
};

function resolveMime(file: File): string {
    if (file.type) return file.type;
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
    return EXTENSION_MIME_MAP[ext] ?? 'application/octet-stream';
}

function readAsBase64(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const result = reader.result as string;
            // Strip the data URL prefix (data:mime;base64,)
            resolve(result.split(',')[1]);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

export function useAttachments() {
    const attachments = ref<Attachment[]>([]);

    const hasAttachments = computed(() => attachments.value.length > 0);
    const canAddMore = computed(() => attachments.value.length < MAX_FILES);

    async function addFiles(files: FileList | File[]) {
        const fileArray = Array.from(files);
        const remaining = MAX_FILES - attachments.value.length;
        const toAdd = fileArray.slice(0, remaining);

        for (const file of toAdd) {
            const mime = resolveMime(file);
            const base64 = await readAsBase64(file);
            const isImage = IMAGE_TYPES.includes(mime);

            attachments.value.push({
                base64,
                mime,
                name: file.name,
                previewUrl: isImage ? URL.createObjectURL(file) : null,
            });
        }
    }

    function removeAttachment(index: number) {
        const att = attachments.value[index];
        if (att?.previewUrl) {
            URL.revokeObjectURL(att.previewUrl);
        }
        attachments.value.splice(index, 1);
    }

    function clearAttachments() {
        // Don't revoke URLs — optimistic messages still reference them.
        // They'll be garbage collected when the blob is no longer referenced.
        attachments.value = [];
    }

    function toPayload(): { base64: string; mime: string; name: string }[] {
        return attachments.value.map(({ base64, mime, name }) => ({ base64, mime, name }));
    }

    return {
        attachments,
        hasAttachments,
        canAddMore,
        addFiles,
        removeAttachment,
        clearAttachments,
        toPayload,
    };
}
