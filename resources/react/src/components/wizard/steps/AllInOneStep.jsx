import { __ } from '@wordpress/i18n'
import React from 'react'
import AllInOneCompare from '@/components/shared/AllInOneCompare'

/**
 * All-in-One step: one screen with the free vs All-in-One table, no scrolling.
 */
export default function AllInOneStep() {
  return (
    <div className="wsms-mx-auto wsms-max-w-3xl wsms-space-y-3">
      <AllInOneCompare compact showHide={false} preview campaign="wizard-compare" />
    </div>
  )
}
