export const accountingReports = [
  {
    title: 'Trial balance',
    url: '/accounting/trial-balance',
    description: 'Account debits, credits and balance checks.',
  },
  {
    title: 'Operating result',
    url: '/accounting/operating-result',
    description: 'Revenue less returns, cost of goods sold and expenses.',
  },
  {
    title: 'Stock reconciliation',
    url: '/accounting/stock-reconciliation',
    description: 'Compare current batch balances with recorded movements.',
  },
  {
    title: 'Journal entries',
    url: '/accounting/journal-entries',
    description: 'Browse source transactions and posting dates.',
  },
];
export interface AccountBalance {
  code: string;
  name: string;
  type: string;
  debit: string;
  credit: string;
}
