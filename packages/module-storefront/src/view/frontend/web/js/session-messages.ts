import events from 'MageObsidian_ModernFrontend::js/events';
import { readCookie } from 'mage-obsidian/runtime/sectionStoreCore.ts';
import { LifecycleEvent } from 'mage-obsidian/runtime/lifecycleEvents.ts';
import { NotificationTone, notify } from 'MageObsidian_Storefront::js/notifications';

export const MESSAGES_SECTION = 'messages';
export const MESSAGES_COOKIE = 'mage-messages';

const BATCH_LIMIT = 3;
const SHOWN_STORAGE_KEY = 'obsidian-session-messages';

const ALLOWED_TAGS = new Set(['A', 'B', 'BR', 'EM', 'I', 'SPAN', 'STRONG']);
const DROPPED_TAGS = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'LINK']);
const ALLOWED_ATTRIBUTES: Record<string, string[]> = { A: ['href', 'title'] };
const SAFE_HREF = /^(https?:|mailto:|tel:|\/|#)/i;
const CONTROL_CHARACTERS = /[\u0000-\u0020]/g;

const TONES: Record<string, NotificationTone> = {
    success: NotificationTone.Success,
    error: NotificationTone.Error,
    warning: NotificationTone.Warning,
    notice: NotificationTone.Notice,
};

export interface SessionMessage {
    type?: string;
    text?: string;
}

export interface DrainedMessage {
    tone: NotificationTone;
    message: string;
    text: string;
    html: boolean;
}

export function toneFor(type: string | undefined): NotificationTone {
    return TONES[String(type ?? '').toLowerCase()] ?? NotificationTone.Notice;
}

function hasSafeHref(value: string): boolean {
    return SAFE_HREF.test(value.replace(CONTROL_CHARACTERS, ''));
}

function scrub(parent: Element): void {
    for (const child of Array.from(parent.children)) {
        if (DROPPED_TAGS.has(child.tagName)) {
            child.remove();
            continue;
        }
        scrub(child);
        if (!ALLOWED_TAGS.has(child.tagName)) {
            child.replaceWith(...Array.from(child.childNodes));
            continue;
        }
        const allowed = ALLOWED_ATTRIBUTES[child.tagName] ?? [];
        for (const attribute of Array.from(child.attributes)) {
            if (!allowed.includes(attribute.name.toLowerCase())) {
                child.removeAttribute(attribute.name);
            }
        }
        const href = child.getAttribute('href');
        if (href !== null && !hasSafeHref(href)) {
            child.removeAttribute('href');
        }
    }
}

export function sanitize(value: string): { html: string; text: string } {
    const parsed = new DOMParser().parseFromString(value, 'text/html');
    scrub(parsed.body);
    return { html: parsed.body.innerHTML, text: parsed.body.textContent ?? '' };
}

export function normalize(value: string): { message: string; text: string; html: boolean } {
    const { html, text } = sanitize(value);
    if (/<[a-z]/i.test(html)) {
        return { message: html, text, html: true };
    }
    return { message: text, text, html: false };
}

function cookiePaths(): string[] {
    const paths = new Set<string>(['/']);
    const pathname = typeof location === 'undefined' ? '' : location.pathname;
    let current = '';
    for (const segment of pathname.split('/').filter(Boolean)) {
        current += `/${segment}`;
        paths.add(current);
        paths.add(`${current}/`);
    }
    return Array.from(paths);
}

function expireCookie(): void {
    for (const path of cookiePaths()) {
        document.cookie = `${MESSAGES_COOKIE}=; Max-Age=0; path=${path}; SameSite=Strict`;
    }
}

function fingerprint(messages: SessionMessage[]): string {
    return messages.map((message) => `${message.type ?? ''}|${message.text ?? ''}`).join('~~');
}

function wasAlreadyShown(mark: string): boolean {
    try {
        return sessionStorage.getItem(SHOWN_STORAGE_KEY) === mark;
    } catch {
        return false;
    }
}

function rememberShown(mark: string): void {
    try {
        sessionStorage.setItem(SHOWN_STORAGE_KEY, mark);
    } catch {
        return;
    }
}

export function readCookieMessages(): SessionMessage[] {
    if (typeof document === 'undefined') {
        return [];
    }
    const raw = readCookie(document.cookie, MESSAGES_COOKIE);
    if (!raw) {
        return [];
    }
    expireCookie();

    let parsed: unknown;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return [];
    }
    if (!Array.isArray(parsed) || parsed.length === 0) {
        return [];
    }

    const messages = parsed.slice(-BATCH_LIMIT) as SessionMessage[];
    const mark = fingerprint(messages);
    if (wasAlreadyShown(mark)) {
        return [];
    }
    rememberShown(mark);
    return messages;
}

export async function drainSectionMessages(): Promise<SessionMessage[]> {
    const { useCustomerData } = await import('MageObsidian_ModernFrontend::js/customer-data');
    const customerData = useCustomerData();
    const items = customerData.section(MESSAGES_SECTION)?.messages;
    if (!Array.isArray(items) || items.length === 0) {
        return [];
    }
    customerData.patch(MESSAGES_SECTION, { messages: [] });
    return items.slice(-BATCH_LIMIT) as SessionMessage[];
}

export function prepare(messages: SessionMessage[]): DrainedMessage[] {
    const seen = new Set<string>();
    const batch: DrainedMessage[] = [];
    for (const message of messages) {
        if (typeof message?.text !== 'string' || message.text.trim() === '') {
            continue;
        }
        const key = `${message.type ?? ''}|${message.text}`;
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        batch.push({ tone: toneFor(message.type), ...normalize(message.text) });
    }
    return batch;
}

let pendingBatch: DrainedMessage[] = [];
let suppressionDepth = 0;

export async function announce(messages: SessionMessage[]): Promise<void> {
    const batch = prepare(messages);
    if (batch.length === 0) {
        return;
    }
    if (suppressionDepth > 0) {
        pendingBatch = [...pendingBatch, ...batch];
        return;
    }
    for (const item of batch) {
        await notify(item.message, item.tone, { html: item.html });
    }
}

export function firstErrorText(batch: DrainedMessage[]): string | undefined {
    return batch.find((item) => item.tone === NotificationTone.Error)?.text;
}

export function consumeLastBatch(): DrainedMessage[] {
    const batch = pendingBatch;
    pendingBatch = [];
    return batch;
}

export async function withSuppressedNotifications<T>(run: () => Promise<T>): Promise<T> {
    if (suppressionDepth === 0) {
        pendingBatch = [];
    }
    suppressionDepth += 1;
    try {
        return await run();
    } finally {
        suppressionDepth = Math.max(0, suppressionDepth - 1);
    }
}

function drainCookie(): void {
    void announce(readCookieMessages());
}

export function observeSectionMessages(): () => void {
    return events.observe(LifecycleEvent.SectionReloadAfter, async (event) => {
        const changed = Array.isArray(event?.changed) ? event.changed : [];
        if (!changed.includes(MESSAGES_SECTION)) {
            return;
        }
        await announce(await drainSectionMessages());
    });
}

export function bindSessionMessages(): void {
    observeSectionMessages();

    if (typeof document === 'undefined') {
        return;
    }
    if ((document as Document & { prerendering?: boolean }).prerendering) {
        document.addEventListener('prerenderingchange', drainCookie, { once: true });
    } else {
        drainCookie();
    }
    window.addEventListener('pageshow', (event) => {
        if ((event as PageTransitionEvent).persisted) {
            drainCookie();
        }
    });
}

bindSessionMessages();
