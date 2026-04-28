<script setup>
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    email: String,
    token: String,
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Redefinir senha" />

    <div
        class="flex flex-center bg-grey-2"
        style="min-height: 100vh; padding: 16px"
    >
        <q-card flat bordered class="q-pa-lg" style="width: 100%; max-width: 420px">
            <q-card-section class="text-center">
                <div class="text-h5 text-weight-medium">Definir nova senha</div>
                <div class="text-caption text-grey-7 q-mt-xs">
                    Escolha uma senha segura para acessar sua conta
                </div>
            </q-card-section>

            <q-card-section>
                <q-form @submit.prevent="submit" class="q-gutter-md">
                    <q-input
                        v-model="form.email"
                        type="email"
                        label="E-mail"
                        outlined
                        autocomplete="username"
                        :error="!!form.errors.email"
                        :error-message="form.errors.email"
                    />

                    <q-input
                        v-model="form.password"
                        type="password"
                        label="Nova senha"
                        outlined
                        autofocus
                        autocomplete="new-password"
                        :error="!!form.errors.password"
                        :error-message="form.errors.password"
                    />

                    <q-input
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirmar nova senha"
                        outlined
                        autocomplete="new-password"
                        :error="!!form.errors.password_confirmation"
                        :error-message="form.errors.password_confirmation"
                    />

                    <q-btn
                        type="submit"
                        color="primary"
                        label="Redefinir senha"
                        class="full-width"
                        size="md"
                        :loading="form.processing"
                    />
                </q-form>
            </q-card-section>
        </q-card>
    </div>
</template>
