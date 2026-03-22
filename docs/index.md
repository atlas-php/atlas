---
layout: home

hero:
  text: "AI Agents <span class='text-laravel'>for Laravel</span>"
  tagline: A unified AI execution layer for Laravel.
  image:
    light: /atlas-logo-5.png
    dark: /atlas-logo-2.png
    alt: Atlas
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started/introduction
    - theme: alt
      text: View on GitHub
      link: https://github.com/atlas-php/atlas

features:
  - icon:
      src: /icons/agent.svg
      alt: Agent Registry
    title: Agent Registry
    details: Reusable agent classes that encapsulate provider, model, instructions, tools, and behavior. Define once, use anywhere.
    link: /features/agents
  - icon:
      src: /icons/tool.svg
      alt: Tool Registry
    title: Tool Registry
    details: Typed tool classes with parameter schemas and dependency injection. Let AI call your PHP code safely.
    link: /features/tools
  - icon:
      src: /icons/multi-provider.svg
      alt: Multi-Modal
    title: Multi-Modal
    details: "Text, images, audio, video, embeddings, moderation, and reranking — all through one consistent API."
    link: /getting-started/introduction
  - icon:
      src: /icons/structured.svg
      alt: Structured Output
    title: Structured Output
    details: Extract typed data from AI responses. Get arrays, objects, or custom schemas instead of raw strings.
    link: /modalities/text
  - icon:
      src: /icons/streaming.svg
      alt: Dynamic Prompts
    title: Dynamic Prompts
    details: "Instructions with {variable} interpolation. Inject user context, session data, or custom values at runtime."
    link: /features/instructions
  - icon:
      src: /icons/pipeline.svg
      alt: Middleware & Events
    title: Middleware & Events
    details: Four middleware layers and 34 lifecycle events. Full observability from request to response.
    link: /advanced/events
---
