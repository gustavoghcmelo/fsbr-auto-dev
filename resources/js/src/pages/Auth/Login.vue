<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
    canResetPassword: Boolean,
    status: String,
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Entrar" />

    <div
        class="flex flex-center bg-grey-2"
        style="min-height: 100vh; padding: 16px"
    >
        <q-card flat bordered class="q-pa-lg" style="width: 100%; max-width: 420px">
            <q-card-section class="text-center">
                <div class="text-h5 text-weight-medium">Acessar o sistema</div>
                <div class="text-caption text-grey-7 q-mt-xs">
                    Informe suas credenciais para continuar
                </div>
            </q-card-section>

            <q-banner
                v-if="status"
                class="bg-positive text-white q-mx-md q-mb-md"
                rounded
            >
                {{ status }}
            </q-banner>

            <q-card-section>
                <q-form @submit.prevent="submit" class="q-gutter-md">
                    <q-input
                        v-model="form.email"
                        type="email"
                        label="E-mail"
                        outlined
                        autofocus
                        autocomplete="username"
                        :error="!!form.errors.email"
                        :error-message="form.errors.email"
                    />

                    <q-input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        label="Senha"
                        outlined
                        autocomplete="current-password"
                        :error="!!form.errors.password"
                        :error-message="form.errors.password"
                    >
                        <template #append>
                            <q-icon
                                :name="showPassword ? 'visibility_off' : 'visibility'"
                                class="cursor-pointer"
                                @click="showPassword = !showPassword"
                            />
                        </template>
                    </q-input>

                    <div class="row items-center justify-between">
                        <q-checkbox v-model="form.remember" label="Lembrar-me" />
                        <Link
                            v-if="canResetPassword"
                            href="/forgot-password"
                            class="text-primary text-caption"
                        >
                            Esqueci minha senha
                        </Link>
                    </div>

                    <q-btn
                        type="submit"
                        color="primary"
                        label="Entrar"
                        class="full-width"
                        size="md"
                        :loading="form.processing"
                    />
                </q-form>
            </q-card-section>
        </q-card>
    </div>
</template>
