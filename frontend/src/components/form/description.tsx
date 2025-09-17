import { toast } from 'sonner'

import { useStableCallback } from '@/hooks/use-stable-callback'

export type FieldDescriptionProps = {
  text?: string
}

export const FieldDescription = ({ text }: FieldDescriptionProps) => {
  const handleCodeClick = useStableCallback(async (event: React.MouseEvent<HTMLElement>) => {
    const target = event.target as HTMLElement
    if (target.tagName === 'CODE') {
      const textToCopy = target.textContent || ''
      try {
        await navigator.clipboard.writeText(textToCopy)
        toast.success('Code snippet copied to clipboard')
      } catch {}
    }
  }, [])

  if (!text) {
    return null
  }

  // Check if text contains HTML tags
  const hasHtmlTags = /<[^>]*>/g.test(text)

  if (hasHtmlTags) {
    return (
      <div
        className="text-xs font-normal text-muted-foreground [&_code]:cursor-pointer"
        dangerouslySetInnerHTML={{ __html: text }}
        onClick={handleCodeClick}
      />
    )
  }

  return <p className="text-xs font-normal text-muted-foreground">{text}</p>
}
