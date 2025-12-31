import * as React from 'react'
import { Users, UserCog, Phone, X, Search, Plus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { getWpSettings } from '@/lib/utils'

const TABS = [
  { id: 'groups', label: 'Groups', icon: Users, description: 'Subscriber groups' },
  { id: 'roles', label: 'Roles', icon: UserCog, description: 'WordPress users' },
  { id: 'numbers', label: 'Numbers', icon: Phone, description: 'Manual entry' },
]

/**
 * RecipientSelector - Enhanced multi-tab selector for SMS recipients
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
    const [searchQuery, setSearchQuery] = React.useState('')
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

    const handleClearAllNumbers = () => {
      onChange?.({ ...value, numbers: [] })
    }

    const handleKeyDown = (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
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

    // Filter items based on search query
    const filteredGroups = groupsArray.filter((group) =>
      group.name.toLowerCase().includes(searchQuery.toLowerCase())
    )
    const filteredRoles = rolesArray.filter((role) =>
      role.name.toLowerCase().includes(searchQuery.toLowerCase())
    )

    // Clear search when switching tabs
    React.useEffect(() => {
      setSearchQuery('')
    }, [activeTab])

    return (
      <div
        ref={ref}
        className={cn('wsms-space-y-3', className)}
        {...props}
      >
        {/* Tab Navigation - Compact Pill Style */}
        <div className="wsms-flex wsms-gap-1 wsms-p-1 wsms-rounded-lg wsms-bg-muted/50">
          {TABS.map((tab) => {
            const Icon = tab.icon
            const count =
              tab.id === 'groups'
                ? value.groups.length
                : tab.id === 'roles'
                ? value.roles.length
                : value.numbers.length
            const isActive = activeTab === tab.id

            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => setActiveTab(tab.id)}
                disabled={disabled}
                className={cn(
                  'wsms-flex-1 wsms-flex wsms-items-center wsms-justify-center wsms-gap-1.5 wsms-px-3 wsms-py-2',
                  'wsms-rounded-md wsms-text-[12px] wsms-font-medium wsms-transition-colors',
                  isActive
                    ? 'wsms-bg-card wsms-text-foreground wsms-shadow-sm'
                    : 'wsms-text-muted-foreground hover:wsms-text-foreground'
                )}
              >
                <Icon className={cn('wsms-h-3.5 wsms-w-3.5', isActive && 'wsms-text-primary')} />
                {tab.label}
                {count > 0 && (
                  <span
                    className={cn(
                      'wsms-px-1.5 wsms-py-0.5 wsms-text-[10px] wsms-rounded-full wsms-font-medium',
                      isActive
                        ? 'wsms-bg-primary wsms-text-primary-foreground'
                        : 'wsms-bg-muted wsms-text-muted-foreground'
                    )}
                  >
                    {count}
                  </span>
                )}
              </button>
            )
          })}
        </div>

        {/* Search Bar - Compact */}
        {(activeTab === 'groups' || activeTab === 'roles') && (
          <div className="wsms-relative">
            <Search className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
            <Input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder={`Search ${activeTab}...`}
              disabled={disabled}
              className="wsms-pl-8 wsms-h-8 wsms-text-[12px]"
            />
          </div>
        )}

        {/* Tab Content - Compact */}
        <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-overflow-hidden">
          <div className="wsms-min-h-[200px] wsms-max-h-[260px] wsms-overflow-y-auto">
            {/* Groups Tab */}
            {activeTab === 'groups' && (
              <div className="wsms-p-1.5">
                {filteredGroups.length === 0 ? (
                  <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-8 wsms-text-center">
                    <Users className="wsms-h-8 wsms-w-8 wsms-text-muted-foreground/30 wsms-mb-2" />
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {searchQuery ? 'No groups match your search' : 'No subscriber groups available'}
                    </p>
                  </div>
                ) : (
                  <div className="wsms-space-y-0.5">
                    {filteredGroups.map((group) => {
                      const isSelected = value.groups.includes(group.id)
                      return (
                        <label
                          key={group.id}
                          className={cn(
                            'wsms-flex wsms-items-center wsms-gap-2.5 wsms-px-2.5 wsms-py-2 wsms-rounded-md',
                            'wsms-cursor-pointer wsms-transition-colors',
                            'hover:wsms-bg-muted/50',
                            isSelected && 'wsms-bg-primary/5'
                          )}
                        >
                          <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => handleGroupToggle(group.id)}
                            disabled={disabled}
                          />
                          <span className="wsms-flex-1 wsms-text-[12px] wsms-text-foreground wsms-truncate">
                            {group.name}
                          </span>
                          {group.count !== undefined && (
                            <span className="wsms-text-[11px] wsms-text-muted-foreground">
                              {group.count}
                            </span>
                          )}
                        </label>
                      )
                    })}
                  </div>
                )}
              </div>
            )}

            {/* Roles Tab */}
            {activeTab === 'roles' && (
              <div className="wsms-p-1.5">
                {filteredRoles.length === 0 ? (
                  <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-8 wsms-text-center">
                    <UserCog className="wsms-h-8 wsms-w-8 wsms-text-muted-foreground/30 wsms-mb-2" />
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {searchQuery ? 'No roles match your search' : 'No user roles available'}
                    </p>
                  </div>
                ) : (
                  <div className="wsms-grid wsms-grid-cols-2 wsms-gap-0.5">
                    {filteredRoles.map((role) => {
                      const isSelected = value.roles.includes(role.id)
                      return (
                        <label
                          key={role.id}
                          className={cn(
                            'wsms-flex wsms-items-center wsms-gap-2 wsms-px-2.5 wsms-py-2 wsms-rounded-md',
                            'wsms-cursor-pointer wsms-transition-colors',
                            'hover:wsms-bg-muted/50',
                            isSelected && 'wsms-bg-primary/5'
                          )}
                        >
                          <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => handleRoleToggle(role.id)}
                            disabled={disabled}
                          />
                          <span className="wsms-text-[12px] wsms-text-foreground wsms-truncate">
                            {role.name}
                          </span>
                        </label>
                      )
                    })}
                  </div>
                )}
              </div>
            )}

            {/* Numbers Tab */}
            {activeTab === 'numbers' && (
              <div className="wsms-p-3 wsms-space-y-3">
                {/* Input Area */}
                <div className="wsms-flex wsms-gap-2">
                  <Input
                    type="text"
                    value={numberInput}
                    onChange={(e) => setNumberInput(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Enter phone number..."
                    disabled={disabled}
                    className="wsms-flex-1 wsms-h-8 wsms-text-[12px] wsms-font-mono"
                  />
                  <Button
                    type="button"
                    onClick={handleAddNumber}
                    disabled={disabled || !numberInput.trim()}
                    size="sm"
                    className="wsms-h-8"
                  >
                    <Plus className="wsms-h-3.5 wsms-w-3.5" />
                  </Button>
                </div>
                <p className="wsms-text-[10px] wsms-text-muted-foreground">
                  Separate multiple with commas
                </p>

                {/* Added Numbers */}
                {value.numbers.length > 0 && (
                  <div className="wsms-space-y-2 wsms-pt-2 wsms-border-t wsms-border-border">
                    <div className="wsms-flex wsms-items-center wsms-justify-between">
                      <p className="wsms-text-[11px] wsms-font-medium wsms-text-muted-foreground">
                        {value.numbers.length} number{value.numbers.length !== 1 ? 's' : ''}
                      </p>
                      <button
                        type="button"
                        onClick={handleClearAllNumbers}
                        disabled={disabled}
                        className="wsms-text-[10px] wsms-text-red-600 hover:wsms-text-red-700"
                      >
                        Clear
                      </button>
                    </div>
                    <div className="wsms-flex wsms-flex-wrap wsms-gap-1.5">
                      {value.numbers.map((number) => (
                        <span
                          key={number}
                          className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[11px] wsms-font-mono wsms-text-foreground"
                        >
                          {number}
                          <button
                            type="button"
                            onClick={() => handleRemoveNumber(number)}
                            disabled={disabled}
                            className="wsms-p-0.5 wsms-rounded hover:wsms-bg-accent wsms-text-muted-foreground hover:wsms-text-foreground"
                          >
                            <X className="wsms-h-3 wsms-w-3" />
                          </button>
                        </span>
                      ))}
                    </div>
                  </div>
                )}

                {/* Empty State */}
                {value.numbers.length === 0 && (
                  <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-6 wsms-text-center">
                    <Phone className="wsms-h-6 wsms-w-6 wsms-text-muted-foreground/30 wsms-mb-1.5" />
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">
                      No numbers added
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    )
  }
)
RecipientSelector.displayName = 'RecipientSelector'

export { RecipientSelector }
