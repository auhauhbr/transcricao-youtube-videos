<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../layouts/PublicLayout.vue';

defineProps({
    appName: { type: String, required: true },
    registerUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
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
        <section class="flex flex-1 items-center border-b border-border bg-background">
            <div class="mx-auto w-full max-w-lg px-5 py-14 sm:px-8 sm:py-20">
                <div class="border border-border bg-card p-7 sm:p-9">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Acesso contínuo</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-foreground">Criar conta</h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">Suas transcrições deste navegador serão associadas à conta.</p>

                    <form class="mt-8 space-y-5" :aria-busy="form.processing" @submit.prevent="submit(registerUrl)">
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
                                class="mt-2 h-12 w-full border border-border bg-background px-4 text-foreground outline-none focus:border-accent disabled:opacity-70"
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
                                class="mt-2 h-12 w-full border border-border bg-background px-4 text-foreground outline-none focus:border-accent disabled:opacity-70"
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
                                class="mt-2 h-12 w-full border border-border bg-background px-4 text-foreground outline-none focus:border-accent disabled:opacity-70"
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
                                class="mt-2 h-12 w-full border border-border bg-background px-4 text-foreground outline-none focus:border-accent disabled:opacity-70"
                                :disabled="form.processing"
                            />
                        </div>

                        <button
                            type="submit"
                            class="inline-flex h-12 w-full items-center justify-center bg-red-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-red-800 disabled:cursor-wait disabled:opacity-70"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Criando conta...' : 'Criar conta' }}
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
