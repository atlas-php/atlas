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
                    { text: 'Agents', link: '/core-concepts/agents' },
                    { text: 'Tools', link: '/core-concepts/tools' },
                    { text: 'Instructions', link: '/core-concepts/instructions' },
                    { text: 'Schema', link: '/core-concepts/schema' },
                    { text: 'Middleware', link: '/core-concepts/pipelines' },
                ]
            },
            {
                text: 'Modalities',
                items: [
                    { text: 'Text', link: '/capabilities/text' },
                    { text: 'Images', link: '/capabilities/images' },
                    { text: 'Audio', link: '/capabilities/audio' },
                    { text: 'Video', link: '/capabilities/video' },
                    { text: 'Embeddings', link: '/capabilities/embeddings' },
                    { text: 'Reranking', link: '/capabilities/reranking' },
                    { text: 'Moderation', link: '/capabilities/moderation' },
                    { text: 'Models', link: '/capabilities/models' },
                ]
            },
            {
                text: 'Guides',
                collapsed: true,
                items: [
                    { text: 'Custom Providers', link: '/guides/custom-providers' },
                    { text: 'Artisan Commands', link: '/guides/artisan-commands' },
                ]
            },
            {
                text: 'Advanced',
                collapsed: true,
                items: [
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
