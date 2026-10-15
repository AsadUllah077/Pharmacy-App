import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Shared } from './types';
export function useBusiness() {
  const page = usePage<Shared>();
  const can = (permission: string) =>
    page.props.auth?.permissions.some((p) => p === '*' || p === permission) ?? false;
  const branch = computed(() =>
    page.props.auth.branches.find((b) => b.id === page.props.auth.branch_id),
  );
  const money = (value: string | number) =>
    new Intl.NumberFormat('en-PK', {
      style: 'currency',
      currency: page.props.business.currency,
      maximumFractionDigits: 2,
    }).format(Number(value));
  const date = (value: string) =>
    new Intl.DateTimeFormat('en-PK', {
      timeZone: page.props.business.timezone,
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value));
  return { page, can, branch, money, date };
}
export async function getJson<T>(url: string): Promise<T> {
  const r = await fetch(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!r.ok) throw new Error('Unable to load records. Please retry.');
  return r.json();
}
