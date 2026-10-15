<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Activity, ArrowRight, ShieldCheck } from 'lucide-vue-next';
import FormErrors from '../Components/FormErrors.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import type { Shared } from '../types';
const form = useForm({ email: '', password: '' });
const page = usePage<Shared>();
</script>
<template>
  <Head title="Sign in" />
  <div class="auth-theme-control"><ThemeToggle /></div>
  <div class="login-shell">
    <div class="login-art">
      <div class="brand">
        <span class="brand-icon"><Activity /></span>PharmaFlow
      </div>
      <div>
        <span class="pill-label">A HEALTHIER WAY TO WORK</span>
        <h1>More clarity.<br />Better care.</h1>
        <p>Your inventory, sales and finances.<br />Connected in one pharmacy workspace.</p>
        <div class="login-grid">
          <span>01<br /><strong>Track every batch</strong></span
          ><span>02<br /><strong>Sell with confidence</strong></span
          ><span>03<br /><strong>Keep books in balance</strong></span>
        </div>
      </div>
      <span class="login-foot"
        ><ShieldCheck :size="18" /> Secure access. Accountable operations.</span
      >
    </div>
    <div class="login-form">
      <div class="login-form-inner">
        <div class="eyebrow">WELCOME TO YOUR WORKSPACE</div>
        <h2>Good to see you.</h2>
        <p>Sign in to manage your pharmacy.</p>
        <div v-if="page.props.flash" class="muted">{{ page.props.flash.success }}</div>
        <form @submit.prevent="form.post('/login', { onFinish: () => form.reset('password') })">
          <FormErrors :errors="form.errors" /><label
            >Email address<input
              v-model="form.email"
              type="email"
              autocomplete="username"
              required
              autofocus
              placeholder="you@pharmacy.com" /></label
          ><label
            >Password<input
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              placeholder="Enter your password" /></label
          ><Link class="text-link forgot-link" href="/forgot-password">Forgot password?</Link
          ><button class="btn primary login-submit" :disabled="form.processing">
            {{ form.processing ? 'Signing in…' : 'Sign in to workspace' }}<ArrowRight :size="17" />
          </button>
        </form>
        <p class="login-help">Need access? Contact your pharmacy administrator.</p>
      </div>
    </div>
  </div>
</template>
