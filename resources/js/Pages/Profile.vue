<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import FormErrors from '../Components/FormErrors.vue';
import { useTheme, type ThemePreference } from '../theme';
const { preference, setTheme } = useTheme();
function changeTheme(event: Event) {
  setTheme((event.target as HTMLSelectElement).value as ThemePreference);
}
const form = useForm({ current_password: '', password: '', password_confirmation: '' });
</script>
<template>
  <AppLayout title="Your account" subtitle="Update your password and secure your account.">
    <section class="panel form-panel">
      <h2>Appearance</h2>
      <label
        >Color theme<select :value="preference" @change="changeTheme">
          <option value="system">Use system setting</option>
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select></label
      >
      <p class="muted">Your preference is saved on this device and applied across the app.</p>
    </section>
    <form class="panel form-panel" @submit.prevent="form.put('/profile/password')">
      <h2>Change password</h2>
      <FormErrors :errors="form.errors" /><label
        >Current password<input
          v-model="form.current_password"
          type="password"
          autocomplete="current-password"
          required /></label
      ><label
        >New password<input
          v-model="form.password"
          type="password"
          minlength="12"
          autocomplete="new-password"
          required /></label
      ><label
        >Confirm new password<input
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          required
      /></label>
      <p class="muted">
        Use at least 12 characters, mixed case and numbers. Changing your password signs out all
        sessions.
      </p>
      <button class="btn primary" :disabled="form.processing">Change password</button>
    </form></AppLayout
  >
</template>
