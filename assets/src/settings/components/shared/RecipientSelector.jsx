import * as React from 'react'
import { Users, UserCog, User, Phone, X, Search, Plus, Loader2 } from 'lucide-react'
import { cn, __, getWpSettings } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { smsApi } from '@/api/smsApi'

const TABS = [
  { id: 'groups', label: __('Groups'), icon: Users, description: __('Subscriber groups') },
  { id: 'roles', label: __('Roles'), icon: UserCog, description: __('WordPress roles') },
  { id: 'users', label: __('Users'), icon: User, description: __('Individual users') },
  { id: 'numbers', label: __('Numbers'), icon: Phone, description: __('Manual entry') },
]

/**
 * RecipientSelector - Enhanced multi-tab selector for SMS recipients
 * Supports selecting groups, user roles, and manual phone numbers
 */
const RecipientSelector = React.forwardRef(
  (
    {
      className,
      value = { groups: [], roles: [], users: [], numbers: [] },
      onChange,
      disabled = false,
      ...props
    },
    ref
  ) => {
    const [activeTab, setActiveTab] = React.useState('groups')
    const [numberInput, setNumberInput] = React.useState('')
    const [searchQuery, setSearchQuery] = React.useState('')
    const [userSearchQuery, setUserSearchQuery] = React.useState('')
    const [userSearchResults, setUserSearchResults] = React.useState([])
    const [isSearchingUsers, setIsSearchingUsers] = React.useState(false)
    const [selectedUserDetails, setSelectedUserDetails] = React.useState({})
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

    const handleUserSelect = (user) => {
      if (value.users.includes(user.id)) return
      // Store user details for display
      setSelectedUserDetails((prev) => ({
        ...prev,
        [user.id]: user,
      }))
      onChange?.({ ...value, users: [...value.users, user.id] })
      setUserSearchQuery('')
      setUserSearchResults([])
    }

    const handleUserRemove = (userId) => {
      onChange?.({
        ...value,
        users: value.users.filter((id) => id !== userId),
      })
    }

    const handleClearAllUsers = () => {
      onChange?.({ ...value, users: [] })
    }

    // Debounced user search
    React.useEffect(() => {
      if (activeTab !== 'users' || !userSearchQuery.trim()) {
        setUserSearchResults([])
        setIsSearchingUsers(false)
        return
      }

      // Show loading immediately when user types
      setIsSearchingUsers(true)

      const timer = setTimeout(async () => {
        try {
          const results = await smsApi.searchUsers(userSearchQuery)
          setUserSearchResults(results)
        } catch (error) {
          console.error('User search failed:', error)
          setUserSearchResults([])
        } finally {
          setIsSearchingUsers(false)
        }
      }, 300)

      return () => clearTimeout(timer)
    }, [userSearchQuery, activeTab])

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
      value.groups.length + value.roles.length + (value.users?.length || 0) + value.numbers.length

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
      setUserSearchQuery('')
      setUserSearchResults([])
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
                : tab.id === 'users'
                ? (value.users?.length || 0)
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
              placeholder={activeTab === 'groups' ? __('Search groups...') : __('Search roles...')}
              disabled={disabled}
              className="wsms-pl-8 wsms-h-8 wsms-text-[12px]"
            />
          </div>
        )}

        {/* User Search Bar */}
        {activeTab === 'users' && (
          <div className="wsms-relative">
            <Search className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
            <Input
              type="text"
              value={userSearchQuery}
              onChange={(e) => setUserSearchQuery(e.target.value)}
              placeholder={__('Search by name, email, or user ID...')}
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
                      {searchQuery ? __('No groups match your search') : __('No subscriber groups available')}
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
                      {searchQuery ? __('No roles match your search') : __('No user roles available')}
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

            {/* Users Tab */}
            {activeTab === 'users' && (
              <div className="wsms-p-3 wsms-space-y-3">
                {/* Search Results */}
                {userSearchQuery && (
                  <div className="wsms-space-y-1">
                    {isSearchingUsers ? (
                      <div className="wsms-flex wsms-items-center wsms-justify-center wsms-py-4">
                        <Loader2 className="wsms-h-5 wsms-w-5 wsms-text-muted-foreground wsms-animate-spin" />
                      </div>
                    ) : userSearchResults.length === 0 ? (
                      <div className="wsms-py-4 wsms-text-center">
                        <p className="wsms-text-[12px] wsms-text-muted-foreground">
                          {__('No users found')}
                        </p>
                      </div>
                    ) : (
                      <div className="wsms-space-y-0.5 wsms-max-h-[150px] wsms-overflow-y-auto">
                        {userSearchResults.map((user) => {
                          const isAlreadySelected = value.users?.includes(user.id)
                          return (
                            <button
                              key={user.id}
                              type="button"
                              onClick={() => handleUserSelect(user)}
                              disabled={disabled || isAlreadySelected || !user.hasMobile}
                              className={cn(
                                'wsms-w-full wsms-flex wsms-items-center wsms-gap-2.5 wsms-px-2.5 wsms-py-2 wsms-rounded-md',
                                'wsms-text-left wsms-transition-colors',
                                isAlreadySelected
                                  ? 'wsms-bg-primary/10 wsms-cursor-not-allowed'
                                  : !user.hasMobile
                                  ? 'wsms-opacity-50 wsms-cursor-not-allowed'
                                  : 'hover:wsms-bg-muted/50 wsms-cursor-pointer'
                              )}
                            >
                              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-w-7 wsms-h-7 wsms-rounded-full wsms-bg-muted">
                                <User className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
                              </div>
                              <div className="wsms-flex-1 wsms-min-w-0">
                                <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground wsms-truncate">
                                  {user.name}
                                  <span className="wsms-text-muted-foreground wsms-font-normal wsms-ml-1">
                                    #{user.id}
                                  </span>
                                </p>
                                <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-truncate">
                                  {user.hasMobile ? user.mobile : __('No mobile number')}
                                </p>
                              </div>
                              {isAlreadySelected && (
                                <span className="wsms-text-[10px] wsms-text-primary wsms-font-medium">
                                  {__('Selected')}
                                </span>
                              )}
                            </button>
                          )
                        })}
                      </div>
                    )}
                  </div>
                )}

                {/* Selected Users */}
                {(value.users?.length || 0) > 0 && (
                  <div className="wsms-space-y-2 wsms-pt-2 wsms-border-t wsms-border-border">
                    <div className="wsms-flex wsms-items-center wsms-justify-between">
                      <p className="wsms-text-[11px] wsms-font-medium wsms-text-muted-foreground">
                        {value.users.length} {value.users.length !== 1 ? __('users') : __('user')} {__('selected')}
                      </p>
                      <button
                        type="button"
                        onClick={handleClearAllUsers}
                        disabled={disabled}
                        className="wsms-text-[10px] wsms-text-red-600 hover:wsms-text-red-700"
                      >
                        {__('Clear')}
                      </button>
                    </div>
                    <div className="wsms-flex wsms-flex-wrap wsms-gap-1.5">
                      {value.users.map((userId) => {
                        const userDetails = selectedUserDetails[userId]
                        return (
                          <span
                            key={userId}
                            className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[11px] wsms-text-foreground"
                          >
                            <User className="wsms-h-3 wsms-w-3 wsms-text-muted-foreground" />
                            {userDetails?.name || `#${userId}`}
                            <button
                              type="button"
                              onClick={() => handleUserRemove(userId)}
                              disabled={disabled}
                              className="wsms-p-0.5 wsms-rounded hover:wsms-bg-accent wsms-text-muted-foreground hover:wsms-text-foreground"
                            >
                              <X className="wsms-h-3 wsms-w-3" />
                            </button>
                          </span>
                        )
                      })}
                    </div>
                  </div>
                )}

                {/* Empty State */}
                {!userSearchQuery && (value.users?.length || 0) === 0 && (
                  <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-6 wsms-text-center">
                    <User className="wsms-h-6 wsms-w-6 wsms-text-muted-foreground/30 wsms-mb-1.5" />
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">
                      {__('Search for users above')}
                    </p>
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
                    placeholder={__('Enter phone number...')}
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
                  {__('Separate multiple with commas')}
                </p>

                {/* Added Numbers */}
                {value.numbers.length > 0 && (
                  <div className="wsms-space-y-2 wsms-pt-2 wsms-border-t wsms-border-border">
                    <div className="wsms-flex wsms-items-center wsms-justify-between">
                      <p className="wsms-text-[11px] wsms-font-medium wsms-text-muted-foreground">
                        {value.numbers.length} {value.numbers.length !== 1 ? __('numbers') : __('number')}
                      </p>
                      <button
                        type="button"
                        onClick={handleClearAllNumbers}
                        disabled={disabled}
                        className="wsms-text-[10px] wsms-text-red-600 hover:wsms-text-red-700"
                      >
                        {__('Clear')}
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
                      {__('No numbers added')}
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
