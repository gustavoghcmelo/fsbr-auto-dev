<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({
    status: String,
})

const form = useForm({
    email: '',
})

function submit() {
    form.post('/forgot-password')
}
</script>

<template>
    <Head title="Recuperar senha" />

    <div
        class="flex flex-center bg-grey-2"
        style="min-height: 100vh; padding: 16px"
    >
        <q-card flat bordered class="q-pa-lg" style="width: 100%; max-width: 420px">
            <q-card-section class="text-center">
                <div class="text-h5 text-weight-medium">Recuperar senha</div>
                <div class="text-caption text-grey-7 q-mt-xs">
                    Informe seu e-mail para receber o link de redefinição
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

                    <q-btn
                        type="submit"
                        color="primary"
                        label="Enviar link"
                        class="full-width"
                        size="md"
                        :loading="form.processing"
                    />

                    <div class="text-center q-mt-sm">
                        <Link href="/login" class="text-primary text-caption">
                            Voltar para o login
                        </Link>
                    </div>
                </q-form>
            </q-card-section>
        </q-card>
    </div>
</template>
