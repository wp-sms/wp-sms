import {
  Settings,
  Users,
  Bell,
  Mail,
  UserCheck,
  ShoppingCart,
  GraduationCap,
  Calendar,
  MessageSquare,
  Cog,
  Send,
  Zap,
  Star,
  MousePointer,
  MessageCircle,
  Shield,
  BarChart3,
  Newspaper,
  BadgeCheck,
  Lock,
} from 'lucide-react'

import type { LucideProps } from 'lucide-react'

export type RenderIconProps = {
  iconName: string
} & LucideProps

const iconMap: Record<string, React.ComponentType<any>> = {
  contact_forms: Mail,
  community_membership: UserCheck,
  ecommerce: ShoppingCart,
  learning_events: GraduationCap,
  booking_appointments: Calendar,
  support: Bell,
  jobs: Users,
  contact_form_7: Mail,
  gravityforms: Mail,
  quform: Mail,
  buddypress: UserCheck,
  ultimate_member: UserCheck,
  woocommerce: ShoppingCart,
  edd: ShoppingCart,
  awesome_support: Bell,
  job_manager: Users,
  Settings,
  Cog,
  MessageSquare,
  Send,
  Zap,
  Star,
  Bell,
  Mail,
  Users,
  MousePointer,
  MessageCircle,
  Shield,
  Lock,
  ShoppingCart,
  UserCheck,
  GraduationCap,
  Calendar,
  BarChart3,
  Newspaper,
  BadgeCheck,
}

export const RenderIcon: React.FC<RenderIconProps> = ({ iconName, ...props }) => {
  const IconComponent = iconMap?.[iconName] || Settings
  return <IconComponent {...props} />
}
