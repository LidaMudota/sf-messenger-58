<template>
    <div class="h-screen flex">
        <!-- Список чатов -->
        <aside class="w-1/3 max-w-xs border-r border-gray-200 overflow-y-auto">
            <div class="p-2 font-semibold border-b flex justify-between items-center">
                <span>Мои чаты</span>
                <button
                    class="px-2 py-1 text-xs rounded bg-green-600 text-white"
                    @click="createChatPrompt"
                >
                    Новый чат
                </button>
            </div>

            <ul>
                <li
                    v-for="chat in chats"
                    :key="chat.id"
                    class="px-3 py-2 cursor-pointer flex items-center justify-between hover:bg-gray-100"
                    :class="{
                        'bg-gray-200': activeChat && activeChat.id === chat.id,
                        'opacity-60': chat.pivot && chat.pivot.muted,
                    }"
                    @click="openChat(chat)"
                    @contextmenu.prevent="toggleMute(chat)"
                >
                    <span class="truncate">
                        {{ chat.title || ('Чат #' + chat.id) }}
                    </span>
                    <span
                        v-if="chat.pivot && chat.pivot.muted"
                        title="Оповещения выключены"
                    >
                        🔕
                    </span>
                </li>
            </ul>
        </aside>

        <!-- Окно сообщений -->
        <section class="flex-1 flex flex-col">
            <!-- Заголовок -->
            <header class="border-b border-gray-200 px-3 py-2 flex items-center justify-between">
                <div class="font-semibold">
                    <span v-if="activeChat">
                        {{ activeChat.title || ('Чат #' + activeChat.id) }}
                    </span>
                    <span v-else class="text-gray-400">Выберите чат</span>
                </div>
                <div
                    v-if="activeChat && activeChat.pivot && activeChat.pivot.muted"
                    class="text-xs text-gray-500 flex items-center gap-1"
                >
                    🔕 Без звука
                </div>
            </header>

            <!-- Список сообщений -->
            <div
                class="flex-1 overflow-y-auto p-3 space-y-2"
                @scroll.passive="onMessagesScroll"
            >
                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    class="px-3 py-2 rounded border bg-white shadow-sm cursor-default"
                    @contextmenu="openContextMenu($event, msg)"
                >
                    <div class="text-xs text-gray-500 mb-1 flex justify-between">
                        <span>
                            {{ msg.user?.nickname || msg.user?.name || ('user #' + msg.user_id) }}
                        </span>
                        <span>
                            {{ msg.created_at }}
                            <span v-if="msg.edited_at" class="italic">(ред.)</span>
                        </span>
                    </div>
                    <div class="whitespace-pre-wrap">
                        {{ msg.body }}
                    </div>
                </div>

                <div v-if="loadingMessages" class="text-center text-xs text-gray-500">
                    Загрузка сообщений...
                </div>
            </div>

            <!-- Поле ввода -->
            <form class="border-t border-gray-200 p-3 flex gap-2" @submit.prevent="sendMessage">
                <textarea
                    v-model="newMessage"
                    class="flex-1 border rounded px-2 py-1 text-sm resize-none"
                    rows="2"
                    placeholder="Введите сообщение..."
                ></textarea>

                <button
                    type="submit"
                    class="px-3 py-2 text-sm rounded bg-blue-600 text-white disabled:opacity-50"
                    :disabled="sending || !activeChat || !newMessage.trim()"
                >
                    {{ editingMessageId ? 'Сохранить' : 'Отправить' }}
                </button>
            </form>
        </section>

        <!-- Контекстное меню сообщения -->
        <div
            v-if="contextMenu.visible && contextMenu.message"
            class="fixed bg-white border border-gray-300 rounded shadow-md text-sm z-50"
            :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
            @click.stop
        >
            <button
                class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                @click="startEditMessage(contextMenu.message)"
            >
                Отредактировать
            </button>
            <button
                class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                @click="deleteMessage(contextMenu.message)"
            >
                Удалить
            </button>
            <button
                class="block w-full text-left px-4 py-2 hover:bg-gray-100"
                @click="forwardMessage(contextMenu.message)"
            >
                Переслать
            </button>
            <button
                class="block w-full text-left px-4 py-2 hover:bg-gray-100 text-gray-500"
                @click="closeContextMenu"
            >
                Отмена
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';

// ======= состояние =======

const chats = ref([]);
const activeChat = ref(null);

const messages = ref([]);
const loadingMessages = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
});

// новое сообщение / редактирование
const newMessage = ref('');
const sending = ref(false);
const editingMessageId = ref(null);

// контекстное меню для сообщения
const contextMenu = ref({
    visible: false,
    x: 0,
    y: 0,
    message: null,
});

// Echo-канал и звук
let currentChannel = null;
const messageSound = typeof Audio !== 'undefined'
    ? new Audio('/sounds/message.mp3')
    : null;

// ======= helpers =======

function upsertMessage(msg) {
    if (!msg || typeof msg.id === 'undefined') return;

    const idx = messages.value.findIndex(m => m.id === msg.id);
    if (idx === -1) {
        messages.value.push(msg);
    } else {
        messages.value[idx] = msg;
    }
}

function handleIncomingSound(chatId = null) {
    if (!activeChat.value) return;

    const muted = activeChat.value.pivot?.muted;
    if (muted) return;
    if (!messageSound) return;

    if (chatId && activeChat.value.id !== chatId) return;

    try {
        messageSound.currentTime = 0;
        messageSound.play();
    } catch {
        // браузер может блокировать звук до первого пользовательского действия
    }
}

// ======= загрузка чатов/сообщений =======

async function loadChats() {
    try {
        const res = await axios.get('/api/chats');
        chats.value = res.data || [];
    } catch (e) {
        console.error('loadChats error', e);
    }
}

async function openChat(chat) {
    activeChat.value = chat;
    messages.value = [];
    pagination.value = { current_page: 1, last_page: 1 };
    await loadMessages(chat.id, 1, false);
}

async function loadMessages(chatId, page = 1, append = false) {
    loadingMessages.value = true;

    try {
        const res = await axios.get(`/api/messages/${chatId}?page=${page}`);

        // backend даёт id DESC → разворачиваем
        const raw = Array.isArray(res.data.data) ? res.data.data : [];
        const pageMessages = raw.slice().reverse();

        if (append) {
            // старые сообщения добавляем наверх
            messages.value.unshift(...pageMessages);
        } else {
            messages.value = pageMessages;
        }

        pagination.value.current_page = res.data.current_page ?? page;
        pagination.value.last_page = res.data.last_page ?? page;
    } catch (e) {
        console.error('loadMessages error', e);
    } finally {
        loadingMessages.value = false;
    }
}

async function loadMore() {
    if (!activeChat.value) return;
    if (pagination.value.current_page >= pagination.value.last_page) return;

    const next = pagination.value.current_page + 1;
    await loadMessages(activeChat.value.id, next, true);
}

function onMessagesScroll(event) {
    const el = event.target;
    if (el.scrollTop === 0 && !loadingMessages.value) {
        loadMore();
    }
}

// ======= создание чатов (минимум для ТЗ) =======

async function createChatPrompt() {
    const type = window.prompt('Тип чата: direct или group?');
    if (!type || !['direct', 'group'].includes(type)) return;

    const raw = window.prompt(
        type === 'direct'
            ? 'ID пользователя для direct-чата:'
            : 'ID пользователей через запятую для группового чата:'
    );
    if (!raw) return;

    const ids = raw.split(',').map(x => Number(x.trim())).filter(Boolean);
    if (!ids.length) return;

    try {
        const res = await axios.post('/api/chats', {
            type,
            participants: ids,
        });
        chats.value.push(res.data);
        await openChat(res.data);
    } catch (e) {
        console.error('createChatPrompt error', e);
    }
}

// ======= отправка / редактирование сообщения =======

async function sendMessage() {
    if (!activeChat.value) return;
    const body = newMessage.value.trim();
    if (!body) return;

    sending.value = true;

    try {
        if (editingMessageId.value) {
            const res = await axios.patch(`/api/messages/${editingMessageId.value}`, {
                body,
            });
            upsertMessage(res.data);
        } else {
            const res = await axios.post(`/api/messages/${activeChat.value.id}`, {
                body,
            });
            upsertMessage(res.data);
        }

        newMessage.value = '';
        editingMessageId.value = null;
    } catch (e) {
        console.error('sendMessage error', e);
    } finally {
        sending.value = false;
    }
}

// ======= контекстное меню =======

function openContextMenu(event, msg) {
    event.preventDefault();
    contextMenu.value = {
        visible: true,
        x: event.clientX,
        y: event.clientY,
        message: msg,
    };
    document.addEventListener('keydown', handleEscClose);
}

function closeContextMenu() {
    contextMenu.value.visible = false;
    contextMenu.value.message = null;
    document.removeEventListener('keydown', handleEscClose);
}

function handleEscClose(e) {
    if (e.key === 'Escape') {
        closeContextMenu();
    }
}

async function deleteMessage(msg) {
    closeContextMenu();
    try {
        await axios.delete(`/api/messages/${msg.id}`);
        messages.value = messages.value.filter(m => m.id !== msg.id);
    } catch (e) {
        console.error('deleteMessage error', e);
    }
}

function startEditMessage(msg) {
    closeContextMenu();
    editingMessageId.value = msg.id;
    newMessage.value = msg.body;
}

async function forwardMessage(msg) {
    closeContextMenu();

    const targetId = window.prompt('Введите ID чата, в который переслать:');
    if (!targetId) return;

    try {
        await axios.post(`/api/messages/${msg.id}/forward`, {
            target_chat_id: Number(targetId),
        });
    } catch (e) {
        console.error('forwardMessage error', e);
    }
}

// ======= Mute / Unmute чата по правому клику =======

async function toggleMute(chat) {
    try {
        const res = await axios.patch(`/api/chats/${chat.id}/mute`);

        if (!chat.pivot) {
            chat.pivot = {};
        }
        chat.pivot.muted = res.data.muted;

        if (activeChat.value && activeChat.value.id === chat.id) {
            activeChat.value = {
                ...activeChat.value,
                pivot: {
                    ...(activeChat.value.pivot ?? {}),
                    muted: res.data.muted,
                },
            };
        }
    } catch (e) {
        console.error('toggleMute error', e);
    }
}

// ======= WebSocket (Echo + Reverb) =======

function unsubscribeFromChannel() {
    if (currentChannel && window.Echo) {
        window.Echo.leave(currentChannel);
        currentChannel = null;
    }
}

function subscribeToChannel(chatId) {
    if (!window.Echo) return;

    const channelName = `private-chat.${chatId}`;
    currentChannel = channelName;

    window.Echo.private(channelName)
        .listen('MessageSent', (e) => {
            const msg = e?.message ?? e;
            upsertMessage(msg);
            handleIncomingSound(msg.chat_id);
        })
        .listen('MessageEdited', (e) => {
            const msg = e?.message ?? e;
            upsertMessage(msg);
        })
        .listen('MessageDeleted', (e) => {
            const payload = e?.message ?? e;
            const id = payload.id ?? payload.message_id;

            if (!id) return;

            messages.value = messages.value.filter(
                m => m.id !== id,
            );
        });
}

watch(activeChat, (chat) => {
    unsubscribeFromChannel();
    if (chat) {
        subscribeToChannel(chat.id);
    }
});

// ======= lifecycle =======

onMounted(() => {
    loadChats();
});

onBeforeUnmount(() => {
    unsubscribeFromChannel();
});
</script>
