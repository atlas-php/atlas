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
      { text: 'API Reference', link: '/api-reference/atlas-facade' }
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/getting-started/introduction' },
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Configuration', link: '/getting-started/configuration' },
        ]
      },
      {
        text: 'Core Concepts',
        items: [
          { text: 'Agents', link: '/core-concepts/agents' },
          { text: 'Tools', link: '/core-concepts/tools' },
          { text: 'Conversations', link: '/core-concepts/conversations' },
          { text: 'System Prompts', link: '/core-concepts/system-prompts' },
          { text: 'Structured Output', link: '/core-concepts/structured-output' },
          { text: 'Pipelines', link: '/core-concepts/pipelines' },
        ]
      },
      {
        text: 'Capabilities',
        items: [
          { text: 'Chat', link: '/capabilities/chat' },
          { text: 'Streaming', link: '/capabilities/streaming' },
          { text: 'Embeddings', link: '/capabilities/embeddings' },
          { text: 'Image Generation', link: '/capabilities/images' },
          { text: 'Speech', link: '/capabilities/speech' },
        ]
      },
      {
        text: 'Guides',
        collapsed: true,
        items: [
          { text: 'Creating Agents', link: '/guides/creating-agents' },
          { text: 'Creating Tools', link: '/guides/creating-tools' },
          { text: 'Multi-Turn Conversations', link: '/guides/multi-turn-conversations' },
          { text: 'Extending Atlas', link: '/guides/extending-atlas' },
          { text: 'Testing', link: '/guides/testing' },
        ]
      },
      {
        text: 'API Reference',
        collapsed: true,
        items: [
          { text: 'Atlas Facade', link: '/api-reference/atlas-facade' },
          { text: 'AgentContract', link: '/api-reference/agent-contract' },
          { text: 'ToolContract', link: '/api-reference/tool-contract' },
          { text: 'Response Objects', link: '/api-reference/response-objects' },
          { text: 'Context Objects', link: '/api-reference/context-objects' },
        ]
      },
      {
        text: 'Advanced',
        collapsed: true,
        items: [
          { text: 'Stateless Architecture', link: '/advanced/stateless-architecture' },
          { text: 'Error Handling', link: '/advanced/error-handling' },
          { text: 'Performance', link: '/advanced/performance' },
        ]
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/atlas-php/atlas' }
    ],

    search: { provider: 'local' },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright 2025 Atlas PHP'
    }
  },

  sitemap: { hostname: 'https://atlasphp.org' }
})
