import * as React from 'react'
import { Users, UserCog, Phone, Hash, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { getWpSettings } from '@/lib/utils'

const TABS = [
  { id: 'groups', label: 'Groups', icon: Users },
  { id: 'roles', label: 'User Roles', icon: UserCog },
  { id: 'numbers', label: 'Phone Numbers', icon: Phone },
]

/**
 * RecipientSelector - Multi-tab selector for SMS recipients
 * Supports selecting groups, user roles, and manual phone numbers
 */
const RecipientSelector = React.forwardRef(
  (
    {
      className,
      value = { groups: [], roles: [], numbers: [] },
      onChange,
      disabled = false,
      ...props
    },
    ref
  ) => {
    const [activeTab, setActiveTab] = React.useState('groups')
    const [numberInput, setNumberInput] = React.useState('')
    const { groups = [], roles = [] } = getWpSettings()

    const handleGroupToggle = (groupId) => {
      const newGroups = value.groups.includes(groupId)
        ? value.groups.filter((id) => id !== groupId)
        : [...value.groups, groupId]
      onChange?.({ ...value, groups: newGroups })
    }

    const handleRoleToggle = (roleId) => {
      const newRoles = value.roles.includes(roleId)
        ? value.roles.filter((id) => id !== roleId)
        : [...value.roles, roleId]
      onChange?.({ ...value, roles: newRoles })
    }

    const handleAddNumber = () => {
      const trimmed = numberInput.trim()
      if (!trimmed) return

      // Split by comma or newline for bulk entry
      const numbers = trimmed
        .split(/[,\n]/)
        .map((n) => n.trim())
        .filter((n) => n && !value.numbers.includes(n))

      if (numbers.length > 0) {
        onChange?.({ ...value, numbers: [...value.numbers, ...numbers] })
        setNumberInput('')
      }
    }

    const handleRemoveNumber = (number) => {
      onChange?.({
        ...value,
        numbers: value.numbers.filter((n) => n !== number),
      })
    }

    const handleKeyDown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault()
        handleAddNumber()
      }
    }

    const totalSelected =
      value.groups.length + value.roles.length + value.numbers.length

    // Convert groups object to array if needed
    const groupsArray = Array.isArray(groups)
      ? groups
      : Object.entries(groups).map(([id, name]) => ({ id, name }))

    // Convert roles object to array if needed
    const rolesArray = Array.isArray(roles)
      ? roles
      : Object.entries(roles).map(([id, name]) => ({ id, name }))

    return (
      <div
        ref={ref}
        className={cn(
          'wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-overflow-hidden',
          className
        )}
        {...props}
      >
        {/* Tab Navigation */}
        <div className="wsms-flex wsms-border-b wsms-border-border wsms-bg-muted/30">
          {TABS.map((tab) => {
            const Icon = tab.icon
            const count =
              tab.id === 'groups'
                ? value.groups.length
                : tab.id === 'roles'
                ? value.roles.length
                : value.numbers.length

            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => setActiveTab(tab.id)}
                disabled={disabled}
                className={cn(
                  'wsms-flex-1 wsms-flex wsms-items-center wsms-justify-center wsms-gap-2 wsms-px-4 wsms-py-2.5',
                  'wsms-text-[12px] wsms-font-medium wsms-transition-colors',
                  'wsms-border-b-2 wsms--mb-[1px]',
                  activeTab === tab.id
                    ? 'wsms-border-primary wsms-text-primary wsms-bg-card'
                    : 'wsms-border-transparent wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-muted/50'
                )}
              >
                <Icon className="wsms-h-4 wsms-w-4" />
                {tab.label}
                {count > 0 && (
                  <span className="wsms-ml-1 wsms-px-1.5 wsms-py-0.5 wsms-text-[10px] wsms-rounded-full wsms-bg-primary wsms-text-primary-foreground">
                    {count}
                  </span>
                )}
              </button>
            )
          })}
        </div>

        {/* Tab Content */}
        <div className="wsms-p-4 wsms-min-h-[200px] wsms-max-h-[300px] wsms-overflow-y-auto">
          {/* Groups Tab */}
          {activeTab === 'groups' && (
            <div className="wsms-space-y-2">
              {groupsArray.length === 0 ? (
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-text-center wsms-py-8">
                  No subscriber groups available
                </p>
              ) : (
                groupsArray.map((group) => (
                  <label
                    key={group.id}
                    className={cn(
                      'wsms-flex wsms-items-center wsms-gap-3 wsms-p-2.5 wsms-rounded-md',
                      'wsms-cursor-pointer wsms-transition-colors',
                      'hover:wsms-bg-muted/50',
                      value.groups.includes(group.id) && 'wsms-bg-primary/5'
                    )}
                  >
                    <Checkbox
                      checked={value.groups.includes(group.id)}
                      onCheckedChange={() => handleGroupToggle(group.id)}
                      disabled={disabled}
                    />
                    <span className="wsms-text-[13px]">{group.name}</span>
                    {group.count !== undefined && (
                      <span className="wsms-ml-auto wsms-text-[11px] wsms-text-muted-foreground">
                        {group.count} subscribers
                      </span>
                    )}
                  </label>
                ))
              )}
            </div>
          )}

          {/* Roles Tab */}
          {activeTab === 'roles' && (
            <div className="wsms-space-y-2">
              {rolesArray.length === 0 ? (
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-text-center wsms-py-8">
                  No user roles available
                </p>
              ) : (
                rolesArray.map((role) => (
                  <label
                    key={role.id}
                    className={cn(
                      'wsms-flex wsms-items-center wsms-gap-3 wsms-p-2.5 wsms-rounded-md',
                      'wsms-cursor-pointer wsms-transition-colors',
                      'hover:wsms-bg-muted/50',
                      value.roles.includes(role.id) && 'wsms-bg-primary/5'
                    )}
                  >
                    <Checkbox
                      checked={value.roles.includes(role.id)}
                      onCheckedChange={() => handleRoleToggle(role.id)}
                      disabled={disabled}
                    />
                    <span className="wsms-text-[13px]">{role.name}</span>
                  </label>
                ))
              )}
            </div>
          )}

          {/* Numbers Tab */}
          {activeTab === 'numbers' && (
            <div className="wsms-space-y-4">
              <div className="wsms-flex wsms-gap-2">
                <Input
                  type="text"
                  value={numberInput}
                  onChange={(e) => setNumberInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Enter phone number(s)..."
                  disabled={disabled}
                  className="wsms-flex-1"
                />
                <Button
                  type="button"
                  onClick={handleAddNumber}
                  disabled={disabled || !numberInput.trim()}
                >
                  Add
                </Button>
              </div>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">
                Enter numbers separated by commas or one per line
              </p>

              {value.numbers.length > 0 && (
                <div className="wsms-flex wsms-flex-wrap wsms-gap-2 wsms-pt-2">
                  {value.numbers.map((number) => (
                    <span
                      key={number}
                      className={cn(
                        'wsms-inline-flex wsms-items-center wsms-gap-1.5',
                        'wsms-px-2.5 wsms-py-1 wsms-rounded-full',
                        'wsms-bg-muted wsms-text-[12px]'
                      )}
                    >
                      <Hash className="wsms-h-3 wsms-w-3 wsms-text-muted-foreground" />
                      {number}
                      <button
                        type="button"
                        onClick={() => handleRemoveNumber(number)}
                        disabled={disabled}
                        className="wsms-ml-0.5 wsms-p-0.5 wsms-rounded-full hover:wsms-bg-accent wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors"
                      >
                        <X className="wsms-h-3 wsms-w-3" />
                      </button>
                    </span>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer with summary */}
        {totalSelected > 0 && (
          <div className="wsms-px-4 wsms-py-2.5 wsms-border-t wsms-border-border wsms-bg-muted/30">
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              <span className="wsms-font-medium wsms-text-foreground">{totalSelected}</span>{' '}
              recipient{totalSelected !== 1 ? 's' : ''} selected
              {value.groups.length > 0 && ` (${value.groups.length} groups)`}
              {value.roles.length > 0 && ` (${value.roles.length} roles)`}
              {value.numbers.length > 0 && ` (${value.numbers.length} numbers)`}
            </p>
          </div>
        )}
      </div>
    )
  }
)
RecipientSelector.displayName = 'RecipientSelector'

export { RecipientSelector }
