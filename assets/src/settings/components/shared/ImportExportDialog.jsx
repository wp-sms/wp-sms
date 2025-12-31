import * as React from 'react'
import { Upload, Download, FileText, AlertCircle, CheckCircle, Loader2, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
} from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/**
 * ImportExportDialog - Modal for CSV import and export operations
 */
const ImportExportDialog = ({
  open,
  onOpenChange,
  mode = 'import', // 'import' | 'export'
  title,
  description,
  onImport,
  onExport,
  exportOptions = [],
  importFields = [],
  isLoading = false,
  sampleCsvUrl,
}) => {
  const [file, setFile] = React.useState(null)
  const [exportFormat, setExportFormat] = React.useState(exportOptions[0]?.value || 'all')
  const [dragActive, setDragActive] = React.useState(false)
  const [preview, setPreview] = React.useState(null)
  const [error, setError] = React.useState('')
  const [success, setSuccess] = React.useState('')
  const fileInputRef = React.useRef(null)

  const handleDrag = (e) => {
    e.preventDefault()
    e.stopPropagation()
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true)
    } else if (e.type === 'dragleave') {
      setDragActive(false)
    }
  }

  const handleDrop = (e) => {
    e.preventDefault()
    e.stopPropagation()
    setDragActive(false)

    const droppedFile = e.dataTransfer.files?.[0]
    if (droppedFile) {
      handleFileSelect(droppedFile)
    }
  }

  const handleFileSelect = (selectedFile) => {
    setError('')
    setSuccess('')

    if (!selectedFile.name.endsWith('.csv')) {
      setError('Please select a CSV file')
      return
    }

    if (selectedFile.size > 5 * 1024 * 1024) {
      setError('File size must be less than 5MB')
      return
    }

    setFile(selectedFile)

    // Preview first few rows
    const reader = new FileReader()
    reader.onload = (e) => {
      const text = e.target.result
      const lines = text.split('\n').slice(0, 6) // First 5 rows + header
      const rows = lines.map((line) => line.split(',').map((cell) => cell.trim()))
      setPreview(rows)
    }
    reader.readAsText(selectedFile)
  }

  const handleImport = async () => {
    if (!file) return

    try {
      setError('')
      await onImport?.(file)
      setSuccess('Import completed successfully')
      setFile(null)
      setPreview(null)
    } catch (err) {
      setError(err.message || 'Import failed')
    }
  }

  const handleExport = async () => {
    try {
      setError('')
      await onExport?.(exportFormat)
      setSuccess('Export completed successfully')
    } catch (err) {
      setError(err.message || 'Export failed')
    }
  }

  const handleClose = () => {
    setFile(null)
    setPreview(null)
    setError('')
    setSuccess('')
    onOpenChange?.(false)
  }

  const clearFile = () => {
    setFile(null)
    setPreview(null)
    setError('')
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent size="lg">
        <DialogHeader>
          <DialogTitle>
            {title || (mode === 'import' ? 'Import Data' : 'Export Data')}
          </DialogTitle>
          <DialogDescription>
            {description ||
              (mode === 'import'
                ? 'Upload a CSV file to import data'
                : 'Export your data to a CSV file')}
          </DialogDescription>
        </DialogHeader>

        <DialogBody>
          {/* Import Mode */}
          {mode === 'import' && (
            <div className="wsms-space-y-4">
              {/* Drop Zone */}
              <div
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
                onClick={() => fileInputRef.current?.click()}
                className={cn(
                  'wsms-relative wsms-border-2 wsms-border-dashed wsms-rounded-lg wsms-p-8',
                  'wsms-text-center wsms-cursor-pointer wsms-transition-colors',
                  dragActive
                    ? 'wsms-border-primary wsms-bg-primary/5'
                    : 'wsms-border-border hover:wsms-border-primary/50 hover:wsms-bg-muted/30'
                )}
              >
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".csv"
                  onChange={(e) => handleFileSelect(e.target.files?.[0])}
                  className="wsms-hidden"
                />

                {file ? (
                  <div className="wsms-flex wsms-items-center wsms-justify-center wsms-gap-3">
                    <FileText className="wsms-h-8 wsms-w-8 wsms-text-primary" />
                    <div className="wsms-text-left">
                      <p className="wsms-text-[13px] wsms-font-medium">{file.name}</p>
                      <p className="wsms-text-[11px] wsms-text-muted-foreground">
                        {(file.size / 1024).toFixed(1)} KB
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={(e) => {
                        e.stopPropagation()
                        clearFile()
                      }}
                    >
                      <X className="wsms-h-4 wsms-w-4" />
                    </Button>
                  </div>
                ) : (
                  <>
                    <Upload className="wsms-h-10 wsms-w-10 wsms-mx-auto wsms-text-muted-foreground wsms-mb-3" />
                    <p className="wsms-text-[13px] wsms-font-medium">
                      Drop your CSV file here or click to browse
                    </p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-1">
                      Maximum file size: 5MB
                    </p>
                  </>
                )}
              </div>

              {/* Expected fields */}
              {importFields.length > 0 && (
                <div className="wsms-p-3 wsms-rounded-md wsms-bg-muted/30 wsms-border wsms-border-border">
                  <p className="wsms-text-[12px] wsms-font-medium wsms-mb-2">Expected CSV columns:</p>
                  <div className="wsms-flex wsms-flex-wrap wsms-gap-1.5">
                    {importFields.map((field) => (
                      <span
                        key={field.name}
                        className={cn(
                          'wsms-px-2 wsms-py-0.5 wsms-rounded wsms-text-[11px]',
                          field.required
                            ? 'wsms-bg-primary/10 wsms-text-primary'
                            : 'wsms-bg-muted wsms-text-muted-foreground'
                        )}
                      >
                        {field.label}
                        {field.required && ' *'}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {/* Preview */}
              {preview && preview.length > 0 && (
                <div className="wsms-overflow-x-auto wsms-rounded-md wsms-border wsms-border-border">
                  <table className="wsms-w-full wsms-text-[11px]">
                    <thead className="wsms-bg-muted/50">
                      <tr>
                        {preview[0]?.map((header, i) => (
                          <th
                            key={i}
                            className="wsms-px-3 wsms-py-2 wsms-text-left wsms-font-medium wsms-text-muted-foreground"
                          >
                            {header}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {preview.slice(1).map((row, i) => (
                        <tr key={i} className="wsms-border-t wsms-border-border">
                          {row.map((cell, j) => (
                            <td key={j} className="wsms-px-3 wsms-py-2 wsms-text-foreground">
                              {cell || '-'}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  <div className="wsms-px-3 wsms-py-2 wsms-text-[10px] wsms-text-muted-foreground wsms-bg-muted/30 wsms-border-t wsms-border-border">
                    Showing first {preview.length - 1} rows
                  </div>
                </div>
              )}

              {/* Sample CSV link */}
              {sampleCsvUrl && (
                <a
                  href={sampleCsvUrl}
                  download
                  className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[12px] wsms-text-primary hover:wsms-underline"
                >
                  <Download className="wsms-h-3.5 wsms-w-3.5" />
                  Download sample CSV template
                </a>
              )}
            </div>
          )}

          {/* Export Mode */}
          {mode === 'export' && (
            <div className="wsms-space-y-4">
              {exportOptions.length > 0 && (
                <div className="wsms-space-y-2">
                  <label className="wsms-text-[12px] wsms-font-medium">Export Format</label>
                  <Select value={exportFormat} onValueChange={setExportFormat}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {exportOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}

              <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/30 wsms-border wsms-border-border wsms-text-center">
                <Download className="wsms-h-10 wsms-w-10 wsms-mx-auto wsms-text-muted-foreground wsms-mb-3" />
                <p className="wsms-text-[13px]">
                  Click export to download your data as a CSV file
                </p>
              </div>
            </div>
          )}

          {/* Error message */}
          {error && (
            <div className="wsms-flex wsms-items-start wsms-gap-2 wsms-p-3 wsms-rounded-md wsms-bg-red-500/10 wsms-border wsms-border-red-500/20">
              <AlertCircle className="wsms-h-4 wsms-w-4 wsms-text-red-600 wsms-shrink-0 wsms-mt-0.5" />
              <p className="wsms-text-[12px] wsms-text-red-700 dark:wsms-text-red-400">{error}</p>
            </div>
          )}

          {/* Success message */}
          {success && (
            <div className="wsms-flex wsms-items-start wsms-gap-2 wsms-p-3 wsms-rounded-md wsms-bg-emerald-500/10 wsms-border wsms-border-emerald-500/20">
              <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-emerald-600 wsms-shrink-0 wsms-mt-0.5" />
              <p className="wsms-text-[12px] wsms-text-emerald-700 dark:wsms-text-emerald-400">
                {success}
              </p>
            </div>
          )}
        </DialogBody>

        <DialogFooter>
          <Button variant="outline" onClick={handleClose} disabled={isLoading}>
            Cancel
          </Button>
          {mode === 'import' ? (
            <Button onClick={handleImport} disabled={isLoading || !file}>
              {isLoading ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Importing...
                </>
              ) : (
                <>
                  <Upload className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  Import
                </>
              )}
            </Button>
          ) : (
            <Button onClick={handleExport} disabled={isLoading}>
              {isLoading ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Exporting...
                </>
              ) : (
                <>
                  <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  Export
                </>
              )}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export { ImportExportDialog }
