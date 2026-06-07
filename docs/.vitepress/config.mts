import { defineConfig, type HeadConfig } from 'vitepress';
import { readdirSync, statSync, mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { join, relative, dirname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const HOSTNAME = 'https://atlasphp.org';
const DOCS_ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

/**
 * Collect every markdown page under docs/ (skipping build/tooling dirs),
 * returned as forward-slash paths relative to the docs root.
 */
function collectPages(dir: string): string[] {
    const pages: string[] = [];
    for (const entry of readdirSync(dir)) {
        if (entry.startsWith('.') || entry === 'node_modules' || entry === 'public') continue;
        const full = join(dir, entry);
        if (statSync(full).isDirectory()) {
            pages.push(...collectPages(full));
        } else if (entry.endsWith('.md')) {
            pages.push(relative(DOCS_ROOT, full).split(sep).join('/'));
        }
    }
    return pages;
}

/**
 * Rewrite each page to a directory index (foo.md -> foo/index.md) so the
 * canonical URL is the clean, trailing-slash form (/foo/). The homepage and any
 * existing index files are left untouched.
 */
const rewrites: Record<string, string> = {};
for (const page of collectPages(DOCS_ROOT)) {
    if (page === 'index.md' || page.endsWith('/index.md')) continue;
    rewrites[page] = page.replace(/\.md$/, '/index.md');
}

// Reverse map (rewritten dest -> real source) so hooks that receive a page's
// rewritten relativePath (foo/index.md) can read the actual source file (foo.md).
const sourceByDest: Record<string, string> = Object.fromEntries(
    Object.entries(rewrites).map(([source, dest]) => [dest, source]),
);

/** Build the clean, trailing-slash canonical URL for a page's source path. */
function canonicalFor(relativePath: string): string {
    const path = relativePath
        .replace(/\.md$/, '')
        .replace(/(^|\/)index$/, '$1')
        .replace(/\/$/, '');
    return path === '' ? `${HOSTNAME}/` : `${HOSTNAME}/${path}/`;
}

/** Strip the common inline markdown so a paragraph reads cleanly as meta text. */
function stripMarkdown(text: string): string {
    return text
        .replace(/`([^`]+)`/g, '$1')                 // inline code
        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')      // links -> link text
        .replace(/[*_]{1,3}([^*_]+)[*_]{1,3}/g, '$1') // bold / italic
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Derive a page description from its first prose paragraph (the lead text under
 * the H1), so social cards and search results show page-specific copy instead of
 * the generic site description. Returns null when no suitable paragraph is found.
 */
function leadDescription(relativePath: string): string | null {
    // relativePath may be the rewritten dest (foo/index.md); read the real source.
    const source = sourceByDest[relativePath] ?? relativePath;

    let raw: string;
    try {
        raw = readFileSync(join(DOCS_ROOT, source), 'utf-8');
    } catch {
        return null;
    }

    // Drop frontmatter, then walk lines for the first real paragraph after the H1,
    // skipping headings, code fences, containers, tables, and lists.
    raw = raw.replace(/^---\r?\n[\s\S]*?\r?\n---\r?\n/, '');
    const lines = raw.split(/\r?\n/);
    const paragraph: string[] = [];
    let started = false;

    for (const line of lines) {
        const trimmed = line.trim();

        if (!started) {
            if (
                trimmed === '' ||
                trimmed.startsWith('#') ||
                trimmed.startsWith('```') ||
                trimmed.startsWith(':::') ||
                trimmed.startsWith('<') ||
                trimmed.startsWith('|') ||
                trimmed.startsWith('- ') ||
                trimmed.startsWith('* ') ||
                trimmed.startsWith('> ')
            ) {
                continue;
            }
            started = true;
            paragraph.push(trimmed);
        } else {
            if (trimmed === '' || trimmed.startsWith('#') || trimmed.startsWith('```')) break;
            paragraph.push(trimmed);
        }
    }

    if (paragraph.length === 0) return null;

    let description = stripMarkdown(paragraph.join(' '));
    if (description.length > 160) {
        description = `${description.slice(0, 157).trimEnd()}…`;
    }

    return description || null;
}

/** A no-content HTML page that redirects an old /foo.html URL to /foo/. */
function redirectStub(cleanPath: string): string {
    return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Redirecting&hellip;</title>
<link rel="canonical" href="${HOSTNAME}${cleanPath}">
<meta http-equiv="refresh" content="0; url=${cleanPath}">
<script>location.replace("${cleanPath}" + location.search + location.hash)</script>
</head>
<body>Redirecting to <a href="${cleanPath}">${cleanPath}</a>&hellip;</body>
</html>
`;
}

export default defineConfig({
    title: 'Atlas',
    description: 'AI Agents for Laravel - Build AI-powered applications with structure and scale',

    // Serve clean, extensionless URLs. Pages are rewritten to directory indexes
    // (see `rewrites`) so the canonical URL is the trailing-slash form (/foo/).
    cleanUrls: true,
    rewrites,
    // Adds git-based last-updated times, which VitePress also writes as <lastmod> in sitemap.xml.
    lastUpdated: true,

    markdown: {
        // `env` isn't a Shiki-bundled grammar; alias it to `ini` so ```env
        // blocks (KEY=value, # comments) highlight without a "language not
        // loaded" warning falling back to plain text.
        languageAlias: {
            env: 'ini',
        },
    },

    head: [
        ['link', { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' }],
        ['link', { rel: 'icon', type: 'image/png', sizes: '256x256', href: '/favicon.png' }],
        ['link', { rel: 'apple-touch-icon', href: '/favicon.png' }],
        ['meta', { name: 'theme-color', content: '#1e1b4b' }],
        // og:type, og:site_name, og:image and twitter:card/image are site-wide.
        // Per-page og/twitter title + description are emitted in transformPageData.
        ['meta', { property: 'og:type', content: 'website' }],
        ['meta', { property: 'og:site_name', content: 'Atlas' }],
        ['meta', { property: 'og:image', content: `${HOSTNAME}/og-image.png` }],
        ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
        ['meta', { name: 'twitter:image', content: `${HOSTNAME}/og-image.png` }],
        // Cloudflare Web Analytics
        ['script', { defer: '', src: 'https://static.cloudflareinsights.com/beacon.min.js', 'data-cf-beacon': '{"token": "745294f0eaa04748bca79beeb599f6bc"}' }],
        // Google Analytics (gtag.js)
        ['script', { async: '', src: 'https://www.googletagmanager.com/gtag/js?id=G-JEV06LWG7N' }],
        ['script', {}, `window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-JEV06LWG7N');`],
    ],

    // Give every page its own title + description for social cards (Discord, Slack,
    // X) and search results. The page title comes from its H1; the description from
    // frontmatter if set, otherwise the page's first paragraph. Without this, every
    // shared link falls back to the generic site title/description.
    transformPageData: (pageData) => {
        const isHome = pageData.frontmatter?.layout === 'home' || pageData.relativePath === 'index.md';

        const title = isHome || !pageData.title
            ? 'Atlas - AI Agents for Laravel'
            : `${pageData.title} | Atlas`;

        const description =
            pageData.frontmatter?.description ||
            (isHome ? null : leadDescription(pageData.relativePath)) ||
            'Build AI-powered applications with structure and scale';

        // Drives the standard <meta name="description"> too, not just social cards.
        pageData.description = description;

        pageData.frontmatter.head ??= [];
        pageData.frontmatter.head.push(
            ['meta', { property: 'og:title', content: title }],
            ['meta', { property: 'og:description', content: description }],
            ['meta', { name: 'twitter:title', content: title }],
            ['meta', { name: 'twitter:description', content: description }],
        );
    },

    // Emit a per-page canonical (and og:url) pointing at the clean trailing-slash
    // URL so search engines consolidate on it, never on the .html redirect stub.
    transformHead: ({ pageData }) => {
        const canonical = canonicalFor(pageData.relativePath);
        const tags: HeadConfig[] = [
            ['link', { rel: 'canonical', href: canonical }],
            ['meta', { property: 'og:url', content: canonical }],
        ];

        return tags;
    },

    // After the static build, drop a redirect stub at every old /foo.html path so
    // already-indexed .html URLs (and any inbound links) forward to /foo/.
    buildEnd: ({ outDir }) => {
        for (const dest of Object.values(rewrites)) {
            const pageDir = dest.replace(/\/index\.md$/, '');
            const stubFile = join(outDir, `${pageDir}.html`);
            mkdirSync(dirname(stubFile), { recursive: true });
            writeFileSync(stubFile, redirectStub(`/${pageDir}/`), 'utf-8');
        }
    },

    themeConfig: {
        siteTitle: 'ATLAS',

        nav: [
            { text: 'Home', link: '/' },
            { text: 'Docs', link: '/getting-started/introduction' },
        ],

        sidebar: [
            {
                text: 'Getting Started',
                items: [
                    { text: 'Introduction', link: '/getting-started/introduction' },
                    { text: 'Installation', link: '/getting-started/installation' },
                    { text: 'Configuration', link: '/getting-started/configuration' },
                    { text: 'Providers', link: '/getting-started/providers' },
                ]
            },
            {
                text: 'Features',
                items: [
                    { text: 'Agents', link: '/features/agents' },
                    { text: 'Tools', link: '/features/tools' },
                    { text: 'Sub-agents', link: '/features/sub-agents' },
                    { text: 'Instructions', link: '/features/instructions' },
                    { text: 'Schema', link: '/features/schema' },
                    { text: 'Middleware', link: '/features/middleware' },
                    { text: 'Similarity Search', link: '/features/similarity-search' },
                ]
            },
            {
                text: 'Modalities',
                items: [
                    { text: 'Text', link: '/modalities/text' },
                    { text: 'Images', link: '/modalities/images' },
                    {
                        text: 'Audio',
                        link: '/modalities/audio',
                        items: [
                            { text: 'Speech', link: '/modalities/speech' },
                            { text: 'Music', link: '/modalities/music' },
                            { text: 'Sound Effects', link: '/modalities/sound-effects' },
                        ]
                    },
                    { text: 'Video', link: '/modalities/video' },
                    { text: 'Voice', link: '/modalities/voice' },
                    { text: 'Embeddings', link: '/modalities/embeddings' },
                    { text: 'Reranking', link: '/modalities/reranking' },
                    { text: 'Moderation', link: '/modalities/moderation' },
                    { text: 'Models', link: '/modalities/models' },
                    { text: 'Voices', link: '/modalities/voices' },
                ]
            },
            {
                text: 'Guides',
                items: [
                    { text: 'Conversations', link: '/guides/conversations' },
                    { text: 'Prompt Caching', link: '/guides/prompt-caching' },
                    { text: 'Streaming', link: '/guides/streaming' },
                    { text: 'Queue & Background Jobs', link: '/guides/queue' },
                    { text: 'Media & Assets', link: '/guides/media-storage' },
                    { text: 'Voice Integration', link: '/guides/voice-integration' },
                    { text: 'Custom Providers', link: '/guides/custom-providers' },
                    { text: 'Custom Drivers', link: '/guides/custom-drivers' },
                    { text: 'Artisan Commands', link: '/guides/artisan-commands' },
                ]
            },
            {
                text: 'Advanced',
                items: [
                    { text: 'Persistence', link: '/advanced/persistence' },
                    { text: 'Events', link: '/advanced/events' },
                    { text: 'Testing', link: '/advanced/testing' },
                    { text: 'Error Handling', link: '/advanced/error-handling' },
                ]
            },
        ],

        socialLinks: [
            { icon: 'github', link: 'https://github.com/atlas-php/atlas' }
        ],

        search: { provider: 'local' },

        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright 2025-2026 Atlas PHP · Created by <a href="https://marois.dev" target="_blank">Tim Marois</a>'
        }
    },

    sitemap: { hostname: 'https://atlasphp.org' }
});
