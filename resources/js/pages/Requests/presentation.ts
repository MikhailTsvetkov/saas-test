import type { RequestStatus } from '../../types';

export const requestStatuses: Record<RequestStatus, { label: string; classes: string }> = {
    new: { label: 'Новое', classes: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200' },
    in_progress: { label: 'В работе', classes: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' },
    resolved: { label: 'Решено', classes: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' },
};

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'UTC',
});

export function formatRequestDate(value: string): string {
    return dateFormatter.format(new Date(value));
}
