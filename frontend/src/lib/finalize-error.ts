import type { AppError } from './build-error'

type ErrorMeta = {
  key: string
  message: string
}[]

type FinalizeErorReturn = {
  error: string
  message: string
  statusCode: number
  meta?: ErrorMeta
}

function isAppError(error: unknown): error is AppError {
  return typeof error === 'object' && error !== null && 'statusCode' in error && 'message' in error
}

function isAxiosError(error: unknown): error is { response: { data: any; status: number } } {
  return (
    typeof error === 'object' &&
    error !== null &&
    'response' in error &&
    typeof error.response === 'object' &&
    error.response !== null &&
    'data' in error.response
  )
}

export function finalizeError(error: unknown): FinalizeErorReturn {
  // Handle our custom AppError

  if (isAxiosError(error)) {
    return {
      error: error?.response?.data?.error ?? 'Unknown Error',
      message: error?.response?.data?.message ?? 'Unknown Error',
      statusCode: error?.response?.data?.statusCode ?? 500,
      meta: error?.response?.data?.meta,
    }
  }

  if (isAppError(error)) {
    return {
      error: error?.message ?? 'Unknown Error',
      message: error?.message ?? 'Unknown Error',
      statusCode: error?.statusCode ?? 500,
      meta: error?.meta,
    }
  }

  // Handle generic errors
  if (error instanceof Error) {
    return {
      error: 'Internal Server Error',
      message: error?.message,
      statusCode: 500,
    }
  }

  // Fallback for completely unknown errors
  return {
    error: 'Internal Server Error',
    message: 'Unknown Error',
    statusCode: 500,
  }
}
