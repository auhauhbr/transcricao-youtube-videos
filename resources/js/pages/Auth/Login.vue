<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../layouts/PublicLayout.vue';

defineProps({
    appName: { type: String, required: true },
    loginUrl: { type: String, required: true },
    registerUrl: { type: String, required: true },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = (loginUrl) => {
    form.post(loginUrl, {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Entrar">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <section class="flex flex-1 items-center border-b border-border bg-background">
            <div class="mx-auto w-full max-w-lg px-5 py-14 sm:px-8 sm:py-20">
                <div class="border border-border bg-card p-7 sm:p-9">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Sua conta</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-foreground">Entrar</h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">Continue extraindo transcrições sem o limite anônimo.</p>

                    <form class="mt-8 space-y-5" :aria-busy="form.processing" @submit.prevent="submit(loginUrl)">
                        <div>
                            <label for="login-email" class="text-sm font-semibold text-foreground">Email</label>
                            <input
                                id="login-email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                autofocus
                                :aria-invalid="form.errors.email ? 'true' : 'false'"
                                :aria-describedby="form.errors.email ? 'login-email-error' : undefined"
                                class="mt-2 h-12 w-full border border-border bg-background px-4 text-foreground outline-none focus:border-accent disabled:opacity-70"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.email" id="login-email-error" class="mt-2 text-sm font-medium text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="login-password" class="text-sm font-semibold text-foreground">Senha</label>
                            <input
                                id="login-password"
                                v-model="form.password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                :aria-invalid="form.errors.password ? 'true' : 'false'"
                                :aria-describedby="form.errors.password ? 'login-password-error' : undefined"
                                class="mt-2 h-12 w-full border border-border bg-background px-4 text-foreground outline-none focus:border-accent disabled:opacity-70"
                                :disabled="form.processing"
                            />
                            <p v-if="form.errors.password" id="login-password-error" class="mt-2 text-sm font-medium text-destructive">{{ form.errors.password }}</p>
                        </div>

                        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-muted-foreground">
                            <input v-model="form.remember" type="checkbox" name="remember" class="size-4 accent-[var(--color-accent)]" />
                            Manter conectado
                        </label>

                        <button
                            type="submit"
                            class="inline-flex h-12 w-full items-center justify-center bg-red-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-red-800 disabled:cursor-wait disabled:opacity-70"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Entrando...' : 'Entrar' }}
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-muted-foreground">
                        Ainda não possui conta?
                        <Link :href="registerUrl" class="font-semibold text-foreground underline decoration-border underline-offset-4 hover:text-accent">Criar conta</Link>
                    </p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
