<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import type { Shared } from '../types';
import FormErrors from '../Components/FormErrors.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
const props = defineProps<{ mode: string; token?: string; email?: string }>();
const page = usePage<Shared>();
const form = useForm({
  email: props.email ?? '',
  token: props.token ?? '',
  password: '',
  password_confirmation: '',
});
</script>
<template>
  <Head title="Password recovery" />
  <div class="auth-theme-control"><ThemeToggle /></div>
  <main class="auth-card">
    <h1>{{ mode === 'forgot' ? 'Reset your password' : 'Choose a new password' }}</h1>
    <div v-if="page.props.flash.success" class="notice success" role="status">
      {{ page.props.flash.success }}
    </div>
    <p class="muted">Reset links are delivered through the configured email service.</p>
    <form @submit.prevent="form.post(mode === 'forgot' ? '/forgot-password' : '/reset-password')">
      <FormErrors :errors="form.errors" /><label
        >Email<input v-model="form.email" type="email" required /></label
      ><template v-if="mode === 'reset'"
        ><label
          >New password<input
            v-model="form.password"
            type="password"
            minlength="12"
            autocomplete="new-password"
            required /></label
        ><label
          >Confirm password<input
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required /></label></template
      ><button class="btn primary" :disabled="form.processing">
        {{ mode === 'forgot' ? 'Send reset link' : 'Reset password' }}
      </button>
    </form>
    <Link href="/login" class="text-link">Back to sign in</Link>
  </main>
</template>
