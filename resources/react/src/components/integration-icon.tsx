import { cn } from '@/lib/utils';
import {
  Globe,
  ShoppingCart,
  Shield,
  Webhook,
  MessageSquare,
  Puzzle,
  type LucideIcon,
} from 'lucide-react';

interface IntegrationIconProps {
  icon: string;
  size?: 'sm' | 'md';
  className?: string;
}

interface IconConfig {
  icon: LucideIcon;
  color: string;
  bg: string;
}

const ICON_MAP: Record<string, IconConfig> = {
  'globe':          { icon: Globe,        color: 'text-blue-600',   bg: 'bg-blue-50' },
  'shopping-cart':  { icon: ShoppingCart,  color: 'text-purple-600', bg: 'bg-purple-50' },
  'shield':         { icon: Shield,       color: 'text-green-600',  bg: 'bg-green-50' },
  'webhook':        { icon: Webhook,      color: 'text-amber-600',  bg: 'bg-amber-50' },
  'message-square': { icon: MessageSquare, color: 'text-indigo-600', bg: 'bg-indigo-50' },
};

const FALLBACK: IconConfig = { icon: Puzzle, color: 'text-muted-foreground', bg: 'bg-muted' };

const sizes = {
  sm: { container: 'h-5 w-5 rounded', icon: 'h-3 w-3' },
  md: { container: 'h-7 w-7 rounded-md', icon: 'h-4 w-4' },
} as const;

export function IntegrationIcon({ icon, size = 'md', className }: IntegrationIconProps) {
  const config = ICON_MAP[icon] ?? FALLBACK;
  const s = sizes[size];
  const Icon = config.icon;

  return (
    <span className={cn('inline-flex shrink-0 items-center justify-center', s.container, config.bg, className)}>
      <Icon className={cn(s.icon, config.color)} />
    </span>
  );
}
