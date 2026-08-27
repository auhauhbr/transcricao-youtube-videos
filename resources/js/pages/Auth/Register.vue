<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../layouts/PublicLayout.vue';

defineProps({
    appName: { type: String, required: true },
    registerUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
    socialProviders: { type: Object, required: true },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = (registerUrl) => {
    form.post(registerUrl, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Criar conta">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <section class="flex flex-1 items-center bg-background">
            <div class="mx-auto w-full max-w-md px-5 py-10 sm:px-8 sm:py-14">
                <div class="ui-panel p-6 sm:p-8">
                    <p class="ui-eyebrow">Acesso contínuo</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-foreground">Criar conta</h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">Suas transcrições deste navegador serão associadas à conta.</p>

                    <div v-if="socialProviders.google || socialProviders.microsoft" class="mt-8 grid gap-2">
                        <a v-if="socialProviders.google" href="/auth/google/redirect" class="ui-button-secondary w-full"><i class="bi bi-google" aria-hidden="true"></i> Continuar com Google</a>
                        <a v-if="socialProviders.microsoft" href="/auth/microsoft/redirect" class="ui-button-secondary w-full"><i class="bi bi-microsoft" aria-hidden="true"></i> Continuar com Microsoft</a>
                    </div>
                    <p v-if="socialProviders.google || socialProviders.microsoft" class="my-6 text-center text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">ou continue com email</p>
                    <p v-else class="mt-8 text-center text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">Crie sua conta com email</p>
                    <form class="space-y-5" :aria-busy="form.processing" @submit.prevent="submit(registerUrl)">
                        <div>
                            <label for="register-name" class="text-sm font-semibold text-foreground">Nome</label>
                            <input
                                id="register-name"
                                v-model="form.name"
                                name="name"
                                type="text"
                                autocomplete="name"
                                autofocus
                                :aria-invalid="form.errors.name ? 'true' : 'false'"
                                :aria-describedby="form.errors.name ? 'register-name-error' : undefined"
                                class="ui-input mt-2 disabled:opacity-70"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.name" id="register-name-error" class="mt-2 text-sm font-medium text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="register-email" class="text-sm font-semibold text-foreground">Email</label>
                            <input
                                id="register-email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                :aria-invalid="form.errors.email ? 'true' : 'false'"
                                :aria-describedby="form.errors.email ? 'register-email-error' : undefined"
                                class="ui-input mt-2 disabled:opacity-70"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.email" id="register-email-error" class="mt-2 text-sm font-medium text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="register-password" class="text-sm font-semibold text-foreground">Senha</label>
                            <input
                                id="register-password"
                                v-model="form.password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                :aria-invalid="form.errors.password ? 'true' : 'false'"
                                :aria-describedby="form.errors.password ? 'register-password-error' : 'register-password-help'"
                                class="ui-input mt-2 disabled:opacity-70"
                                :disabled="form.processing"
                            />
                            <p id="register-password-help" class="mt-2 text-xs text-muted-foreground">Use pelo menos 8 caracteres.</p>
                            <p v-if="form.errors.password" id="register-password-error" class="mt-2 text-sm font-medium text-destructive">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label for="register-password-confirmation" class="text-sm font-semibold text-foreground">Confirmar senha</label>
                            <input
                                id="register-password-confirmation"
                                v-model="form.password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="ui-input mt-2 disabled:opacity-70"
                                :disabled="form.processing"
                            />
                        </div>

                        <button
                            type="submit"
                            class="ui-button-primary w-full"
                            :disabled="form.processing"
                        >
                            <i class="bi bi-person-plus" aria-hidden="true"></i> {{ form.processing ? 'Criando conta...' : 'Criar conta' }}
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-muted-foreground">
                        Já possui conta?
                        <Link :href="loginUrl" class="font-semibold text-foreground underline decoration-border underline-offset-4 hover:text-accent">Entrar</Link>
                    </p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
