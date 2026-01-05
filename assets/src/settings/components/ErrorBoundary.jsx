import React from 'react'
import PropTypes from 'prop-types'
import { AlertTriangle, RefreshCw } from 'lucide-react'
import { Button } from './ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from './ui/card'

/**
 * Error Boundary component for graceful error handling
 *
 * Catches JavaScript errors anywhere in child component tree,
 * logs the error, and displays a fallback UI instead of crashing.
 */
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    }
  }

  static getDerivedStateFromError(error) {
    // Update state so next render shows fallback UI
    return { hasError: true, error }
  }

  componentDidCatch(error, errorInfo) {
    // Log error to console for debugging
    console.error('ErrorBoundary caught an error:', error)
    console.error('Error message:', error?.message)
    console.error('Component stack:', errorInfo?.componentStack)
    console.error('Error info:', errorInfo)

    this.setState({
      error,
      errorInfo,
    })
  }

  handleRetry = () => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    })
  }

  render() {
    if (this.state.hasError) {
      // Render fallback UI
      return (
        <div className="wsms-flex wsms-items-center wsms-justify-center wsms-min-h-[400px] wsms-p-6">
          <Card className="wsms-max-w-md wsms-w-full">
            <CardHeader className="wsms-text-center">
              <div className="wsms-mx-auto wsms-mb-4 wsms-w-12 wsms-h-12 wsms-rounded-full wsms-bg-destructive/10 wsms-flex wsms-items-center wsms-justify-center">
                <AlertTriangle className="wsms-w-6 wsms-h-6 wsms-text-destructive" />
              </div>
              <CardTitle>Something went wrong</CardTitle>
              <CardDescription>
                An error occurred while rendering this section. This has been logged for debugging.
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              {this.state.error && (
                <details className="wsms-bg-muted wsms-p-3 wsms-rounded-md wsms-text-sm" open>
                  <summary className="wsms-cursor-pointer wsms-font-medium wsms-mb-2">
                    Error Details
                  </summary>
                  <pre className="wsms-overflow-auto wsms-max-h-40 wsms-text-xs wsms-text-muted-foreground">
                    {this.state.error.toString()}
                    {this.state.errorInfo?.componentStack}
                  </pre>
                </details>
              )}
              <div className="wsms-flex wsms-justify-center">
                <Button onClick={this.handleRetry} variant="outline">
                  <RefreshCw className="wsms-w-4 wsms-h-4 wsms-mr-2" />
                  Try Again
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )
    }

    return this.props.children
  }
}

ErrorBoundary.propTypes = {
  children: PropTypes.node.isRequired,
}

export default ErrorBoundary
