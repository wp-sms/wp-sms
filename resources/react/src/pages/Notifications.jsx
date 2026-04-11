import { __ } from '@wordpress/i18n'
import React, { useMemo } from 'react'
import { Bell, FileText, UserPlus, MessageCircle, LogIn, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import { InputField, SelectField, MultiSelectField, SettingRow } from '@/components/ui/form-field'
import { TemplateTextarea } from '@/components/shared/TemplateTextarea'
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
  // Legacy format stores just term IDs (not taxonomy:termId)
  const taxonomyOptions = useMemo(() => {
    if (!taxonomies) return []
    const options = []
    Object.entries(taxonomies).forEach(([taxName, taxData]) => {
      if (taxData.terms) {
        Object.entries(taxData.terms).forEach(([termId, termName]) => {
          options.push({
            value: termId, // Legacy format: just term ID
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
        title={__('New Content Notifications', 'wp-sms')}
        description={__('Send SMS when you publish new posts or pages.', 'wp-sms')}
        enabled={notifNewPost === '1'}
        onToggle={(checked) => setNotifNewPost(checked ? '1' : '')}
      >
        <MultiSelectField
          label={__('Content Types', 'wp-sms')}
          options={postTypes}
          value={notifNewPostTypes}
          onValueChange={setNotifNewPostTypes}
          placeholder={__('All post types', 'wp-sms')}
          searchPlaceholder={__('Search post types...', 'wp-sms')}
          description={__('Which content types trigger notifications.', 'wp-sms')}
        />

        <MultiSelectField
          label={__('Categories & Tags', 'wp-sms')}
          options={taxonomyOptions}
          value={notifNewPostTaxonomies}
          onValueChange={setNotifNewPostTaxonomies}
          placeholder={__('All taxonomies', 'wp-sms')}
          searchPlaceholder={__('Search categories, tags...', 'wp-sms')}
          description={__('Only notify for content in these categories or with these tags. Leave empty for all.', 'wp-sms')}
        />

        <SelectField
          label={__('Send To', 'wp-sms')}
          value={notifNewPostReceiver}
          onValueChange={setNotifNewPostReceiver}
          placeholder={__('Select recipients', 'wp-sms')}
          description={__('Who should receive these notifications.', 'wp-sms')}
          options={[
            { value: 'subscriber', label: __('Subscribers', 'wp-sms') },
            { value: 'numbers', label: __('Phone Numbers', 'wp-sms') },
            { value: 'users', label: __('User Roles', 'wp-sms') },
          ]}
        />

        {notifNewPostReceiver === 'subscriber' && (
          <SelectField
            label={__('Subscriber Group', 'wp-sms')}
            value={notifNewPostGroup}
            onValueChange={setNotifNewPostGroup}
            placeholder={__('Select group', 'wp-sms')}
            options={[
              { value: '0', label: __('All Groups', 'wp-sms') },
              ...(Array.isArray(groups) ? groups : Object.values(groups || {})).map((group) => ({
                value: String(group.id || group.ID),
                label: group.name || group,
              })),
            ]}
          />
        )}

        {notifNewPostReceiver === 'numbers' && (
          <InputField
            label={__('Phone Numbers', 'wp-sms')}
            value={notifNewPostNumbers}
            onChange={(e) => setNotifNewPostNumbers(e.target.value)}
            placeholder="+1 555 111 2222, +1 555 333 4444"
            description={__('Enter phone numbers, separated by commas.', 'wp-sms')}
          />
        )}

        {notifNewPostReceiver === 'users' && (
          <MultiSelectField
            label={__('User Roles', 'wp-sms')}
            options={roles}
            value={notifNewPostUsers}
            onValueChange={setNotifNewPostUsers}
            placeholder={__('Select user roles...', 'wp-sms')}
            searchPlaceholder={__('Search roles...', 'wp-sms')}
            description={__('Notify users with these roles.', 'wp-sms')}
          />
        )}

        <SettingRow
          title={__('Auto-send', 'wp-sms')}
          description={__('Send automatically when publishing (no confirmation prompt).', 'wp-sms')}
          checked={notifNewPostForce === '1'}
          onCheckedChange={(checked) => setNotifNewPostForce(checked ? '1' : '')}
        />

        <SettingRow
          title={__('Include Featured Image', 'wp-sms')}
          description={__("Send as MMS with the post's featured image (if gateway supports MMS).", 'wp-sms')}
          checked={notifNewPostMMS === '1'}
          onCheckedChange={(checked) => setNotifNewPostMMS(checked ? '1' : '')}
        />

        <div className="wsms-space-y-2">
          <Label htmlFor="postTemplate">{__('Message Template', 'wp-sms')}</Label>
          <TemplateTextarea
            id="postTemplate"
            value={notifNewPostTemplate}
            onChange={setNotifNewPostTemplate}
            placeholder={__('New post: %post_title% - Read more: %post_url%', 'wp-sms')}
            rows={3}
            variables={['%post_title%', '%post_content%', '%post_url%', '%post_date%', '%post_thumbnail%', '%post_author%', '%post_author_email%', '%post_status%', '%post_password%', '%post_comment_count%', '%post_post_type%', '%post_id%']}
          />
        </div>

        <InputField
          label={__('Content Word Limit', 'wp-sms')}
          type="number"
          value={notifNewPostWordCount}
          onChange={(e) => setNotifNewPostWordCount(e.target.value)}
          placeholder="10"
          description={__('Maximum words to include from post content in %post_content%.', 'wp-sms')}
        />
      </NotificationSection>

      {/* Post Author Notification */}
      <NotificationSection
        icon={FileText}
        title={__('Author Notifications', 'wp-sms')}
        description={__('Notify post authors when their content is published.', 'wp-sms')}
        enabled={notifPostAuthor === '1'}
        onToggle={(checked) => setNotifPostAuthor(checked ? '1' : '')}
      >
        <MultiSelectField
          label={__('Content Types', 'wp-sms')}
          options={postTypes}
          value={notifPostAuthorPostTypes}
          onValueChange={setNotifPostAuthorPostTypes}
          placeholder={__('All post types', 'wp-sms')}
          searchPlaceholder={__('Search post types...', 'wp-sms')}
          description={__('Which content types trigger author notifications.', 'wp-sms')}
        />

        <div className="wsms-space-y-2">
          <Label htmlFor="authorTemplate">{__('Message Template', 'wp-sms')}</Label>
          <TemplateTextarea
            id="authorTemplate"
            value={notifPostAuthorTemplate}
            onChange={setNotifPostAuthorTemplate}
            placeholder={__("Your post '%post_title%' has been published!", 'wp-sms')}
            rows={3}
            variables={['%post_title%', '%post_content%', '%post_url%', '%post_date%', '%post_thumbnail%', '%post_author%', '%post_author_email%', '%post_status%', '%post_password%', '%post_comment_count%', '%post_post_type%', '%post_id%']}
          />
        </div>
      </NotificationSection>

      {/* WordPress Update */}
      <NotificationSection
        icon={RefreshCw}
        title={__('WordPress Updates', 'wp-sms')}
        description={__('Get SMS alerts when a new WordPress version is available.', 'wp-sms')}
        enabled={notifWpVersion === '1'}
        onToggle={(checked) => setNotifWpVersion(checked ? '1' : '')}
      />

      {/* New User Registration */}
      <NotificationSection
        icon={UserPlus}
        title={__('New User Alerts', 'wp-sms')}
        description={__('Send SMS when someone registers on your site.', 'wp-sms')}
        enabled={notifNewUser === '1'}
        onToggle={(checked) => setNotifNewUser(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label htmlFor="userAdminTemplate">{__('Admin Notification', 'wp-sms')}</Label>
          <TemplateTextarea
            id="userAdminTemplate"
            value={notifNewUserAdminTemplate}
            onChange={setNotifNewUserAdminTemplate}
            placeholder={__('New user registered: %user_login% (%user_email%)', 'wp-sms')}
            rows={3}
            variables={['%user_id%', '%user_login%', '%user_email%', '%date_register%', '%user_url%', '%display_name%', '%first_name%', '%last_name%', '%user_role%']}
          />
          <p className="wsms-text-[12px] wsms-text-muted-foreground">
            {__('Sent to admin.', 'wp-sms')}
          </p>
        </div>

        <div className="wsms-space-y-2">
          <Label htmlFor="userTemplate">{__('Welcome Message', 'wp-sms')}</Label>
          <TemplateTextarea
            id="userTemplate"
            value={notifNewUserTemplate}
            onChange={setNotifNewUserTemplate}
            placeholder={__('Welcome %first_name%! Your account has been created.', 'wp-sms')}
            rows={3}
            variables={['%user_id%', '%user_login%', '%user_email%', '%date_register%', '%user_url%', '%display_name%', '%first_name%', '%last_name%', '%user_role%']}
          />
          <p className="wsms-text-[12px] wsms-text-muted-foreground">
            {__('Sent to new user.', 'wp-sms')}
          </p>
        </div>
      </NotificationSection>

      {/* New Comment */}
      <NotificationSection
        icon={MessageCircle}
        title={__('New Comment Alerts', 'wp-sms')}
        description={__('Get SMS when someone comments on your content.', 'wp-sms')}
        enabled={notifNewComment === '1'}
        onToggle={(checked) => setNotifNewComment(checked ? '1' : '')}
      >
        <div className="wsms-space-y-2">
          <Label htmlFor="commentTemplate">{__('Message Template', 'wp-sms')}</Label>
          <TemplateTextarea
            id="commentTemplate"
            value={notifNewCommentTemplate}
            onChange={setNotifNewCommentTemplate}
            placeholder={__("New comment on '%comment_post_title%' by %comment_author%", 'wp-sms')}
            rows={3}
            variables={['%comment_id%', '%comment_author%', '%comment_author_email%', '%comment_author_url%', '%comment_author_IP%', '%comment_date%', '%comment_content%', '%comment_url%', '%comment_post_title%', '%comment_post_url%', '%comment_post_id%']}
          />
        </div>
      </NotificationSection>

      {/* User Login */}
      <NotificationSection
        icon={LogIn}
        title={__('Login Alerts', 'wp-sms')}
        description={__('Get SMS when users log into your site.', 'wp-sms')}
        enabled={notifUserLogin === '1'}
        onToggle={(checked) => setNotifUserLogin(checked ? '1' : '')}
      >
        <MultiSelectField
          label={__('Monitor Roles', 'wp-sms')}
          options={roles}
          value={notifUserLoginRoles}
          onValueChange={setNotifUserLoginRoles}
          placeholder={__('All user roles', 'wp-sms')}
          searchPlaceholder={__('Search roles...', 'wp-sms')}
          description={__('Only notify when users with these roles log in.', 'wp-sms')}
        />

        <div className="wsms-space-y-2">
          <Label htmlFor="loginTemplate">{__('Message Template', 'wp-sms')}</Label>
          <TemplateTextarea
            id="loginTemplate"
            value={notifUserLoginTemplate}
            onChange={setNotifUserLoginTemplate}
            placeholder={__('User %user_login% logged in', 'wp-sms')}
            rows={3}
            variables={['%user_id%', '%user_login%', '%user_email%', '%date_register%', '%user_url%', '%display_name%', '%first_name%', '%last_name%', '%user_role%']}
          />
        </div>
      </NotificationSection>
    </div>
  )
}
