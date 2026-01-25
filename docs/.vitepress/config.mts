import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Atlas',
  description: 'AI Agents for Laravel - Build AI-powered applications with structure and scale',

  head: [
    ['link', { rel: 'icon', href: '/favicon.ico' }],
    ['meta', { property: 'og:title', content: 'Atlas - AI Agents for Laravel' }],
    ['meta', { property: 'og:description', content: 'Build AI-powered applications with structure and scale' }],
    ['meta', { property: 'og:image', content: '/og-image.png' }],
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
        text: 'Core Concepts',
        items: [
          { text: 'Agents', link: '/core-concepts/agents' },
          { text: 'Tools', link: '/core-concepts/tools' },
          { text: 'System Prompts', link: '/core-concepts/system-prompts' },
          { text: 'Pipelines', link: '/core-concepts/pipelines' },
        ]
      },
      {
        text: 'Capabilities',
        items: [
          { text: 'Chat', link: '/capabilities/chat' },
          { text: 'Text', link: '/capabilities/text' },
          { text: 'Images', link: '/capabilities/images' },
          { text: 'Audio', link: '/capabilities/speech' },
          { text: 'Embeddings', link: '/capabilities/embeddings' },
          { text: 'Structured', link: '/capabilities/structured-output' },
          { text: 'Streaming', link: '/capabilities/streaming' },
          { text: 'Moderation', link: '/capabilities/moderation' },
          { text: 'MCP', link: '/capabilities/mcp' },
        ]
      },
      {
        text: 'Advanced',
        collapsed: true,
        items: [
          { text: 'Testing', link: '/advanced/testing' },
          { text: 'Custom Providers', link: '/advanced/custom-providers' },
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
      copyright: 'Copyright 2025 Atlas PHP · Created by <a href="https://github.com/timothymarois" target="_blank">Timothy Marois</a>'
    }
  },

  sitemap: { hostname: 'https://atlasphp.org' }
})
