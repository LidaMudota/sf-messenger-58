// resources/js/bootstrap.js

import axios from 'axios'

window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// ВРЕМЕННО: без Echo, чтобы не ломать Inertia на /login
// WebSocket-логику подключим отдельно в Messenger.vue
