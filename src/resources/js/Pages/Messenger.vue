<script setup>
import axios from 'axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()
const viewer = computed(() => page.props.auth?.user ?? { name: 'Гость', email: 'user@example.com' })

const profile = reactive({
    nickname: viewer.value.nickname || viewer.value.name || 'Без ника',
    email: viewer.value.email,
    showEmail: !viewer.value.email_hidden,
    avatar: viewer.value.avatar_path
        ? `/storage/${viewer.value.avatar_path}`
        : '',
})

const nicknameError = ref('')

const contacts = ref([])
const searchPool = reactive({ term: '', results: [], loading: false, error: '' })

const chats = ref([])

const activeChatId = ref('')
const messageDraft = ref('')
const editingMessageId = ref(null)
const forwardMode = reactive({ visible: false, messageId: null, targetId: null })
const groupComposer = reactive({ visible: false, name: '', members: [] })
const participantPicker = reactive({ visible: false, chatId: '' })
const messageMenu = reactive({ open: false, x: 0, y: 0, messageId: null })
const chatMenu = reactive({ open: false, x: 0, y: 0, chatId: null })

const loading = reactive({ contacts: false, chats: false, messages: false, syncing: false })
const echo = ref(null)
const channelLinks = new Map()
let pollTimer = null

const alertSound = typeof Audio !== 'undefined'
    ? new Audio(
        'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YQgAAAAA//8AAP//AAD//wAA//8AAP//AAD//wAA',
    )
    : null

const nowClock = () => new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
const formatTime = (value) => value
    ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : nowClock()

const activeChat = computed(() =>
    chats.value.find((chat) => chat.id === activeChatId.value) ?? {
        id: '',
        title: 'Выберите чат',
        isGroup: false,
        participants: [],
        muted: false,
        unread: 0,
        messages: [],
    },
)

const visibleMessages = computed(() => activeChat.value.messages)

const availableContacts = computed(() => contacts.value.map((contact) => ({
    ...contact,
    label: contact.nickname || contact.email,
    visibleEmail: contact.hiddenEmail ? (profile.showEmail ? contact.email : 'скрыт') : contact.email,
})))

const chatMenuMuted = computed(() => {
    const chat = findChat(chatMenu.chatId)
    return chat ? chat.muted : false
})

const nicknameTaken = (candidate) =>
    contacts.value.some((contact) => contact.nickname.toLowerCase() === candidate.toLowerCase())

const findChat = (id) => chats.value.find((item) => item.id === id)
const lastMessageText = (chat) => {
    const last = chat.messages[chat.messages.length - 1]
    return last ? last.text : 'Нет сообщений'
}
const lastMessageTime = (chat) => {
    const last = chat.messages[chat.messages.length - 1]
    return last ? last.time : ''
}

const closeMenus = () => {
    messageMenu.open = false
    chatMenu.open = false
}

const selectChat = (id) => {
    const chat = findChat(id)
    if (!chat) return
    activeChatId.value = id
    chat.unread = 0
    closeMenus()
    hydrateMessages(id)
    subscribeToChat(id)
}

const persistProfile = () => {
    nicknameError.value = ''
        if (!profile.nickname.trim()) {
        nicknameError.value = 'Ник не может быть пустым.'
        return
    }
    if (nicknameTaken(profile.nickname.trim())) {
        nicknameError.value = 'Такой ник уже занят контактом.'
        return
    }
}

const ensureMessageOwner = (message) => message.author === 'me'

const buildAuthorLabel = (user) =>
    user?.nickname || user?.name || user?.email || (user?.id ? `user#${user.id}` : 'Собеседник')

const normalizeMessage = (payload) => {
    const createdAt = payload.created_at ? new Date(payload.created_at) : new Date()
    const updatedAt = payload.updated_at ? new Date(payload.updated_at) : createdAt

    const selfAuthored = payload.user_id === viewer.value.id
    const authorLabel = selfAuthored
        ? (profile.nickname || profile.email || buildAuthorLabel(payload.user))
        : buildAuthorLabel(payload.user)

    return {
        id: payload.id,
        userId: payload.user_id,
        author: selfAuthored ? 'me' : 'peer',
        authorLabel,
        text: payload.body,
        time: formatTime(updatedAt),
        edited: Boolean(payload.edited_at) || updatedAt.getTime() !== createdAt.getTime(),
        forwardedFrom: payload.forwarded_from_message_id ? 'переслано' : null,
        createdAt,
    }
}

const normalizeMessageList = (items) =>
    items
        .map((item) => normalizeMessage(item))
        .sort((a, b) => a.createdAt.getTime() - b.createdAt.getTime())

const resolveChatTitle = (raw) => {
    if (raw.type === 'group') {
        return raw.title || 'Групповой чат'
    }

    const peer = (raw.users || []).find((user) => user.id !== viewer.value.id) || (raw.users || [])[0]
    return buildAuthorLabel(peer)
}

const inflateChat = (raw) => {
    const myPivot = (raw.users || []).find((user) => user.id === viewer.value.id)?.pivot

    return {
        id: raw.id,
        title: resolveChatTitle(raw),
        isGroup: raw.type === 'group',
        participants: (raw.users || []).map((user) => buildAuthorLabel(user)),
        memberIds: (raw.users || []).map((user) => user.id),
        muted: Boolean(myPivot?.muted ?? raw.muted_by_default),
        unread: 0,
        messages: normalizeMessageList(raw.messages || []),
    }
}

const findDirectChatWith = (userId) =>
    chats.value.find((chat) => !chat.isGroup && chat.memberIds.includes(userId))

const attachMessage = (chatId, payload, { selfAuthored = false } = {}) => {
    const chat = findChat(chatId)
    if (!chat) return

    const normalized = normalizeMessage(payload)
    const idx = chat.messages.findIndex((item) => item.id === normalized.id)

    if (idx === -1) {
        chat.messages.push(normalized)
    } else {
        chat.messages[idx] = { ...chat.messages[idx], ...normalized }
    }

    chat.messages.sort((a, b) => a.createdAt.getTime() - b.createdAt.getTime())

    if (!selfAuthored && chat.id !== activeChatId.value) {
        chat.unread += 1
    }

    if (!selfAuthored && !chat.muted) {
        playSound()
    }
}

const applyEdit = (chatId, payload) => {
    const chat = findChat(chatId)
    if (!chat) return

    const idx = chat.messages.findIndex((item) => item.id === payload.id)
    if (idx === -1) return

    chat.messages[idx] = { ...chat.messages[idx], text: payload.body, edited: true, time: formatTime(payload.updated_at) }
}

const applyDelete = (chatId, messageId) => {
    const chat = findChat(chatId)
    if (!chat) return

    const idx = chat.messages.findIndex((item) => item.id === messageId)
    if (idx === -1) return

    chat.messages.splice(idx, 1)
}

const playSound = () => {
    if (!alertSound) return
    alertSound.currentTime = 0
    alertSound.play().catch(() => {})
}

const submitMessage = async () => {
    const chat = activeChat.value
    if (!chat) return
    const trimmed = messageDraft.value.trim()
    if (!trimmed || loading.syncing) return

    loading.syncing = true

    if (editingMessageId.value) {
        const target = chat.messages.find((m) => m.id === editingMessageId.value)
        if (target && ensureMessageOwner(target)) {
            try {
                const response = await axios.patch(`/api/messages/${editingMessageId.value}`, { body: trimmed })
                attachMessage(chat.id, response.data, { selfAuthored: true })
            } finally {
                editingMessageId.value = null
                messageDraft.value = ''
                loading.syncing = false
            }
            return
        }
    }

    try {
        const response = await axios.post(`/api/messages/${chat.id}`, { body: trimmed })
        attachMessage(chat.id, response.data, { selfAuthored: true })
        messageDraft.value = ''
    } finally {
        loading.syncing = false
    }
}

const openMessageMenu = (event, message) => {
    if (!ensureMessageOwner(message)) return
    event.preventDefault()
    messageMenu.open = true
    messageMenu.x = event.clientX
    messageMenu.y = event.clientY
    messageMenu.messageId = message.id
}

const editCurrentMessage = () => {
    const chat = activeChat.value
    if (!chat) return
    const target = chat.messages.find((m) => m.id === messageMenu.messageId)
    if (target && ensureMessageOwner(target)) {
        messageDraft.value = target.text
        editingMessageId.value = target.id
    }
    closeMenus()
}

const deleteCurrentMessage = () => {
    const chat = activeChat.value
    if (!chat) return
    const targetId = messageMenu.messageId
    closeMenus()

    if (!targetId) return

    axios.delete(`/api/messages/${targetId}`).catch(() => {})
    applyDelete(chat.id, targetId)
}

const startForward = () => {
    forwardMode.visible = true
    forwardMode.messageId = messageMenu.messageId
    forwardMode.targetId = null
    closeMenus()
}

const commitForward = async () => {
    const sourceChat = activeChat.value
    if (!sourceChat) return
    const destination = chats.value.find((chat) => chat.id === forwardMode.targetId)
    if (!destination) {
        forwardMode.visible = false
        return
    }

    try {
        const response = await axios.post(`/api/messages/${forwardMode.messageId}/forward`, {
            target_chat_id: destination.id,
        })
        attachMessage(destination.id, response.data, { selfAuthored: true })
    } finally {
        forwardMode.visible = false
    }
}

const toggleMute = async (chatId) => {
    const chat = chats.value.find((item) => item.id === chatId)
    if (!chat) return

    chat.muted = !chat.muted
    closeMenus()

    try {
        await axios.patch(`/api/chats/${chatId}/mute`)
    } catch (_) {
        chat.muted = !chat.muted
    }
}

const openChatMenu = (event, chatId) => {
    event.preventDefault()
    chatMenu.open = true
    chatMenu.x = event.clientX
    chatMenu.y = event.clientY
    chatMenu.chatId = chatId
}

const openGroupComposer = () => {
    groupComposer.visible = true
    groupComposer.name = ''
    groupComposer.members = []
}

const openParticipantPicker = () => {
    if (!activeChat.value.isGroup) return

    participantPicker.visible = true
    participantPicker.chatId = activeChat.value.id
    searchPool.term = ''
    searchPool.results = []
    searchPool.error = ''
}

const createGroupChat = async () => {
    if (!groupComposer.name.trim() || groupComposer.members.length === 0) return

    const payload = {
        type: 'group',
        title: groupComposer.name.trim(),
        participants: [...groupComposer.members],
    }

    const response = await axios.post('/api/chats', payload)
    const hydrated = inflateChat(response.data)

    chats.value.unshift(hydrated)
    activeChatId.value = hydrated.id
    groupComposer.visible = false

    subscribeToChat(hydrated.id)
}

const handleGlobalClick = () => closeMenus()

const hydrateContacts = async () => {
    loading.contacts = true
    try {
        const response = await axios.get('/api/contacts')
        contacts.value = response.data.map((item) => {
            const user = item.contact_user || item.contactUser || item.user
            return {
                id: user?.id,
                nickname: user?.nickname || user?.name || 'Без имени',
                email: user?.email,
                hiddenEmail: user?.email_hidden,
                avatar: user?.avatar_path
                    ? `/storage/${user.avatar_path}`
                    : `https://api.dicebear.com/7.x/identicon/svg?seed=${encodeURIComponent(user?.nickname || user?.email || 'contact')}`,
            }
        })
    } finally {
        loading.contacts = false
    }
}

const searchUsers = async () => {
    searchPool.error = ''
    const query = searchPool.term.trim()
    if (!query) {
        searchPool.results = []
        return
    }

    searchPool.loading = true
    try {
        const response = await axios.get('/api/users/search', { params: { query } })
        searchPool.results = response.data
    } catch (_) {
        searchPool.error = 'Не удалось выполнить поиск.'
    } finally {
        searchPool.loading = false
    }
}

const addContact = async (userId) => {
    await axios.post('/api/contacts', { user_id: userId })
    await hydrateContacts()
    searchPool.results = searchPool.results.map((item) =>
        item.id === userId ? { ...item, in_contacts: true } : item,
    )
}

const startDirectWith = async (userId) => {
    const existing = findDirectChatWith(userId)
    if (existing) {
        selectChat(existing.id)
        return
    }

    const response = await axios.post('/api/chats', { type: 'direct', participants: [userId] })
    const hydrated = inflateChat(response.data)
    chats.value.unshift(hydrated)
    selectChat(hydrated.id)
    subscribeToChat(hydrated.id)
}

const isUserInChat = (chatId, userId) => {
    const chat = findChat(chatId)
    if (!chat) return false

    return chat.memberIds.includes(userId)
}

const addParticipantToChat = async (user) => {
    const chatId = participantPicker.chatId
    if (!chatId || !user?.id || isUserInChat(chatId, user.id)) return

    await axios.post(`/api/chats/${chatId}/participants`, { user_id: user.id })

    const chat = findChat(chatId)
    if (!chat) return

    chat.memberIds.push(user.id)
    chat.participants.push(buildAuthorLabel(user))
}

const hydrateChats = async () => {
    loading.chats = true
    try {
        const response = await axios.get('/api/chats')
        chats.value = response.data.map((chat) => inflateChat(chat))
        activeChatId.value = chats.value[0]?.id ?? ''
    } finally {
        loading.chats = false
    }
}

const hydrateMessages = async (chatId) => {
    if (!chatId) return
    loading.messages = true
    try {
        const response = await axios.get(`/api/messages/${chatId}`)
        const payload = Array.isArray(response.data) ? response.data : response.data.data ?? []
        const chat = findChat(chatId)
        if (!chat) return

        chat.messages = normalizeMessageList(payload)
    } finally {
        loading.messages = false
    }
}

const connectRealtime = () => {
    if (echo.value || !import.meta.env.VITE_REVERB_APP_KEY) return

    window.Pusher = Pusher

    echo.value = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios
                    .post('/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name,
                    }, { withCredentials: true })
                    .then((response) => {
                        callback(false, response.data)
                    })
                    .catch((error) => {
                        callback(true, error)
                    })
            },
        }),
    })
    
    const connection = echo.value.connector.pusher.connection

    connection.bind('connected', stopPolling)
    connection.bind('unavailable', startPolling)
    connection.bind('failed', startPolling)
    connection.bind('disconnected', startPolling)
    connection.bind('error', startPolling)
}

const subscribeToChat = (chatId) => {
    if (!echo.value || channelLinks.has(chatId)) return

    const channel = echo.value.private(`private-chat.${chatId}`)
        .listen('MessageSent', (payload) => attachMessage(chatId, payload))
        .listen('MessageEdited', (payload) => applyEdit(chatId, payload))
        .listen('MessageDeleted', (payload) => applyDelete(chatId, payload.message_id))

    channelLinks.set(chatId, channel)
}

const startPolling = () => {
    if (pollTimer) return

    pollTimer = setInterval(() => {
        if (activeChatId.value) {
            hydrateMessages(activeChatId.value)
        }
    }, 8000)
}

const stopPolling = () => {
    if (!pollTimer) return

    clearInterval(pollTimer)
    pollTimer = null
}

const stopRealtime = () => {
    if (echo.value) {
        channelLinks.forEach((_, chatId) => echo.value.leave(`private-chat.${chatId}`))
        channelLinks.clear()
        echo.value.disconnect()
        echo.value = null
    }

    stopPolling()
}

const initData = async () => {
    await Promise.all([hydrateContacts(), hydrateChats()])
    connectRealtime()
    chats.value.forEach((chat) => subscribeToChat(chat.id))
    startPolling()
}

onMounted(() => {
    window.addEventListener('click', handleGlobalClick)
    initData()
})

onBeforeUnmount(() => {
    window.removeEventListener('click', handleGlobalClick)
    stopRealtime()
})
</script>


<template>
    <Head title="Messenger" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Мессенджер
                    </h2>
                    <p class="text-sm text-gray-500">
                        Асинхронное общение с редактированием, пересылкой и бесшумным режимом.
                    </p>
                </div>
                <button
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
                    @click="openGroupComposer"
                >
                    Новый групповой чат
                </button>
            </div>
        </template>

        <div class="bg-gray-50 py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:grid-cols-12 sm:px-6 lg:px-8">
                <section class="space-y-4 sm:col-span-4">
                    <div class="rounded-lg bg-white p-4 shadow">
                        <div class="flex items-center space-x-4">
                            <img
                                :src="profile.avatar || 'https://api.dicebear.com/7.x/identicon/svg?seed=default'"
                                alt="avatar"
                                class="h-12 w-12 rounded-full border"
                            >
                            <div>
                                <div class="text-lg font-semibold text-gray-800">{{ profile.nickname }}</div>
                                <div class="text-sm text-gray-500">{{ profile.showEmail ? profile.email : 'Email скрыт' }}</div>
                            </div>
                        </div>
                        <div class="mt-4 space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Ник</label>
                            <input
                                v-model="profile.nickname"
                                @blur="persistProfile"
                                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <p v-if="nicknameError" class="text-sm text-red-500">{{ nicknameError }}</p>

                            <label class="block text-sm font-medium text-gray-700">Аватар (URL)</label>
                            <input
                                v-model="profile.avatar"
                                placeholder="https://..."
                                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">Показывать email</span>
                                <button
                                    class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700"
                                    @click="profile.showEmail = !profile.showEmail"
                                >
                                    {{ profile.showEmail ? 'Скрыть' : 'Показать' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Контакты</h3>
                            <span class="text-xs text-gray-500">{{ availableContacts.length }} всего</span>
                        </div>
                        <div class="space-y-3">
                            <div class="space-y-2 rounded border p-2">
                                <div class="flex space-x-2">
                                    <input
                                        v-model="searchPool.term"
                                        @keyup.enter="searchUsers"
                                        class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Email или никнейм"
                                    >
                                    <button
                                        class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
                                        @click="searchUsers"
                                    >
                                        {{ searchPool.loading ? 'Поиск...' : 'Искать' }}
                                    </button>
                                </div>
                                <p v-if="searchPool.error" class="text-xs text-red-500">{{ searchPool.error }}</p>
                                <div v-if="searchPool.results.length" class="space-y-2">
                                    <div
                                        v-for="user in searchPool.results"
                                        :key="user.id"
                                        class="flex items-center justify-between rounded border p-2 text-sm"
                                    >
                                        <div>
                                            <div class="font-semibold text-gray-800">{{ user.nickname || 'Без имени' }}</div>
                                            <div class="text-xs text-gray-500">{{ user.email || 'Email скрыт' }}</div>
                                        </div>
                                        <button
                                            class="rounded px-3 py-1 text-xs font-semibold"
                                            :class="user.in_contacts ? 'bg-gray-200 text-gray-600' : 'bg-indigo-100 text-indigo-700'"
                                            :disabled="user.in_contacts"
                                            @click="addContact(user.id)"
                                        >
                                            {{ user.in_contacts ? 'В контактах' : 'Добавить' }}
                                        </button>
                                    </div>
                                </div>
                                <p v-else-if="searchPool.term" class="text-[11px] text-gray-500">Никого не найдено.</p>
                                <p v-else class="text-[11px] text-gray-500">Введите запрос, чтобы найти пользователя.</p>
                            </div>
                            <div
                                v-for="contact in availableContacts"
                                :key="contact.id"
                                class="flex items-center justify-between rounded border p-2 hover:border-indigo-400"
                            >
                                <div class="flex items-center space-x-3">
                                    <img :src="contact.avatar" class="h-8 w-8 rounded-full" alt="avatar">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-800">{{ contact.label }}</div>
                                        <div class="text-xs text-gray-500">{{ contact.visibleEmail }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button
                                        class="rounded bg-indigo-600 px-3 py-1 text-[10px] font-semibold text-white hover:bg-indigo-500"
                                        @click="startDirectWith(contact.id)"
                                    >
                                        Написать
                                    </button>
                                    <span class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-semibold text-indigo-700">в сети</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="sm:col-span-8">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="rounded-lg bg-white shadow lg:col-span-1">
                            <div class="border-b px-4 py-3">
                                <h3 class="text-sm font-semibold text-gray-700">Чаты</h3>
                            </div>
                            <div class="divide-y max-h-[520px] overflow-y-auto">
                                <button
                                    v-for="chat in chats"
                                    :key="chat.id"
                                    class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-indigo-50"
                                    :class="{ 'bg-indigo-50': chat.id === activeChatId }"
                                    @click="selectChat(chat.id)"
                                    @contextmenu="openChatMenu($event, chat.id)"
                                >
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-semibold text-gray-800">{{ chat.title }}</span>
                                            <span v-if="chat.isGroup" class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">группа</span>
                                            <span v-if="chat.muted" class="text-[10px] text-gray-500">🔕</span>
                                        </div>
                                        <div class="text-xs text-gray-500">{{ lastMessageText(chat) }}</div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="chat.unread" class="rounded-full bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white">{{ chat.unread }}</span>
                                        <span class="text-[10px] text-gray-400">{{ lastMessageTime(chat) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-lg bg-white shadow lg:col-span-2">
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">{{ activeChat.title }}</div>
                                    <div class="text-xs text-gray-500">{{ activeChat.isGroup ? 'Групповое общение' : 'Личные сообщения' }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button
                                        v-if="activeChat.isGroup"
                                        class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"
                                        @click="openParticipantPicker"
                                    >
                                        Добавить участника
                                    </button>
                                    <button
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="activeChat.muted ? 'bg-gray-200 text-gray-700' : 'bg-indigo-100 text-indigo-700'"
                                        @click="activeChat.id && toggleMute(activeChat.id)"
                                    >
                                        {{ activeChat.muted ? 'Включить звук' : 'Без звука' }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex h-[460px] flex-col">
                                <div class="flex-1 space-y-3 overflow-y-auto bg-gray-50 px-4 py-3">
                                    <div
                                        v-for="message in visibleMessages"
                                        :key="message.id"
                                        class="flex"
                                        :class="message.author === 'me' ? 'justify-end' : 'justify-start'"
                                        @contextmenu="message.author === 'me' && openMessageMenu($event, message)"
                                    >
                                        <div
                                            class="max-w-[80%] rounded-lg px-3 py-2 text-sm shadow"
                                            :class="message.author === 'me' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800'"
                                        >
                                            <div
                                                class="mb-1 text-[11px] font-semibold"
                                                :class="message.author === 'me' ? 'text-indigo-100 text-right' : 'text-indigo-700'"
                                            >
                                                {{ message.authorLabel }}
                                            </div>
                                            <div v-if="message.forwardedFrom" class="text-[10px] uppercase tracking-wide text-gray-200">Переслано из {{ message.forwardedFrom }}</div>
                                            <div>{{ message.text }}</div>
                                            <div class="mt-1 flex items-center justify-end space-x-2 text-[10px] opacity-80">
                                                <span>{{ message.time }}</span>
                                                <span v-if="message.edited">(изменено)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t px-4 py-3">
                                    <textarea
                                        v-model="messageDraft"
                                        class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows="2"
                                        placeholder="Введите сообщение"
                                    ></textarea>
                                    <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                                        <div>
                                            <span v-if="editingMessageId" class="text-indigo-600">Редактирование сообщения</span>
                                        </div>
                                        <button
                                            class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-500"
                                            @click="submitMessage"
                                        >
                                            {{ editingMessageId ? 'Сохранить' : 'Отправить' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-if="messageMenu.open"
            class="fixed z-50 rounded-md bg-white shadow-lg"
            :style="{ left: `${messageMenu.x}px`, top: `${messageMenu.y}px` }"
        >
            <button class="block w-full px-4 py-2 text-left text-sm hover:bg-indigo-50" @click="editCurrentMessage">Редактировать</button>
            <button class="block w-full px-4 py-2 text-left text-sm hover:bg-indigo-50" @click="deleteCurrentMessage">Удалить</button>
            <button class="block w-full px-4 py-2 text-left text-sm hover:bg-indigo-50" @click="startForward">Переслать</button>
        </div>

        <div
            v-if="chatMenu.open"
            class="fixed z-50 rounded-md bg-white shadow-lg"
            :style="{ left: `${chatMenu.x}px`, top: `${chatMenu.y}px` }"
        >
            <button class="block w-full px-4 py-2 text-left text-sm hover:bg-indigo-50" @click="toggleMute(chatMenu.chatId)">
                {{ chatMenuMuted ? 'Включить оповещения' : 'Отключить оповещения' }}
            </button>
        </div>

        <div
            v-if="forwardMode.visible"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/30 px-4"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-4 shadow-lg">
                <h3 class="text-sm font-semibold text-gray-800">Переслать сообщение</h3>
                <p class="text-xs text-gray-500">Выберите чат из списка контактов.</p>
                <div class="mt-3 space-y-2 max-h-64 overflow-y-auto">
                    <label
                        v-for="chat in chats"
                        :key="chat.id"
                        class="flex cursor-pointer items-center space-x-2 rounded border px-3 py-2 text-sm hover:border-indigo-400"
                    >
                        <input
                            type="radio"
                            name="forwardTarget"
                            class="text-indigo-600"
                            :value="chat.id"
                            v-model="forwardMode.targetId"
                        >
                        <span>{{ chat.title }}</span>
                        <span v-if="chat.isGroup" class="text-[10px] text-indigo-600">группа</span>
                    </label>
                </div>
                <div class="mt-4 flex justify-end space-x-2 text-sm">
                    <button class="rounded px-3 py-1 text-gray-600 hover:bg-gray-100" @click="forwardMode.visible = false">Отмена</button>
                    <button class="rounded bg-indigo-600 px-3 py-1 font-semibold text-white hover:bg-indigo-500" @click="commitForward">Переслать</button>
                </div>
            </div>
        </div>

        <div
            v-if="participantPicker.visible"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/30 px-4"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-4 shadow-lg">
                <h3 class="text-sm font-semibold text-gray-800">Добавить участников в группу</h3>
                <p class="text-xs text-gray-500">Поиск работает так же, как в разделе контактов.</p>

                <div class="mt-3 space-y-3">
                    <div class="space-y-2 rounded border p-2">
                        <div class="flex space-x-2">
                            <input
                                v-model="searchPool.term"
                                @keyup.enter="searchUsers"
                                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Email или никнейм"
                            >
                            <button
                                class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
                                @click="searchUsers"
                            >
                                {{ searchPool.loading ? 'Поиск...' : 'Искать' }}
                            </button>
                        </div>
                        <p v-if="searchPool.error" class="text-xs text-red-500">{{ searchPool.error }}</p>
                        <div v-if="searchPool.results.length" class="space-y-2 max-h-64 overflow-y-auto">
                            <div
                                v-for="user in searchPool.results"
                                :key="user.id"
                                class="flex items-center justify-between rounded border p-2 text-sm"
                            >
                                <div>
                                    <div class="font-semibold text-gray-800">{{ user.nickname || 'Без имени' }}</div>
                                    <div class="text-xs text-gray-500">{{ user.email || 'Email скрыт' }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span
                                        v-if="!user.in_contacts"
                                        class="text-[10px] font-semibold text-red-500"
                                    >
                                        Нет в контактах
                                    </span>
                                    <button
                                        class="rounded px-3 py-1 text-xs font-semibold"
                                        :class="isUserInChat(participantPicker.chatId, user.id)
                                            ? 'bg-gray-200 text-gray-600'
                                            : 'bg-indigo-100 text-indigo-700'"
                                        :disabled="!user.in_contacts || isUserInChat(participantPicker.chatId, user.id)"
                                        @click="addParticipantToChat(user)"
                                    >
                                        {{ isUserInChat(participantPicker.chatId, user.id) ? 'Уже в чате' : 'Добавить' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else-if="searchPool.term" class="text-[11px] text-gray-500">Никого не найдено.</p>
                        <p v-else class="text-[11px] text-gray-500">Введите запрос, чтобы найти пользователя.</p>
                    </div>
                </div>

                <div class="mt-4 flex justify-end space-x-2 text-sm">
                    <button class="rounded px-3 py-1 text-gray-600 hover:bg-gray-100" @click="participantPicker.visible = false">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="groupComposer.visible"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/30 px-4"
        >
            <div class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-800">Создать групповой чат</h3>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Название</label>
                        <input
                            v-model="groupComposer.name"
                            class="mt-1 w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Например, Общий чат"
                        >
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-700">Участники</div>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label
                                v-for="contact in availableContacts"
                                :key="contact.id"
                                class="flex cursor-pointer items-center space-x-2 rounded border px-3 py-2 text-sm hover:border-indigo-400"
                            >
                                <input
                                    type="checkbox"
                                    class="text-indigo-600"
                                    :value="contact.id"
                                    v-model="groupComposer.members"
                                >
                                <span>{{ contact.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-2 text-sm">
                    <button class="rounded px-3 py-1 text-gray-600 hover:bg-gray-100" @click="groupComposer.visible = false">Отмена</button>
                    <button
                        class="rounded bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-500"
                        @click="createGroupChat"
                    >
                        Создать
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>