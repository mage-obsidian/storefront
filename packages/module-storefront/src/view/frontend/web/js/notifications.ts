import events from 'MageObsidian_ModernFrontend::js/events';

export const NOTIFICATION_EVENT = 'notification_add';

/** Kept so inline snippets written against the old CustomEvent keep working. */
export const LEGACY_TOAST_EVENT = 'obsidian:toast';

export const NotificationTone = {
    Success: 'success',
    Error: 'error',
    Warning: 'warning',
    Notice: 'notice',
} as const;

export type NotificationTone = (typeof NotificationTone)[keyof typeof NotificationTone];

export interface NotificationEvent {
    message: string;
    tone: NotificationTone;
    html?: boolean;
    durationMs?: number;
}

export interface NotifyOptions {
    html?: boolean;
    durationMs?: number;
}

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap {
        [NOTIFICATION_EVENT]: NotificationEvent;
    }
}

const PENDING_LIMIT = 5;

const pending: NotificationEvent[] = [];

function hasSink(): boolean {
    return events.observersOf(NOTIFICATION_EVENT).length > 0;
}

export function onNotification(handler: (event: NotificationEvent) => void): () => void {
    const unobserve = events.observe(NOTIFICATION_EVENT, handler);
    pending.splice(0, pending.length).forEach(handler);
    return unobserve;
}

export function notify(
    message: string,
    tone: NotificationTone = NotificationTone.Success,
    options: NotifyOptions = {},
): Promise<NotificationEvent> {
    const event: NotificationEvent = { message, tone, ...options };
    if (!hasSink()) {
        pending.push(event);
        if (pending.length > PENDING_LIMIT) {
            pending.shift();
        }
        return Promise.resolve(event);
    }
    return events.dispatch(NOTIFICATION_EVENT, event);
}
