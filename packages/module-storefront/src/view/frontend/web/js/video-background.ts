const VIDEO_BACKGROUND = '[data-background-type="video"]';
const REMOTE_HOSTS = ["youtube.com", "youtu.be", "player.vimeo.com", "vimeo.com"];

const bound = new WeakSet<Element>();

export function isSelfHosted(source: string): boolean {
    if (source === "") {
        return false;
    }

    return !REMOTE_HOSTS.some((host) => source.includes(host));
}

export function sourceOf(element: Element): string {
    return (element.getAttribute("data-video-src") ?? "").trim();
}

export function posterOf(element: Element): string {
    return (element.getAttribute("data-video-fallback-src") ?? "").trim();
}

export function playsOnlyWhenVisible(element: Element): boolean {
    return element.getAttribute("data-video-play-only-visible") !== "false";
}

function layerFor(element: HTMLElement, view: Window): HTMLVideoElement {
    const video = view.document.createElement("video");
    video.muted = true;
    video.defaultMuted = true;
    video.loop = element.getAttribute("data-video-loop") !== "false";
    video.autoplay = true;
    video.setAttribute("playsinline", "");
    video.setAttribute("aria-hidden", "true");
    video.setAttribute("data-video-background", "");
    video.setAttribute("preload", "none");

    const poster = posterOf(element);
    if (poster !== "") {
        video.setAttribute("poster", poster);
    }

    Object.assign(video.style, {
        position: "absolute",
        inset: "0",
        width: "100%",
        height: "100%",
        objectFit: "cover",
        pointerEvents: "none",
        zIndex: "0",
    });

    if (view.getComputedStyle(element).position === "static") {
        element.style.position = "relative";
    }
    element.insertBefore(video, element.firstChild);

    return video;
}

function play(video: HTMLVideoElement, source: string): void {
    if (video.getAttribute("src") !== source) {
        video.setAttribute("src", source);
    }

    void video.play?.().catch(() => undefined);
}

export function enhanceVideoBackgrounds(root: ParentNode = document, view: Window = window): void {
    root.querySelectorAll<HTMLElement>(VIDEO_BACKGROUND).forEach((element) => {
        const source = sourceOf(element);
        if (bound.has(element) || !isSelfHosted(source)) {
            return;
        }

        bound.add(element);
        const video = layerFor(element, view);

        if (typeof view.IntersectionObserver !== "function" || !playsOnlyWhenVisible(element)) {
            play(video, source);
            return;
        }

        const observer = new view.IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    play(video, source);
                    return;
                }

                video.pause?.();
            });
        });
        observer.observe(element);
    });
}

enhanceVideoBackgrounds();
