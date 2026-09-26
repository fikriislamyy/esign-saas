import { config } from "@vue/test-utils";
import { vi } from "vitest";

globalThis.route = vi.fn((name, params) => {
    const query = params ? "?" + new URLSearchParams(params).toString() : "";
    return `/${name}${query}`;
});
config.global.mocks.route = globalThis.route;

class IntersectionObserverStub {
    constructor(callback) {
        this.callback = callback;
    }
    observe(target) {
        this.callback([{ isIntersecting: true, target }], this);
    }
    unobserve() {}
    disconnect() {}
    takeRecords() {
        return [];
    }
}
globalThis.IntersectionObserver ??= IntersectionObserverStub;

globalThis.matchMedia ??= () => ({ matches: false, addEventListener() {}, removeEventListener() {} });
