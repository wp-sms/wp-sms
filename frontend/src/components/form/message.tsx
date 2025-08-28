export type FieldMessageProps = {
  text?: string
}

export const FieldMessage: React.FC<FieldMessageProps> = ({ text }) => {
  if (!text) {
    return null
  }

  return <p className="text-xs font-normal text-destructive">{text}</p>
}
