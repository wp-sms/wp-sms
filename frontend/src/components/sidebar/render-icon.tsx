import type { LucideProps } from 'lucide-react'
import {
  BadgeCheck,
  BarChart3,
  Bell,
  Calendar,
  Cog,
  GraduationCap,
  Lock,
  Mail,
  MessageCircle,
  MessageSquare,
  MousePointer,
  Newspaper,
  Send,
  Settings,
  Shield,
  ShoppingCart,
  Star,
  UserCheck,
  Users,
  Zap,
} from 'lucide-react'

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

export const RenderIcon = ({ iconName, ...props }: RenderIconProps) => {
  const IconComponent = iconMap?.[iconName] || Settings
  return <IconComponent {...props} />
}
