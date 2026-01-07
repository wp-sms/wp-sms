import React, { useMemo } from 'react'
import { Bell, FileText, UserPlus, MessageCircle, LogIn, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { InputField, SelectField, MultiSelectField, SettingRow } from '@/components/ui/form-field'
import { TemplateTextarea } from '@/components/shared/TemplateTextarea'
import { useSetting } from '@/context/SettingsContext'
import { getWpSettings, __ } from '@/lib/utils'

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
          <Switch checked={enabled} onCheckedChange={onToggle} aria-label={`Enable ${title}`} />
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
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* New Post Alerts */}
      <NotificationSection
        icon={FileText}
        title={__('New Content Notifications')}
        description={__('Send SMS when you publish new posts or pages.')}
        enabled={notifNewPost === '1'}
        onToggle={(checked) => setNotifNewPost(checked ? '1' : '')}
      >
        <MultiSelectField
          label={__('Content Types')}
          options={postTypes}
          value={notifNewPostTypes}
          onValueChange={setNotifNewPostTypes}
          placeholder={__('All post types')}
          searchPlaceholder={__('Search post types...')}
          description={__('Which content types trigger notifications.')}
        />

        <MultiSelectField
          label={__('Categories & Tags')}
          options={taxonomyOptions}
          value={notifNewPostTaxonomies}
          onValueChange={setNotifNewPostTaxonomies}
          placeholder={__('All taxonomies')}
          searchPlaceholder={__('Search categories, tags...')}
          description={__('Only notify for content in these categories or with these tags. Leave empty for all.')}
        />

        <SelectField
          label={__('Send To')}
          value={notifNewPostReceiver}
          onValueChange={setNotifNewPostReceiver}
          placeholder={__('Select recipients')}
          description={__('Who should receive these notifications.')}
          options={[
            { value: 'subscriber', label: __('Subscribers') },
            { value: 'numbers', label: __('Phone Numbers') },
            { value: 'users', label: __('User Roles') },
          ]}
        />

        {notifNewPostReceiver === 'subscriber' && (
          <SelectField
            label={__('Subscriber Group')}
            value={notifNewPostGroup}
            onValueChange={setNotifNewPostGroup}
            placeholder={__('Select group')}
            options={[
              { value: '0', label: __('All Groups') },
              ...Object.entries(groups || {}).map(([id, group]) => ({
                value: String(id),
                label: typeof group === 'object' ? group.name : group,
              })),
            ]}
          />
        )}

        {notifNewPostReceiver === 'numbers' && (
          <InputField
            label={__('Phone Numbers')}
            value={notifNewPostNumbers}
            onChange={(e) => setNotifNewPostNumbers(e.target.value)}
            placeholder="+1 555 111 2222, +1 555 333 4444"
            description={__('Enter phone numbers, separated by commas.')}
          />
        )}

        {notifNewPostReceiver === 'users' && (
          <MultiSelectField
            label={__('User Roles')}
            options={roles}
            value={notifNewPostUsers}
            onValueChange={setNotifNewPostUsers}
            placeholder={__('Select user roles...')}
            searchPlaceholder={__('Search roles...')}
            description={__('Notify users with these roles.')}
          />
        )}

        <SettingRow
          title={__('Auto-send')}
          description={__('Send automatically when publishing (no confirmation prompt).')}
          checked={notifNewPostForce === '1'}
          onCheckedChange={(checked) => setNotifNewPostForce(checked ? '1' : '')}
        />

        <SettingRow
          title={__('Include Featured Image')}
          description={__("Send as MMS with the post's featured image (if gateway supports MMS).")}
          checked={notifNewPostMMS === '1'}
          onCheckedChange={(checked) => setNotifNewPostMMS(checked ? '1' : '')}
        />

        <div className="wsms-space-y-2">
          <Label htmlFor="postTemplate">{__('Message Template')}</Label>
          <TemplateTextarea
            id="postTemplate"
            value={notifNewPostTemplate}
            onChange={setNotifNewPostTemplate}
            placeholder={__('New post: %post_title% - Read more: %post_url%')}
            rows={3}
            variables={['%post_title%', '%post_url%', '%post_date%', '%post_content%', '%post_author%']}
          />
        </div>

        <InputField
          label={__('Content Word Limit')}
          type="number"
          value={notifNewPostWordCount}
          onChange={(e) => setNotifNewPostWordCount(e.target.value)}
          placeholder="10"
          description={__('Maximum words to include from post content in %post_content%.')}
        />
      </NotificationSection>

      {/* Post Author Notification */}
      <NotificationSection
        icon={FileText}
        title={__('Author Notifications')}
        description={__('Notify post authors when their content is published.')}
        enabled={notifPostAuthor === '1'}
        onToggle={(checked) => setNotifPostAuthor(checked ? '1' : '')}
      >
        <MultiSelectField
          label={__('Content Types')}
          options={postTypes}
          value={notifPostAuthorPostTypes}
          onValueChange={setNotifPostAuthorPostTypes}
          placeholder={__('All post types')}
          searchPlaceholder={__('Search post types...')}
          description={__('Which content types trigger author notifications.')}
        />

        <div className="wsms-space-y-2">
          <Label htmlFor="authorTemplate">{__('Message Template')}</Label>
          <TemplateTextarea
            id="authorTemplate"
            value={notifPostAuthorTemplate}
            onChange={setNotifPostAuthorTemplate}
            placeholder={__("Your post '%post_title%' has been published!")}
            rows={3}
            variables={['%post_title%', '%post_url%', '%post_date%', '%post_content%']}
          />
        </div>
      </NotificationSection>

      {/* WordPress Update */}
      <NotificationSection
        icon={RefreshCw}
        title={__('WordPress Updates')}
        description={__('Get SMS alerts when a new WordPress version is available.')}
        enabled={notifWpVersion === '1'}
        onToggle={(checked) => setNotifWpVersion(checked ? '1' : '')}
      />

      {/* New User Registration */}
      <NotificationSection
        icon={UserPlus}
        title={__('New User Alerts')}
        description={__('Send SMS when someone registers on your site.')}
        enabled={notifNewUser === '1'}
        onToggle={(checked) => setNotifNewUser(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label htmlFor="userAdminTemplate">{__('Admin Notification')}</Label>
          <TemplateTextarea
            id="userAdminTemplate"
            value={notifNewUserAdminTemplate}
            onChange={setNotifNewUserAdminTemplate}
            placeholder={__('New user registered: %user_login% (%user_email%)')}
            rows={3}
            variables={['%user_login%', '%user_email%', '%user_firstname%', '%user_lastname%', '%date_register%']}
          />
          <p className="wsms-text-[12px] wsms-text-muted-foreground">
            {__('Sent to admin.')}
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="userTemplate">{__('Welcome Message')}</Label>
          <TemplateTextarea
            id="userTemplate"
            value={notifNewUserTemplate}
            onChange={setNotifNewUserTemplate}
            placeholder={__('Welcome %user_firstname%! Your account has been created.')}
            rows={3}
            variables={['%user_login%', '%user_email%', '%user_firstname%', '%user_lastname%', '%date_register%']}
          />
          <p className="wsms-text-[12px] wsms-text-muted-foreground">
            {__('Sent to new user.')}
          </p>
        </div>
      </NotificationSection>

      {/* New Comment */}
      <NotificationSection
        icon={MessageCircle}
        title={__('New Comment Alerts')}
        description={__('Get SMS when someone comments on your content.')}
        enabled={notifNewComment === '1'}
        onToggle={(checked) => setNotifNewComment(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label htmlFor="commentTemplate">{__('Message Template')}</Label>
          <TemplateTextarea
            id="commentTemplate"
            value={notifNewCommentTemplate}
            onChange={setNotifNewCommentTemplate}
            placeholder={__("New comment on '%comment_post_title%' by %comment_author%")}
            rows={3}
            variables={['%comment_author%', '%comment_author_email%', '%comment_content%', '%comment_post_title%', '%comment_post_url%', '%comment_date%']}
          />
        </div>
      </NotificationSection>

      {/* User Login */}
      <NotificationSection
        icon={LogIn}
        title={__('Login Alerts')}
        description={__('Get SMS when users log into your site.')}
        enabled={notifUserLogin === '1'}
        onToggle={(checked) => setNotifUserLogin(checked ? '1' : '')}
      >
        <MultiSelectField
          label={__('Monitor Roles')}
          options={roles}
          value={notifUserLoginRoles}
          onValueChange={setNotifUserLoginRoles}
          placeholder={__('All user roles')}
          searchPlaceholder={__('Search roles...')}
          description={__('Only notify when users with these roles log in.')}
        />

        <div className="wsms-space-y-2">
          <Label htmlFor="loginTemplate">{__('Message Template')}</Label>
          <TemplateTextarea
            id="loginTemplate"
            value={notifUserLoginTemplate}
            onChange={setNotifUserLoginTemplate}
            placeholder={__('User %user_login% logged in at %date_login%')}
            rows={3}
            variables={['%user_login%', '%user_email%', '%user_firstname%', '%user_lastname%', '%date_login%']}
          />
        </div>
      </NotificationSection>
    </div>
  )
}
