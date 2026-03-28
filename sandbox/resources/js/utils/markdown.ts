import { marked } from 'marked';
import DOMPurify from 'dompurify';

const renderer = new marked.Renderer();

renderer.link = ({ href, title, text }) => {
    const isExternal = href && !href.startsWith('/');
    const target = isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
    const titleAttr = title ? ` title="${title}"` : '';
    return `<a href="${href}"${titleAttr}${target}>${text}</a>`;
};

marked.setOptions({
    renderer,
    breaks: true,
    gfm: true,
});

export function renderMarkdown(text: string): string {
    const html = marked.parse(text) as string;
    return DOMPurify.sanitize(html, {
        ADD_TAGS: ['audio', 'video'],
        ADD_ATTR: ['target', 'controls', 'src', 'download', 'autoplay', 'loop', 'muted', 'playsinline'],
    });
}
