import axios from 'axios'

import { WordPressDataService } from './data-service'

const dataService = WordPressDataService.getInstance()

const BASE_URL = dataService.getRestUrl()
const HEADERS = dataService.getHeaders()

/**
 * Creates an Axios instance with a predefined base URL.
 */

const instance = axios.create({
  baseURL: BASE_URL,
})

/**
 * Axios request interceptor.
 * - Removes empty query parameters (`""` or `undefined` values).
 * - Applies throttling for `POST` and `PUT` requests.
 */
instance.interceptors.request.use(
  async (request) => {
    Object.assign(request.headers, HEADERS)

    if (request.params && Object.keys(request.params).length) {
      for (const key of Object.keys(request.params)) {
        if (request.params[key] === '' || request.params[key] === undefined) {
          delete request.params[key]
        }
      }
    }

    return request
  },
  (error) => {
    return Promise.reject(error)
  }
)

/**
 * Axios response interceptor.
 * - Directly returns the response.
 * - Handles errors by forwarding them for centralized error handling.
 */
instance.interceptors.response.use(
  (response) => response,
  async (error) => {
    return Promise.reject(error)
  }
)

export { instance as clientRequest }
