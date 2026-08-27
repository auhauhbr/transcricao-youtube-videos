<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashToast from '../../components/FlashToast.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';

const props = defineProps({
    appName: { type: String, required: true },
    profile: { type: Object, required: true },
    flash: { type: Object, required: true },
    profileUrl: { type: String, required: true },
    passwordUrl: { type: String, required: true },
    hasPassword: { type: Boolean, required: true },
});

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
});
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const flashMessage = computed(() => {
    if (props.flash.status === 'profile-updated') return 'Dados da conta atualizados.';
    if (props.flash.status === 'password-updated') return 'Senha atualizada com segurança.';

    return null;
});

const updateProfile = () => profileForm.patch(props.profileUrl, { preserveScroll: true });
const updatePassword = () =>
    passwordForm.put(props.passwordUrl, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onFinish: () => passwordForm.reset('current_password', 'password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Minha conta">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <FlashToast :flash-id="flash.id" :message="flashMessage" />
        <section class="flex-1 bg-background">
            <div class="mx-auto max-w-4xl px-5 py-9 sm:px-8 sm:py-12 lg:px-10">
                <a href="/" class="ui-button-ghost px-0"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</a>
                <p class="ui-eyebrow mt-6">Configurações</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-foreground">Minha conta</h1>

                <div class="mt-8 grid gap-6 md:grid-cols-2">
                    <section class="ui-panel p-5 sm:p-6" aria-labelledby="profile-title">
                        <h2 id="profile-title" class="text-xl font-semibold text-foreground">Dados pessoais</h2>
                        <form class="mt-6 space-y-5" @submit.prevent="updateProfile">
                            <div>
                                <label for="account-name" class="text-sm font-semibold text-foreground">Nome</label>
                                <input id="account-name" v-model="profileForm.name" type="text" autocomplete="name" :aria-invalid="profileForm.errors.name ? 'true' : 'false'" :aria-describedby="profileForm.errors.name ? 'account-name-error' : undefined" class="ui-input mt-2" />
                                <p v-if="profileForm.errors.name" id="account-name-error" class="mt-2 text-sm text-destructive">{{ profileForm.errors.name }}</p>
                            </div>
                            <div>
                                <label for="account-email" class="text-sm font-semibold text-foreground">Email</label>
                                <input id="account-email" v-model="profileForm.email" type="email" autocomplete="email" :aria-invalid="profileForm.errors.email ? 'true' : 'false'" :aria-describedby="profileForm.errors.email ? 'account-email-error' : undefined" class="ui-input mt-2" />
                                <p v-if="profileForm.errors.email" id="account-email-error" class="mt-2 text-sm text-destructive">{{ profileForm.errors.email }}</p>
                            </div>
                            <button type="submit" class="ui-button-primary" :disabled="profileForm.processing">
                                <i class="bi bi-check2" aria-hidden="true"></i> Salvar dados
                            </button>
                        </form>
                    </section>

                    <section v-if="hasPassword" class="ui-panel p-5 sm:p-6" aria-labelledby="password-title">
                        <h2 id="password-title" class="text-xl font-semibold text-foreground">Alterar senha</h2>
                        <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
                            <div>
                                <label for="current-password" class="text-sm font-semibold text-foreground">Senha atual</label>
                                <input id="current-password" v-model="passwordForm.current_password" type="password" autocomplete="current-password" :aria-invalid="passwordForm.errors.current_password ? 'true' : 'false'" :aria-describedby="passwordForm.errors.current_password ? 'current-password-error' : undefined" class="ui-input mt-2" />
                                <p v-if="passwordForm.errors.current_password" id="current-password-error" class="mt-2 text-sm text-destructive">{{ passwordForm.errors.current_password }}</p>
                            </div>
                            <div>
                                <label for="new-password" class="text-sm font-semibold text-foreground">Nova senha</label>
                                <input id="new-password" v-model="passwordForm.password" type="password" autocomplete="new-password" :aria-invalid="passwordForm.errors.password ? 'true' : 'false'" :aria-describedby="passwordForm.errors.password ? 'new-password-error' : undefined" class="ui-input mt-2" />
                                <p v-if="passwordForm.errors.password" id="new-password-error" class="mt-2 text-sm text-destructive">{{ passwordForm.errors.password }}</p>
                            </div>
                            <div>
                                <label for="new-password-confirmation" class="text-sm font-semibold text-foreground">Confirmar nova senha</label>
                                <input id="new-password-confirmation" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" class="ui-input mt-2" />
                            </div>
                            <button type="submit" class="ui-button-secondary" :disabled="passwordForm.processing">
                                <i class="bi bi-shield-lock" aria-hidden="true"></i> Atualizar senha
                            </button>
                        </form>
                    </section>
                    <section v-else class="ui-panel p-5 sm:p-6" aria-labelledby="external-login-title">
                        <h2 id="external-login-title" class="text-xl font-semibold text-foreground">Login externo</h2>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">Esta conta utiliza login externo.</p>
                    </section>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
