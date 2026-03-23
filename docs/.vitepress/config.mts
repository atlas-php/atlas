import { defineConfig } from 'vitepress';

export default defineConfig({
    title: 'Atlas',
    description: 'AI Agents for Laravel - Build AI-powered applications with structure and scale',

    head: [
        ['link', { rel: 'icon', href: '/favicon.ico' }],
        ['meta', { property: 'og:title', content: 'Atlas - AI Agents for Laravel' }],
        ['meta', { property: 'og:description', content: 'Build AI-powered applications with structure and scale' }],
        ['meta', { property: 'og:image', content: '/og-image.png' }],
        // Cloudflare Web Analytics
        ['script', { defer: '', src: 'https://static.cloudflareinsights.com/beacon.min.js', 'data-cf-beacon': '{"token": "745294f0eaa04748bca79beeb599f6bc"}' }],
    ],

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
                    { text: 'Media & Assets', link: '/guides/media-storage' },
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
            copyright: 'Copyright 2025 Atlas PHP · Created by <a href="https://marois.dev" target="_blank">Timothy Marois</a>'
        }
    },

    sitemap: { hostname: 'https://atlasphp.org' }
});
