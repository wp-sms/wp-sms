import React, { useState, useEffect } from 'react'
import {
  Shield,
  Search,
  Download,
  Trash2,
  User,
  Phone,
  MessageSquare,
  Users,
  CheckCircle,
  AlertCircle,
  AlertTriangle,
  Loader2,
  FileText,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { StatusBadge } from '@/components/shared/StatusBadge'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
} from '@/components/ui/dialog'
import { privacyApi } from '@/api/privacyApi'
import { cn, formatDate } from '@/lib/utils'

export default function Privacy() {
  // Search state
  const [phoneNumber, setPhoneNumber] = useState('')
  const [searchResults, setSearchResults] = useState(null)
  const [isSearching, setIsSearching] = useState(false)

  // Action state
  const [isExporting, setIsExporting] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)
  const [notification, setNotification] = useState(null)

  // Handle search
  const handleSearch = async (e) => {
    e.preventDefault()
    if (!phoneNumber.trim()) return

    setIsSearching(true)
    setSearchResults(null)
    setNotification(null)

    try {
      const result = await privacyApi.searchData(phoneNumber.trim())
      setSearchResults(result)

      if (!result.found) {
        setNotification({
          type: 'info',
          message: 'No data found for this phone number',
        })
      }
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsSearching(false)
    }
  }

  // Handle export
  const handleExport = async () => {
    if (!phoneNumber.trim()) return

    setIsExporting(true)
    try {
      const result = await privacyApi.exportData(phoneNumber.trim())
      privacyApi.downloadCsv(result.csvData, result.filename)
      setNotification({
        type: 'success',
        message: `Exported ${result.count} records successfully`,
      })
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsExporting(false)
    }
  }

  // Handle delete
  const handleDelete = async () => {
    if (!phoneNumber.trim()) return

    setIsDeleting(true)
    try {
      const result = await privacyApi.deleteData(phoneNumber.trim(), true)
      setNotification({
        type: 'success',
        message: result.message,
      })
      setSearchResults(null)
      setPhoneNumber('')
      setShowDeleteConfirm(false)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsDeleting(false)
    }
  }

  // Clear notification
  useEffect(() => {
    if (notification) {
      const timer = setTimeout(() => setNotification(null), 5000)
      return () => clearTimeout(timer)
    }
  }, [notification])

  // Get source icon
  const getSourceIcon = (source) => {
    switch (source) {
      case 'wp_users':
        return User
      case 'sms_subscribes':
        return Users
      case 'sms_send':
        return MessageSquare
      default:
        return FileText
    }
  }

  // Get source label
  const getSourceLabel = (source) => {
    switch (source) {
      case 'wp_users':
        return 'WordPress User'
      case 'sms_subscribes':
        return 'Newsletter Subscriber'
      case 'sms_send':
        return 'Sent Message'
      default:
        return source
    }
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Notification */}
      {notification && (
        <div
          className={cn(
            'wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-lg wsms-border',
            'wsms-animate-in wsms-fade-in wsms-slide-in-from-top-2 wsms-duration-300',
            notification.type === 'success'
              ? 'wsms-bg-emerald-500/10 wsms-border-emerald-500/20 wsms-text-emerald-700 dark:wsms-text-emerald-400'
              : notification.type === 'info'
              ? 'wsms-bg-blue-500/10 wsms-border-blue-500/20 wsms-text-blue-700 dark:wsms-text-blue-400'
              : 'wsms-bg-red-500/10 wsms-border-red-500/20 wsms-text-red-700 dark:wsms-text-red-400'
          )}
        >
          {notification.type === 'success' ? (
            <CheckCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          ) : notification.type === 'info' ? (
            <AlertCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          ) : (
            <AlertTriangle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          )}
          <p className="wsms-text-[13px] wsms-font-medium">{notification.message}</p>
        </div>
      )}

      {/* Info Card */}
      <Card className="wsms-border-primary/20 wsms-bg-primary/[0.02]">
        <CardContent className="wsms-py-4">
          <div className="wsms-flex wsms-items-start wsms-gap-4">
            <div className="wsms-p-2 wsms-rounded-lg wsms-bg-primary/10">
              <Shield className="wsms-h-6 wsms-w-6 wsms-text-primary" />
            </div>
            <div className="wsms-space-y-1">
              <h3 className="wsms-text-[14px] wsms-font-semibold wsms-text-foreground">
                GDPR Data Management
              </h3>
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Search for user data by phone number, export it for data portability requests,
                or delete it for right to erasure requests.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Search Form */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Search className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Search User Data
          </CardTitle>
          <CardDescription>
            Enter a phone number to search for associated data
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSearch} className="wsms-flex wsms-gap-3">
            <div className="wsms-relative wsms-flex-1">
              <Phone className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <Input
                type="tel"
                value={phoneNumber}
                onChange={(e) => setPhoneNumber(e.target.value)}
                placeholder="+1234567890"
                className="wsms-pl-9"
              />
            </div>
            <Button type="submit" disabled={isSearching || !phoneNumber.trim()}>
              {isSearching ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Searching...
                </>
              ) : (
                <>
                  <Search className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  Search
                </>
              )}
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Search Results */}
      {searchResults && searchResults.found && (
        <>
          {/* Summary */}
          <Card>
            <CardHeader>
              <CardTitle>Data Found</CardTitle>
              <CardDescription>
                {searchResults.summary.total_records} records found for {phoneNumber}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="wsms-grid wsms-grid-cols-3 wsms-gap-4">
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-text-center">
                  <User className="wsms-h-5 wsms-w-5 wsms-mx-auto wsms-text-muted-foreground wsms-mb-1" />
                  <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">
                    {searchResults.summary.wp_users}
                  </p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">User Records</p>
                </div>
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-text-center">
                  <Users className="wsms-h-5 wsms-w-5 wsms-mx-auto wsms-text-muted-foreground wsms-mb-1" />
                  <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">
                    {searchResults.summary.subscribers}
                  </p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">Subscriptions</p>
                </div>
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-text-center">
                  <MessageSquare className="wsms-h-5 wsms-w-5 wsms-mx-auto wsms-text-muted-foreground wsms-mb-1" />
                  <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">
                    {searchResults.summary.outbox_messages}
                  </p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">Messages</p>
                </div>
              </div>
            </CardContent>
            <CardFooter className="wsms-flex wsms-gap-3">
              <Button variant="outline" onClick={handleExport} disabled={isExporting}>
                {isExporting ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                    Exporting...
                  </>
                ) : (
                  <>
                    <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                    Export Data (CSV)
                  </>
                )}
              </Button>
              <Button
                variant="destructive"
                onClick={() => setShowDeleteConfirm(true)}
              >
                <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                Delete All Data
              </Button>
            </CardFooter>
          </Card>

          {/* Records List */}
          <Card>
            <CardHeader>
              <CardTitle>Records</CardTitle>
            </CardHeader>
            <CardContent className="wsms-p-0">
              <div className="wsms-divide-y wsms-divide-border">
                {searchResults.records.map((record, index) => {
                  const Icon = getSourceIcon(record.source)
                  return (
                    <div
                      key={`${record.source}-${record.id}-${index}`}
                      className="wsms-p-4 wsms-flex wsms-items-start wsms-gap-4"
                    >
                      <div className="wsms-p-2 wsms-rounded-md wsms-bg-muted">
                        <Icon className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                      </div>
                      <div className="wsms-flex-1 wsms-min-w-0">
                        <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mb-1">
                          <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
                            {record.display_name || 'Unknown'}
                          </span>
                          <StatusBadge variant="pending" size="sm">
                            {getSourceLabel(record.source)}
                          </StatusBadge>
                        </div>
                        <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-font-mono">
                          {record.mobile}
                        </p>
                        {record.message && (
                          <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-1 wsms-line-clamp-2">
                            {record.message}
                          </p>
                        )}
                        {record.group && (
                          <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-1">
                            Group: {record.group}
                          </p>
                        )}
                      </div>
                      <div className="wsms-text-right wsms-shrink-0">
                        <p className="wsms-text-[11px] wsms-text-muted-foreground">
                          {formatDate(record.created_at)}
                        </p>
                        {record.status && (
                          <StatusBadge
                            variant={record.status === 'Active' ? 'active' : 'inactive'}
                            size="sm"
                            className="wsms-mt-1"
                          >
                            {record.status}
                          </StatusBadge>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            </CardContent>
          </Card>
        </>
      )}

      {/* Delete Confirmation Dialog */}
      <Dialog open={showDeleteConfirm} onOpenChange={setShowDeleteConfirm}>
        <DialogContent size="sm">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-red-600">
              <AlertTriangle className="wsms-h-5 wsms-w-5" />
              Confirm Deletion
            </DialogTitle>
            <DialogDescription>
              This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <p className="wsms-text-[13px] wsms-text-foreground">
                You are about to permanently delete all data associated with:
              </p>
              <div className="wsms-p-3 wsms-rounded-md wsms-bg-muted wsms-font-mono wsms-text-[13px] wsms-text-center">
                {phoneNumber}
              </div>
              <div className="wsms-p-3 wsms-rounded-md wsms-bg-red-500/10 wsms-border wsms-border-red-500/20">
                <p className="wsms-text-[12px] wsms-text-red-700 dark:wsms-text-red-400">
                  This will delete:
                </p>
                <ul className="wsms-text-[12px] wsms-text-red-700 dark:wsms-text-red-400 wsms-list-disc wsms-ml-4 wsms-mt-2 wsms-space-y-1">
                  <li>User mobile number from profiles</li>
                  <li>Newsletter subscriptions</li>
                  <li>Sent message history</li>
                </ul>
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowDeleteConfirm(false)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleDelete} disabled={isDeleting}>
              {isDeleting ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Deleting...
                </>
              ) : (
                <>
                  <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  Delete All Data
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
