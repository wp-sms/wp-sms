export type FieldDescriptionProps = {
  text?: string
}

export const FieldDescription: React.FC<FieldDescriptionProps> = ({ text }) => {
  if (!text) {
    return null
  }

  return <p className="text-xs font-normal text-muted-foreground">{text}</p>
}
