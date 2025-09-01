export type FieldDescriptionProps = {
  text?: string
}

export const FieldDescription = ({ text }: FieldDescriptionProps) => {
  if (!text) {
    return null
  }

  return <p className="text-xs font-normal text-muted-foreground">{text}</p>
}
