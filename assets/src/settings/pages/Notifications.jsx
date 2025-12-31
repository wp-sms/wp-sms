import React, { useMemo } from 'react'
import { Bell, FileText, UserPlus, MessageCircle, LogIn, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { MultiSelect } from '@/components/ui/multi-select'
import { useSetting } from '@/context/SettingsContext'
import { getWpSettings } from '@/lib/utils'

function NotificationSection({
  icon: Icon,
  title,
  description,
  enabled,
  onToggle,
  children
}) {
  return (
    <Card>
      <CardHeader>
        <div className="wsms-flex wsms-items-start wsms-justify-between">
          <div className="wsms-flex wsms-items-start wsms-gap-3">
            <div className="wsms-rounded-lg wsms-bg-primary/10 wsms-p-2">
              <Icon className="wsms-h-5 wsms-w-5 wsms-text-primary" />
            </div>
            <div>
              <CardTitle className="wsms-text-base">{title}</CardTitle>
              <CardDescription>{description}</CardDescription>
            </div>
          </div>
          <Switch checked={enabled} onCheckedChange={onToggle} />
        </div>
      </CardHeader>
      {enabled && children && (
        <CardContent className="wsms-space-y-4 wsms-border-t wsms-pt-4">
          {children}
        </CardContent>
      )}
    </Card>
  )
}

export default function Notifications() {
  const { postTypes = {}, roles = {}, groups = {}, taxonomies = {} } = getWpSettings()

  // Convert taxonomies to a flat options list for MultiSelect
  const taxonomyOptions = useMemo(() => {
    if (!taxonomies) return []
    const options = []
    Object.entries(taxonomies).forEach(([taxName, taxData]) => {
      if (taxData.terms) {
        Object.entries(taxData.terms).forEach(([termId, termName]) => {
          options.push({
            value: `${taxName}:${termId}`,
            label: `${taxData.label}: ${termName}`,
          })
        })
      }
    })
    return options
  }, [taxonomies])

  // New Post Notification
  const [notifNewPost, setNotifNewPost] = useSetting('notif_publish_new_post', '')
  const [notifNewPostReceiver, setNotifNewPostReceiver] = useSetting('notif_publish_new_post_receiver', 'subscriber')
  const [notifNewPostGroup, setNotifNewPostGroup] = useSetting('notif_publish_new_post_default_group', '0')
  const [notifNewPostNumbers, setNotifNewPostNumbers] = useSetting('notif_publish_new_post_numbers', '')
  const [notifNewPostForce, setNotifNewPostForce] = useSetting('notif_publish_new_post_force', '')
  const [notifNewPostTemplate, setNotifNewPostTemplate] = useSetting('notif_publish_new_post_template', '')
  const [notifNewPostWordCount, setNotifNewPostWordCount] = useSetting('notif_publish_new_post_words_count', '10')
  // New fields for New Post Alerts
  const [notifNewPostTypes, setNotifNewPostTypes] = useSetting('notif_publish_new_post_type', [])
  const [notifNewPostTaxonomies, setNotifNewPostTaxonomies] = useSetting('notif_publish_new_taxonomy_and_term', [])
  const [notifNewPostUsers, setNotifNewPostUsers] = useSetting('notif_publish_new_post_users', [])
  const [notifNewPostMMS, setNotifNewPostMMS] = useSetting('notif_publish_new_send_mms', '')

  // Post Author Notification
  const [notifPostAuthor, setNotifPostAuthor] = useSetting('notif_publish_new_post_author', '')
  const [notifPostAuthorTemplate, setNotifPostAuthorTemplate] = useSetting('notif_publish_new_post_author_template', '')
  const [notifPostAuthorPostTypes, setNotifPostAuthorPostTypes] = useSetting('notif_publish_new_post_author_post_type', [])

  // WordPress Update Notification
  const [notifWpVersion, setNotifWpVersion] = useSetting('notif_publish_new_wpversion', '')

  // New User Registration
  const [notifNewUser, setNotifNewUser] = useSetting('notif_register_new_user', '')
  const [notifNewUserAdminTemplate, setNotifNewUserAdminTemplate] = useSetting('notif_register_new_user_admin_template', '')
  const [notifNewUserTemplate, setNotifNewUserTemplate] = useSetting('notif_register_new_user_template', '')

  // New Comment
  const [notifNewComment, setNotifNewComment] = useSetting('notif_new_comment', '')
  const [notifNewCommentTemplate, setNotifNewCommentTemplate] = useSetting('notif_new_comment_template', '')

  // User Login
  const [notifUserLogin, setNotifUserLogin] = useSetting('notif_user_login', '')
  const [notifUserLoginTemplate, setNotifUserLoginTemplate] = useSetting('notif_user_login_template', '')
  const [notifUserLoginRoles, setNotifUserLoginRoles] = useSetting('notif_user_login_roles', [])

  return (
    <div className="wsms-space-y-6">
      {/* New Post Alerts */}
      <NotificationSection
        icon={FileText}
        title="New Content Notifications"
        description="Send SMS when you publish new posts or pages."
        enabled={notifNewPost === '1'}
        onToggle={(checked) => setNotifNewPost(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label>Content Types</Label>
          <MultiSelect
            options={postTypes}
            value={notifNewPostTypes}
            onValueChange={setNotifNewPostTypes}
            placeholder="All post types"
            searchPlaceholder="Search post types..."
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Which content types trigger notifications.
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label>Categories & Tags</Label>
          <MultiSelect
            options={taxonomyOptions}
            value={notifNewPostTaxonomies}
            onValueChange={setNotifNewPostTaxonomies}
            placeholder="All taxonomies"
            searchPlaceholder="Search categories, tags..."
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Only notify for content in these categories or with these tags. Leave empty for all.
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label>Send To</Label>
          <Select value={notifNewPostReceiver} onValueChange={setNotifNewPostReceiver}>
            <SelectTrigger>
              <SelectValue placeholder="Select recipients" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="subscriber">Subscribers</SelectItem>
              <SelectItem value="numbers">Phone Numbers</SelectItem>
              <SelectItem value="users">User Roles</SelectItem>
            </SelectContent>
          </Select>
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Who should receive these notifications.
          </p>
        </div>

        {notifNewPostReceiver === 'subscriber' && (
          <div className="wsms-space-y-2">
            <Label>Subscriber Group</Label>
            <Select value={notifNewPostGroup} onValueChange={setNotifNewPostGroup}>
              <SelectTrigger>
                <SelectValue placeholder="Select group" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="0">All Groups</SelectItem>
                {groups && Object.entries(groups).map(([id, name]) => (
                  <SelectItem key={id} value={String(id)}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}

        {notifNewPostReceiver === 'numbers' && (
          <div className="wsms-space-y-2">
            <Label htmlFor="postNumbers">Phone Numbers</Label>
            <Input
              id="postNumbers"
              value={notifNewPostNumbers}
              onChange={(e) => setNotifNewPostNumbers(e.target.value)}
              placeholder="+1 555 111 2222, +1 555 333 4444"
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Enter phone numbers, separated by commas.
            </p>
          </div>
        )}

        {notifNewPostReceiver === 'users' && (
          <div className="wsms-space-y-2">
            <Label>User Roles</Label>
            <MultiSelect
              options={roles}
              value={notifNewPostUsers}
              onValueChange={setNotifNewPostUsers}
              placeholder="Select user roles..."
              searchPlaceholder="Search roles..."
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Notify users with these roles.
            </p>
          </div>
        )}

        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
          <div>
            <p className="wsms-font-medium">Auto-send</p>
            <p className="wsms-text-sm wsms-text-muted-foreground">
              Send automatically when publishing (no confirmation prompt).
            </p>
          </div>
          <Switch
            checked={notifNewPostForce === '1'}
            onCheckedChange={(checked) => setNotifNewPostForce(checked ? '1' : '')}
          />
        </div>

        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
          <div>
            <p className="wsms-font-medium">Include Featured Image</p>
            <p className="wsms-text-sm wsms-text-muted-foreground">
              Send as MMS with the post's featured image (if gateway supports MMS).
            </p>
          </div>
          <Switch
            checked={notifNewPostMMS === '1'}
            onCheckedChange={(checked) => setNotifNewPostMMS(checked ? '1' : '')}
          />
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="postTemplate">Message Template</Label>
          <Textarea
            id="postTemplate"
            value={notifNewPostTemplate}
            onChange={(e) => setNotifNewPostTemplate(e.target.value)}
            placeholder="New post: %post_title% - Read more: %post_url%"
            rows={3}
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Available variables: %post_title%, %post_url%, %post_date%, %post_content%, %post_author%
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="wordCount">Content Word Limit</Label>
          <Input
            id="wordCount"
            type="number"
            value={notifNewPostWordCount}
            onChange={(e) => setNotifNewPostWordCount(e.target.value)}
            placeholder="10"
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Maximum words to include from post content in %post_content%.
          </p>
        </div>
      </NotificationSection>

      {/* Post Author Notification */}
      <NotificationSection
        icon={FileText}
        title="Author Notifications"
        description="Notify post authors when their content is published."
        enabled={notifPostAuthor === '1'}
        onToggle={(checked) => setNotifPostAuthor(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label>Content Types</Label>
          <MultiSelect
            options={postTypes}
            value={notifPostAuthorPostTypes}
            onValueChange={setNotifPostAuthorPostTypes}
            placeholder="All post types"
            searchPlaceholder="Search post types..."
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Which content types trigger author notifications.
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="authorTemplate">Message Template</Label>
          <Textarea
            id="authorTemplate"
            value={notifPostAuthorTemplate}
            onChange={(e) => setNotifPostAuthorTemplate(e.target.value)}
            placeholder="Your post '%post_title%' has been published!"
            rows={3}
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Variables: %post_title%, %post_url%, %post_date%, %post_content%
          </p>
        </div>
      </NotificationSection>

      {/* WordPress Update */}
      <NotificationSection
        icon={RefreshCw}
        title="WordPress Updates"
        description="Get SMS alerts when a new WordPress version is available."
        enabled={notifWpVersion === '1'}
        onToggle={(checked) => setNotifWpVersion(checked ? '1' : '')}
      />

      {/* New User Registration */}
      <NotificationSection
        icon={UserPlus}
        title="New User Alerts"
        description="Send SMS when someone registers on your site."
        enabled={notifNewUser === '1'}
        onToggle={(checked) => setNotifNewUser(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label htmlFor="userAdminTemplate">Admin Notification</Label>
          <Textarea
            id="userAdminTemplate"
            value={notifNewUserAdminTemplate}
            onChange={(e) => setNotifNewUserAdminTemplate(e.target.value)}
            placeholder="New user registered: %user_login% (%user_email%)"
            rows={3}
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Sent to admin. Variables: %user_login%, %user_email%, %user_firstname%, %user_lastname%, %date_register%
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="userTemplate">Welcome Message</Label>
          <Textarea
            id="userTemplate"
            value={notifNewUserTemplate}
            onChange={(e) => setNotifNewUserTemplate(e.target.value)}
            placeholder="Welcome %user_firstname%! Your account has been created."
            rows={3}
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Sent to new user. Variables: %user_login%, %user_email%, %user_firstname%, %user_lastname%, %date_register%
          </p>
        </div>
      </NotificationSection>

      {/* New Comment */}
      <NotificationSection
        icon={MessageCircle}
        title="New Comment Alerts"
        description="Get SMS when someone comments on your content."
        enabled={notifNewComment === '1'}
        onToggle={(checked) => setNotifNewComment(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label htmlFor="commentTemplate">Message Template</Label>
          <Textarea
            id="commentTemplate"
            value={notifNewCommentTemplate}
            onChange={(e) => setNotifNewCommentTemplate(e.target.value)}
            placeholder="New comment on '%comment_post_title%' by %comment_author%"
            rows={3}
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Variables: %comment_author%, %comment_author_email%, %comment_content%, %comment_post_title%, %comment_post_url%, %comment_date%
          </p>
        </div>
      </NotificationSection>

      {/* User Login */}
      <NotificationSection
        icon={LogIn}
        title="Login Alerts"
        description="Get SMS when users log into your site."
        enabled={notifUserLogin === '1'}
        onToggle={(checked) => setNotifUserLogin(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label>Monitor Roles</Label>
          <MultiSelect
            options={roles}
            value={notifUserLoginRoles}
            onValueChange={setNotifUserLoginRoles}
            placeholder="All user roles"
            searchPlaceholder="Search roles..."
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Only notify when users with these roles log in.
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="loginTemplate">Message Template</Label>
          <Textarea
            id="loginTemplate"
            value={notifUserLoginTemplate}
            onChange={(e) => setNotifUserLoginTemplate(e.target.value)}
            placeholder="User %user_login% logged in at %date_login%"
            rows={3}
          />
          <p className="wsms-text-xs wsms-text-muted-foreground">
            Variables: %user_login%, %user_email%, %user_firstname%, %user_lastname%, %date_login%
          </p>
        </div>
      </NotificationSection>
    </div>
  )
}
