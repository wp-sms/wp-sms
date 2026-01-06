import React, { useState } from 'react'
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
  AlertTriangle,
  Loader2,
  FileText,
  Eye,
  Database,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Tip } from '@/components/ui/ux-helpers'
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
import { cn, formatDate, __ } from '@/lib/utils'
import { useToast } from '@/components/ui/toaster'

export default function Privacy() {
  const { toast } = useToast()

  // Search state
  const [phoneNumber, setPhoneNumber] = useState('')
  const [searchResults, setSearchResults] = useState(null)
  const [isSearching, setIsSearching] = useState(false)

  // Action state
  const [isExporting, setIsExporting] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)

  // Handle search
  const handleSearch = async (e) => {
    e.preventDefault()
    if (!phoneNumber.trim()) return

    setIsSearching(true)
    setSearchResults(null)

    try {
      const result = await privacyApi.searchData(phoneNumber.trim())
      setSearchResults(result)

      if (!result.found) {
        toast({
          title: __('No data found for this phone number'),
          variant: 'default',
        })
      }
    } catch (error) {
      toast({ title: error.message, variant: 'destructive' })
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
      toast({
        title: __(`Exported ${result.count} records successfully`),
        variant: 'success',
      })
    } catch (error) {
      toast({ title: error.message, variant: 'destructive' })
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
      toast({
        title: result.message,
        variant: 'success',
      })
      setSearchResults(null)
      setPhoneNumber('')
      setShowDeleteConfirm(false)
    } catch (error) {
      toast({ title: error.message, variant: 'destructive' })
    } finally {
      setIsDeleting(false)
    }
  }

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
      {/* Hero Section */}
      <div className="wsms-relative wsms-overflow-hidden wsms-rounded-lg wsms-bg-gradient-to-br wsms-from-primary/5 wsms-via-primary/10 wsms-to-transparent wsms-border wsms-border-primary/20">
        <div className="wsms-absolute wsms-top-0 wsms-right-0 wsms-w-32 wsms-h-32 wsms-bg-primary/5 wsms-rounded-full wsms--translate-y-1/2 wsms-translate-x-1/2" />
        <div className="wsms-relative wsms-p-6">
          <div className="wsms-flex wsms-items-start wsms-gap-4">
            <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-xl wsms-bg-primary/10 wsms-shrink-0">
              <Shield className="wsms-h-6 wsms-w-6 wsms-text-primary" />
            </div>
            <div>
              <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
                GDPR Data Management
              </h2>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-lg">
                Search for user data by phone number, export it for data portability requests (Article 20),
                or delete it for right to erasure requests (Article 17).
              </p>
            </div>
          </div>

          {/* Feature Cards */}
          <div className="wsms-grid wsms-grid-cols-3 wsms-gap-4 wsms-mt-6">
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3 wsms-rounded-lg wsms-bg-background/50 wsms-border wsms-border-border">
              <Eye className="wsms-h-5 wsms-w-5 wsms-text-blue-500" />
              <div>
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">Right to Access</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">Article 15</p>
              </div>
            </div>
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3 wsms-rounded-lg wsms-bg-background/50 wsms-border wsms-border-border">
              <Database className="wsms-h-5 wsms-w-5 wsms-text-emerald-500" />
              <div>
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">Data Portability</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">Article 20</p>
              </div>
            </div>
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3 wsms-rounded-lg wsms-bg-background/50 wsms-border wsms-border-border">
              <Trash2 className="wsms-h-5 wsms-w-5 wsms-text-red-500" />
              <div>
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">Right to Erasure</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">Article 17</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Search Form - Centered */}
      <Card>
        <CardContent className="wsms-py-6">
          <form onSubmit={handleSearch} className="wsms-max-w-xl wsms-mx-auto">
            <div className="wsms-text-center wsms-mb-6">
              <h3 className="wsms-text-[14px] wsms-font-semibold wsms-text-foreground wsms-mb-1">
                Search User Data
              </h3>
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Enter a phone number to find all associated data
              </p>
            </div>
            <div className="wsms-flex wsms-gap-3">
              <div className="wsms-relative wsms-flex-1">
                <Phone className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  type="tel"
                  value={phoneNumber}
                  onChange={(e) => setPhoneNumber(e.target.value)}
                  placeholder="+1234567890"
                  className="wsms-pl-9 wsms-h-11 wsms-text-base wsms-font-mono"
                />
              </div>
              <Button type="submit" disabled={isSearching || !phoneNumber.trim()} className="wsms-h-11 wsms-px-6">
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
            </div>
          </form>
        </CardContent>
      </Card>

      {/* Search Results */}
      {searchResults && searchResults.found && (
        <>
          {/* Summary Card */}
          <Card>
            <CardHeader>
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                    <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-success" />
                    Data Found
                  </CardTitle>
                  <CardDescription>
                    {searchResults.summary.total_records} records found for {phoneNumber}
                  </CardDescription>
                </div>
                {/* Action Buttons */}
                <div className="wsms-flex wsms-items-center wsms-gap-2">
                  <Button variant="outline" onClick={handleExport} disabled={isExporting}>
                    {isExporting ? (
                      <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
                    ) : (
                      <>
                        <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                        Export CSV
                      </>
                    )}
                  </Button>
                  <Button variant="destructive" onClick={() => setShowDeleteConfirm(true)}>
                    <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                    Delete All
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="wsms-grid wsms-grid-cols-3 wsms-gap-4">
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                  <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-blue-500/10">
                    <User className="wsms-h-6 wsms-w-6 wsms-text-blue-500" />
                  </div>
                  <div>
                    <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">
                      {searchResults.summary.wp_users}
                    </p>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">User Records</p>
                  </div>
                </div>
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                  <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-emerald-500/10">
                    <Users className="wsms-h-6 wsms-w-6 wsms-text-emerald-500" />
                  </div>
                  <div>
                    <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">
                      {searchResults.summary.subscribers}
                    </p>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">Subscriptions</p>
                  </div>
                </div>
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                  <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-amber-500/10">
                    <MessageSquare className="wsms-h-6 wsms-w-6 wsms-text-amber-500" />
                  </div>
                  <div>
                    <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">
                      {searchResults.summary.outbox_messages}
                    </p>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">Messages</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Records List */}
          <Card>
            <CardHeader>
              <CardTitle>All Records</CardTitle>
              <CardDescription>Detailed view of all data associated with this phone number</CardDescription>
            </CardHeader>
            <CardContent className="wsms-p-0">
              <div className="wsms-divide-y wsms-divide-border">
                {searchResults.records.map((record, index) => {
                  const Icon = getSourceIcon(record.source)
                  return (
                    <div
                      key={`${record.source}-${record.id}-${index}`}
                      className="wsms-p-4 wsms-flex wsms-items-start wsms-gap-4 hover:wsms-bg-muted/20 wsms-transition-colors"
                    >
                      <div className={cn(
                        'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-shrink-0',
                        record.source === 'wp_users' && 'wsms-bg-blue-500/10',
                        record.source === 'sms_subscribes' && 'wsms-bg-emerald-500/10',
                        record.source === 'sms_send' && 'wsms-bg-amber-500/10'
                      )}>
                        <Icon className={cn(
                          'wsms-h-5 wsms-w-5',
                          record.source === 'wp_users' && 'wsms-text-blue-500',
                          record.source === 'sms_subscribes' && 'wsms-text-emerald-500',
                          record.source === 'sms_send' && 'wsms-text-amber-500'
                        )} />
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
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-destructive">
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
              <div className="wsms-p-4 wsms-rounded-lg wsms-bg-muted wsms-font-mono wsms-text-[14px] wsms-text-center wsms-font-medium">
                {phoneNumber}
              </div>
              <div className="wsms-p-4 wsms-rounded-lg wsms-bg-destructive/10 wsms-border wsms-border-destructive/20">
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-destructive wsms-mb-2">
                  This will delete:
                </p>
                <ul className="wsms-text-[12px] wsms-text-destructive wsms-list-disc wsms-ml-4 wsms-space-y-1">
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
