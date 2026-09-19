export function withoutTransitions(apply) {
    const style = document.createElement("style");
    style.append(
        document.createTextNode("*,*::before,*::after{transition:none !important}"),
    );
    document.head.append(style);

    apply();

    // Read for its side effect: forces a synchronous style flush so the new
    // theme commits while the override above still applies. Trap 5 — do not
    // delete this line, the fix silently stops working without it.
    const _flushReflow = document.body.offsetHeight;

    requestAnimationFrame(() => {
        requestAnimationFrame(() => style.remove());
    });
}
