<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../layouts/PublicLayout.vue';

defineProps({
    appName: { type: String, required: true },
    loginUrl: { type: String, required: true },
    registerUrl: { type: String, required: true },
    socialProviders: { type: Object, required: true },
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
        <section class="flex flex-1 items-center bg-background">
            <div class="mx-auto w-full max-w-md px-5 py-10 sm:px-8 sm:py-14">
                <div class="ui-panel p-6 sm:p-8">
                    <p class="ui-eyebrow">Acesso</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-foreground">Entrar</h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">Acesse sua biblioteca e continue trabalhando nas suas transcrições.</p>

                    <div v-if="socialProviders.google || socialProviders.microsoft" class="mt-8 grid gap-2">
                        <a v-if="socialProviders.google" href="/auth/google/redirect" class="ui-button-secondary w-full"><i class="bi bi-google" aria-hidden="true"></i> Continuar com Google</a>
                        <a v-if="socialProviders.microsoft" href="/auth/microsoft/redirect" class="ui-button-secondary w-full"><i class="bi bi-microsoft" aria-hidden="true"></i> Continuar com Microsoft</a>
                    </div>
                    <p v-if="form.errors.social" class="mt-3 text-sm text-destructive" role="alert">{{ form.errors.social }}</p>
                    <p v-if="socialProviders.google || socialProviders.microsoft" class="my-6 text-center text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">ou continue com email</p>
                    <p v-else class="mt-8 text-center text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">Acesse com email</p>
                    <form class="space-y-5" :aria-busy="form.processing" @submit.prevent="submit(loginUrl)">
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
                                class="ui-input mt-2 disabled:opacity-70"
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
                                class="ui-input mt-2 disabled:opacity-70"
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
                            class="ui-button-primary w-full"
                            :disabled="form.processing"
                        >
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> {{ form.processing ? 'Entrando...' : 'Entrar' }}
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-muted-foreground">
                        Ainda não possui conta?
                        <Link :href="registerUrl" class="font-semibold text-foreground underline decoration-border underline-offset-4 hover:text-accent">Criar conta</Link>
                    </p>
                    <div class="mt-5 flex items-center gap-3 text-xs text-muted-foreground"><span class="h-px flex-1 bg-border"></span><span>ou</span><span class="h-px flex-1 bg-border"></span></div>
                    <Link href="/" class="ui-button-secondary mt-5 w-full">Continuar sem conta</Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
