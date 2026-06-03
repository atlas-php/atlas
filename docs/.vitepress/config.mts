import { defineConfig, type HeadConfig } from 'vitepress';

const HOSTNAME = 'https://atlasphp.org';

export default defineConfig({
    title: 'Atlas',
    description: 'AI Agents for Laravel - Build AI-powered applications with structure and scale',

    // Keep .html URLs — these are the indexed, canonical URLs and must not change.
    cleanUrls: false,
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

    // Emit a per-page canonical (and og:url) pointing at the .html URL so search
    // engines consolidate on it and never index the extensionless variant.
    transformHead: ({ pageData }) => {
        const canonical = HOSTNAME + '/' + pageData.relativePath
            .replace(/index\.md$/, '')
            .replace(/\.md$/, '.html');

        const tags: HeadConfig[] = [
            ['link', { rel: 'canonical', href: canonical }],
            ['meta', { property: 'og:url', content: canonical }],
        ];

        return tags;
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
            copyright: 'Copyright 2025-2026 Atlas PHP · Created by <a href="https://marois.dev" target="_blank">Timothy Marois</a>'
        }
    },

    sitemap: { hostname: 'https://atlasphp.org' }
});
