<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
  Activity,
  LayoutDashboard,
  ShoppingCart,
  Pill,
  Boxes,
  Truck,
  Users,
  Receipt,
  Landmark,
  Bell,
  ShieldCheck,
  LogOut,
  Menu,
  ArrowUpRight,
  ClipboardList,
  X,
} from 'lucide-vue-next';
import { useBusiness } from '../composables';
import ThemeToggle from './ThemeToggle.vue';
defineProps<{ title: string; subtitle?: string }>();
const { page, can } = useBusiness();
const mobile = ref(false);
const isMobile = ref(window.matchMedia('(max-width: 900px)').matches);
const sidebar = ref<HTMLElement>();
const menuButton = ref<HTMLButtonElement>();
const closeButton = ref<HTMLButtonElement>();
const mobileQuery = window.matchMedia('(max-width: 900px)');
const originalOverflow = document.body.style.overflow;
function onViewportChange(event: MediaQueryListEvent) {
  isMobile.value = event.matches;
  if (!event.matches) mobile.value = false;
}
watch(mobile, async (open) => {
  document.body.style.overflow = open ? 'hidden' : originalOverflow;
  await nextTick();
  if (open) {
    requestAnimationFrame(() => {
      if (mobile.value) closeButton.value?.focus({ preventScroll: true });
    });
  } else if (isMobile.value) menuButton.value?.focus({ preventScroll: true });
});
function handleDrawerKey(event: KeyboardEvent) {
  if (!isMobile.value || !mobile.value) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    mobile.value = false;
  }
  if (event.key !== 'Tab') return;
  const controls = Array.from(
    sidebar.value?.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), select, input, [tabindex="0"]',
    ) ?? [],
  ).filter((element) => element.getClientRects().length > 0);
  const first = controls[0],
    last = controls[controls.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last?.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first?.focus();
  }
}
onMounted(() => mobileQuery.addEventListener('change', onViewportChange));
onUnmounted(() => {
  mobileQuery.removeEventListener('change', onViewportChange);
  document.body.style.overflow = originalOverflow;
});
const navigation = [
  { label: 'Overview', url: '/', permission: 'dashboard.view', icon: LayoutDashboard },
  { label: 'Point of sale', url: '/pos', permission: 'sales.create', icon: ShoppingCart },
  { label: 'Medicines', url: '/medicines', permission: 'medicines.view', icon: Pill },
  { label: 'Inventory & expiry', url: '/inventory', permission: 'inventory.view', icon: Boxes },
  { label: 'Purchases', url: '/purchases', permission: 'purchases.view', icon: Truck },
  { label: 'Suppliers', url: '/suppliers', permission: 'suppliers.view', icon: ClipboardList },
  { label: 'Sales & returns', url: '/sales', permission: 'sales.view', icon: Receipt },
  { label: 'Customers', url: '/customers', permission: 'customers.view', icon: Users },
  {
    label: 'Supplier payments',
    url: '/supplier-payments',
    permission: 'accounting.view',
    icon: Landmark,
  },
  { label: 'Accounting', url: '/accounting', permission: 'accounting.view', icon: Landmark },
  { label: 'Expenses', url: '/expenses', permission: 'accounting.view', icon: Receipt },
  { label: 'Notifications', url: '/notifications', permission: 'inventory.view', icon: Bell },
  {
    label: 'Administration',
    url: '/administration',
    permission: 'users.manage',
    icon: ShieldCheck,
  },
  { label: 'Audit trail', url: '/audit', permission: 'audit_logs.view', icon: ClipboardList },
];
function switchBranch(event: Event) {
  router.post('/branch', { branch_id: Number((event.target as HTMLSelectElement).value) });
}
</script>
<template>
  <Head :title="title" />
  <div class="app-shell">
    <button
      v-if="mobile && isMobile"
      class="sidebar-backdrop print:hidden"
      aria-label="Close navigation"
      tabindex="-1"
      @click="mobile = false"
    />
    <aside
      id="workspace-sidebar"
      ref="sidebar"
      class="sidebar print:hidden"
      :class="{ open: mobile }"
      :inert="isMobile && !mobile"
      :role="isMobile ? 'dialog' : undefined"
      :aria-modal="isMobile && mobile ? true : undefined"
      aria-label="Workspace navigation"
      @keydown="handleDrawerKey"
    >
      <button
        ref="closeButton"
        type="button"
        class="icon-button sidebar-close"
        aria-label="Close menu"
        @click="mobile = false"
      >
        <X :size="20" />
      </button>
      <Link href="/" class="brand"
        ><span class="brand-icon"><Activity :size="24" /></span>Pharma<span class="brand-light"
          >Flow</span
        ></Link
      >
      <div class="workspace-label">PHARMACY WORKSPACE</div>
      <nav aria-label="Main navigation">
        <template v-for="item in navigation" :key="item.url"
          ><Link
            v-if="can(item.permission)"
            :href="item.url"
            class="nav-item"
            :class="{
              selected: item.url === '/' ? page.url === '/' : page.url.startsWith(item.url),
            }"
            :aria-current="
              (item.url === '/' ? page.url === '/' : page.url.startsWith(item.url))
                ? 'page'
                : undefined
            "
            @click="mobile = false"
            ><component :is="item.icon" :size="18" /><span>{{ item.label }}</span
            ><span v-if="item.url === '/pos'" class="nav-shortcut">POS</span></Link
          ></template
        >
      </nav>
      <div class="sidebar-bottom">
        <div class="system-status"><span class="status-dot" /> Database connected</div>
        <Link href="/profile" class="user-card"
          ><span class="avatar">{{ page.props.auth.user.name.slice(0, 2).toUpperCase() }}</span
          ><span
            ><strong>{{ page.props.auth.user.name }}</strong
            ><small>Account & password</small></span
          ><ArrowUpRight :size="16" /></Link
        ><button class="logout" @click="router.post('/logout')">
          <LogOut :size="16" /> Sign out
        </button>
      </div>
    </aside>
    <div class="main-shell" :inert="isMobile && mobile">
      <header class="topbar print:hidden">
        <div class="topbar-left">
          <button
            ref="menuButton"
            type="button"
            class="icon-button mobile-toggle"
            aria-label="Open menu"
            aria-controls="workspace-sidebar"
            :aria-expanded="mobile"
            @click="mobile = !mobile"
          >
            <Menu :size="20" /></button
          ><span class="breadcrumb"
            >Workspace <span>/</span> <strong>{{ title }}</strong></span
          >
        </div>
        <div class="topbar-right">
          <div class="branch-selector">
            <span class="branch-dot" /><select
              :value="page.props.auth.branch_id"
              aria-label="Active branch"
              @change="switchBranch"
            >
              <option v-for="b in page.props.auth.branches" :key="b.id" :value="b.id">
                {{ b.name }}
              </option>
            </select>
          </div>
          <ThemeToggle />
          <Link
            v-if="can('inventory.view')"
            href="/notifications"
            class="icon-button"
            aria-label="Notifications"
            ><Bell :size="19" /></Link
          ><span class="avatar small">{{ page.props.auth.user.name.slice(0, 1) }}</span>
        </div>
      </header>
      <main>
        <div class="page-heading print:hidden">
          <div>
            <div class="eyebrow">YOUR PHARMACY, CONNECTED</div>
            <h1>{{ title }}</h1>
            <p v-if="subtitle">{{ subtitle }}</p>
          </div>
          <div class="page-actions"><slot name="actions" /></div>
        </div>
        <div v-if="page.props.flash.success" class="notice success" role="status">
          {{ page.props.flash.success }}
        </div>
        <slot />
      </main>
      <footer class="print:hidden">
        PharmaFlow <span>·</span> Pharmacy operations, in sync.
        <span class="footer-currency"
          >{{ page.props.business.currency }} · {{ page.props.business.timezone }}</span
        >
      </footer>
    </div>
  </div>
</template>
