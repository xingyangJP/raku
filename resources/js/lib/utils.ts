import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}


export const getStatusColor = (status: string) => {
  switch (status) {
    case 'draft':
      return 'bg-gray-200 text-gray-800';
    case 'pending':
      return 'bg-yellow-200 text-yellow-800';
    case 'sent':
      return 'bg-green-200 text-green-800';
    case 'rejected':
      return 'bg-red-200 text-red-800';
    default:
      return 'bg-gray-200 text-gray-800';
  }
};

export const getStatusText = (status: string) => {
  switch (status) {
    case 'draft':
      return '下書き';
    case 'pending':
      return '承認待ち';
    case 'sent':
      return '承認済み';
    case 'rejected':
      return '差し戻し';
    default:
      return '不明';
  }
};

export function formatCurrency(amount: number | null | undefined): string {
  if (amount === null || amount === undefined) {
    // Return ¥0 for null/undefined to avoid empty spaces in UI
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(0);
  }
  return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
}

export function formatDate(dateString: string | null | undefined): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    // Check if date is valid
    if (isNaN(date.getTime())) {
        return '';
    }
    return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
}