/**
 * Insert a string at the cursor position of an input or textarea element.
 * Restores focus and places the cursor after the inserted text.
 */
export function insertVariableAtCursor(
  el: HTMLInputElement | HTMLTextAreaElement | null,
  variable: string,
  currentValue: string,
  onChange: (value: string) => void,
) {
  if (el) {
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? start;
    onChange(currentValue.slice(0, start) + variable + currentValue.slice(end));
    requestAnimationFrame(() => {
      el.focus();
      const pos = start + variable.length;
      el.setSelectionRange(pos, pos);
    });
  } else {
    onChange(currentValue + variable);
  }
}
