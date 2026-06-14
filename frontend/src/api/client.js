import axios from 'axios'

/**
 * Pre-configured Axios instance for the KALT API.
 *
 * - baseURL: /api — proxied to Laravel by Vite (see vite.config.js)
 * - withCredentials: required for Sanctum SPA cookie auth
 * - X-Requested-With: tells Laravel this is an XHR request (affects JSON error responses)
 */
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

/**
 * Ensure a valid CSRF token exists before mutating requests.
 * Sanctum SPA mode requires hitting /sanctum/csrf-cookie first.
 */
let csrfInitialised = false

async function ensureCsrf() {
  if (!csrfInitialised) {
    await api.get('/sanctum/csrf-cookie')
    csrfInitialised = true
  }
}

// Request interceptor: obtain CSRF cookie before POST/PUT/PATCH/DELETE
api.interceptors.request.use(async (config) => {
  const mutating = ['post', 'put', 'patch', 'delete']
  if (mutating.includes(config.method)) {
    await ensureCsrf()
  }
  return config
})

// Response interceptor: on 401 clear state and redirect to login
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Avoid circular import — use a custom event instead
      csrfInitialised = false
      window.dispatchEvent(new CustomEvent('kalt:unauthenticated'))
    }
    return Promise.reject(error)
  }
)

export default api
