// resources/js/bootstrap.js

import axios from 'axios'

window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
window.axios.defaults.withCredentials = true
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN'
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN'

// ВРЕМЕННО: без Echo, чтобы не ломать Inertia на /login
// WebSocket-логику подключим отдельно в Messenger.vue
