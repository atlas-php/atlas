import { defineConfig, type HeadConfig } from 'vitepress';
import { readdirSync, statSync, mkdirSync, writeFileSync } from 'node:fs';
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

/** Build the clean, trailing-slash canonical URL for a page's source path. */
function canonicalFor(relativePath: string): string {
    const path = relativePath
        .replace(/\.md$/, '')
        .replace(/(^|\/)index$/, '$1')
        .replace(/\/$/, '');
    return path === '' ? `${HOSTNAME}/` : `${HOSTNAME}/${path}/`;
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

    head: [
        ['link', { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' }],
        ['link', { rel: 'icon', type: 'image/png', sizes: '256x256', href: '/favicon.png' }],
        ['link', { rel: 'apple-touch-icon', href: '/favicon.png' }],
        ['meta', { name: 'theme-color', content: '#1e1b4b' }],
        ['meta', { property: 'og:type', content: 'website' }],
        ['meta', { property: 'og:site_name', content: 'Atlas' }],
        ['meta', { property: 'og:title', content: 'Atlas - AI Agents for Laravel' }],
        ['meta', { property: 'og:description', content: 'Build AI-powered applications with structure and scale' }],
        ['meta', { property: 'og:image', content: `${HOSTNAME}/og-image.png` }],
        ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
        ['meta', { name: 'twitter:title', content: 'Atlas - AI Agents for Laravel' }],
        ['meta', { name: 'twitter:description', content: 'Build AI-powered applications with structure and scale' }],
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
