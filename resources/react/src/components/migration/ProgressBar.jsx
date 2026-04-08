import React, { useEffect, useState } from 'react'

/**
 * Simulated progress bar for the Execute step. Real per-source progress would
 * require splitting execute into multiple AJAX calls (losing atomic backup) or
 * polling a status endpoint with non-blocking work — neither is worth the cost
 * yet. The fill caps at 95% until the caller flips `isDone`.
 */
const TICK_INTERVAL_MS = 150

export default function ProgressBar({ estimatedMs = 5000, isDone = false }) {
  const [progress, setProgress] = useState(0)

  useEffect(() => {
    if (isDone) {
      setProgress(100)
      return undefined
    }

    const startedAt = performance.now()
    const id = setInterval(() => {
      const elapsed = performance.now() - startedAt
      const ratio = Math.min(1, elapsed / estimatedMs)
      // Decaying curve: 95% * (1 - e^(-3r)) — fast at start, slow approach to cap.
      setProgress(Math.min(95, 95 * (1 - Math.exp(-ratio * 3))))
    }, TICK_INTERVAL_MS)

    return () => clearInterval(id)
  }, [estimatedMs, isDone])

  return (
    <div
      role="progressbar"
      aria-valuenow={Math.round(progress)}
      aria-valuemin={0}
      aria-valuemax={100}
      className="wsms-w-full wsms-h-2 wsms-rounded-full wsms-bg-muted wsms-overflow-hidden"
    >
      <div
        className="wsms-h-full wsms-bg-primary motion-safe:wsms-transition-[width] motion-safe:wsms-duration-200 motion-safe:wsms-ease-out"
        style={{ width: `${progress}%` }}
      />
    </div>
  )
}
