import { useEffect } from 'react'

interface PageMeta {
  title: string
  description: string
  /** Open Graph type; defaults to 'website'. */
  ogType?: string
}

function setMetaTag(selector: string, attribute: string, key: string, content: string): () => void {
  let tag = document.head.querySelector<HTMLMetaElement>(selector)
  const created = tag === null

  if (tag === null) {
    tag = document.createElement('meta')
    tag.setAttribute(attribute, key)
    document.head.appendChild(tag)
  }

  const previous = tag.getAttribute('content')
  tag.setAttribute('content', content)

  return () => {
    if (created) {
      tag?.remove()
    } else if (previous !== null) {
      tag?.setAttribute('content', previous)
    }
  }
}

/**
 * Page title, description and Open Graph tags without pulling in a helmet
 * library. The SPA has no SSR (CLAUDE.md §2 — no Next.js), so these are set
 * on mount and restored on unmount; crawlers that execute JS and link
 * unfurlers that follow redirects both pick them up.
 */
export function usePageMeta({ title, description, ogType = 'website' }: PageMeta): void {
  useEffect(() => {
    const previousTitle = document.title
    document.title = title

    const cleanups = [
      setMetaTag('meta[name="description"]', 'name', 'description', description),
      setMetaTag('meta[property="og:title"]', 'property', 'og:title', title),
      setMetaTag('meta[property="og:description"]', 'property', 'og:description', description),
      setMetaTag('meta[property="og:type"]', 'property', 'og:type', ogType),
      setMetaTag('meta[property="og:url"]', 'property', 'og:url', window.location.href),
      setMetaTag('meta[name="twitter:card"]', 'name', 'twitter:card', 'summary_large_image'),
    ]

    return () => {
      document.title = previousTitle
      cleanups.forEach((cleanup) => cleanup())
    }
  }, [title, description, ogType])
}
