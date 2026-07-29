import events from 'MageObsidian_ModernFrontend::js/events';

export const NOTIFICATION_EVENT = 'notification_add';

/** Kept so inline snippets written against the old CustomEvent keep working. */
export const LEGACY_TOAST_EVENT = 'obsidian:toast';

export const NotificationTone = {
    Success: 'success',
    Error: 'error',
    Warning: 'warning',
} as const;

export type NotificationTone = (typeof NotificationTone)[keyof typeof NotificationTone];

export interface NotificationEvent {
    message: string;
    tone: NotificationTone;
}

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap {
        [NOTIFICATION_EVENT]: NotificationEvent;
    }
}

export function notify(
    message: string,
    tone: NotificationTone = NotificationTone.Success,
): Promise<NotificationEvent> {
    return events.dispatch(NOTIFICATION_EVENT, { message, tone });
}
