import { __, _n, sprintf } from '@wordpress/i18n'
import * as React from 'react'
import { Copy, ArrowRight } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogBody,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

/**
 * Find numbers that appear more than once among group members.
 *
 * @param {object[]} members - Members from smsApi.getGroupMembers()
 * @returns {{ number: string, members: object[] }[]} Duplicate numbers with the members sharing them
 */
export function findDuplicateNumbers(members = []) {
  const byNumber = new Map()

  members.forEach((member) => {
    const key = member.normalized || member.mobile
    if (!byNumber.has(key)) {
      byNumber.set(key, [])
    }
    byNumber.get(key).push(member)
  })

  return Array.from(byNumber.entries())
    .filter(([, list]) => list.length > 1)
    .map(([number, list]) => ({ number, members: list }))
}

/**
 * DuplicateNumbersDialog - Lists numbers that appear more than once in the
 * selected groups before sending. Each number only gets the message once.
 */
export function DuplicateNumbersDialog({ open, onOpenChange, duplicates = [], onContinue }) {
  const extraCount = duplicates.reduce((count, item) => count + item.members.length - 1, 0)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="lg">
        <DialogHeader>
          <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Copy className="wsms-h-4 wsms-w-4 wsms-text-amber-600" />
            {__('Duplicate numbers found', 'wp-sms')}
          </DialogTitle>
          <DialogDescription>
            {sprintf(
              /* translators: 1: number of duplicate phone numbers, 2: number of extra entries skipped */
              _n(
                '%1$d number appears more than once in the selected groups. Each number gets the message once, so %2$d duplicate entries will be skipped.',
                '%1$d numbers appear more than once in the selected groups. Each number gets the message once, so %2$d duplicate entries will be skipped.',
                duplicates.length,
                'wp-sms'
              ),
              duplicates.length,
              extraCount
            )}
          </DialogDescription>
        </DialogHeader>

        <DialogBody>
          <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            <div className="wsms-max-h-[320px] wsms-overflow-y-auto">
              <table className="wsms-w-full wsms-text-[12px]">
                <thead className="wsms-bg-muted/40 wsms-sticky wsms-top-0">
                  <tr>
                    <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">
                      {__('Number', 'wp-sms')}
                    </th>
                    <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">
                      {__('Subscribers', 'wp-sms')}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {duplicates.map(({ number, members }) => (
                    <tr key={number} className="wsms-border-t wsms-border-border wsms-align-top">
                      <td className="wsms-px-3 wsms-py-2 wsms-font-mono wsms-text-foreground wsms-whitespace-nowrap" dir="ltr">
                        {number}
                      </td>
                      <td className="wsms-px-3 wsms-py-2">
                        <ul className="wsms-space-y-0.5">
                          {members.map((member) => (
                            <li key={member.id} className="wsms-text-foreground">
                              {member.name || __('(no name)', 'wp-sms')}
                              <span className="wsms-text-muted-foreground">
                                {' '}
                                {sprintf(
                                  /* translators: %s: subscriber group name */
                                  __('in %s', 'wp-sms'),
                                  member.group_name || __('No group', 'wp-sms')
                                )}
                              </span>
                              {member.mobile !== number && (
                                <span className="wsms-text-muted-foreground wsms-font-mono" dir="ltr">
                                  {' '}({member.mobile})
                                </span>
                              )}
                            </li>
                          ))}
                        </ul>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <p className="wsms-mt-3 wsms-text-[11px] wsms-text-muted-foreground">
            {__('To leave someone out, go back, open the group and untick them.', 'wp-sms')}
          </p>
        </DialogBody>

        <DialogFooter>
          <Button variant="outline" size="sm" onClick={() => onOpenChange(false)}>
            {__('Go back', 'wp-sms')}
          </Button>
          <Button size="sm" onClick={onContinue}>
            {__('Send once per number', 'wp-sms')}
            <ArrowRight className="wsms-h-4 wsms-w-4 wsms-ms-1.5 rtl:wsms-scale-x-[-1]" />
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
