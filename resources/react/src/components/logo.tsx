import type { SVGProps } from 'react';

export function Logo(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 512 512" fill="currentColor" {...props}>
      <path d="M116 167.808V257.015L312.101 153.007V64L116 167.808Z" />
      <path d="M116 285.7V374.707L395.989 226.296V137.289L116 285.7Z" />
      <path d="M396 254.984V342.991L200.116 447.999L199.898 357.992L396 254.984Z" />
    </svg>
  );
}
