<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    nickname: user.nickname ?? '',
    email_hidden: user.email_hidden ?? false,
    avatar: null,
});

const submitForm = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    }));

    form.post(route('profile.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('avatar'),
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Профиль
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Обновите данные профиля, никнейм, аватар и настройку видимости почты.
            </p>
        </header>

        <form @submit.prevent="submitForm" class="mt-6 space-y-6">
            <div>
                <InputLabel for="name" value="Имя" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="nickname" value="Nickname" />

                <TextInput
                    id="nickname"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.nickname"
                    autocomplete="nickname"
                    placeholder="Уникальное имя в сети"
                />

                <InputError class="mt-2" :message="form.errors.nickname" />
            </div>

            <div>
                <InputLabel for="avatar" value="Аватар" />

                <input
                    id="avatar"
                    type="file"
                    class="mt-1 block w-full text-sm"
                    accept="image/*"
                    @change="form.avatar = $event.target.files[0]"
                />

                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="email_hidden"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    v-model="form.email_hidden"
                />
                <InputLabel for="email_hidden" value="Скрыть email (требуется nickname)" />
                <InputError class="mt-2" :message="form.errors.email_hidden" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
