import { withoutTransitions } from "@/lib/theme-transitions";

describe("withoutTransitions", () => {
    it("calls the apply callback", () => {
        const apply = vi.fn();

        withoutTransitions(apply);

        expect(apply).toHaveBeenCalledTimes(1);
    });

    it("adds a style with transition:none while apply runs", () => {
        let styleAdded = false;

        withoutTransitions(() => {
            const styles = Array.from(document.head.querySelectorAll("style"));
            styleAdded = styles.some((s) =>
                s.textContent.includes("transition:none")
            );
        });

        expect(styleAdded).toBe(true);
    });

    it("reads offsetHeight to flush styles", () => {
        const spy = vi.spyOn(HTMLElement.prototype, "offsetHeight", "get");

        withoutTransitions(() => {});

        expect(spy).toHaveBeenCalled();
        spy.mockRestore();
    });

    it("schedules style element removal via requestAnimationFrame", () => {
        const rAF = vi.spyOn(window, "requestAnimationFrame");

        withoutTransitions(() => {});

        expect(rAF).toHaveBeenCalled();
        rAF.mockRestore();
    });
});
