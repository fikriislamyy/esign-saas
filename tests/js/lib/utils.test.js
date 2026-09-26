import { cn } from "@/lib/utils";

describe("cn", () => {
    it("merges class names", () => {
        expect(cn("p-2", "text-sm")).toBe("p-2 text-sm");
    });

    it("lets the last Tailwind conflict win", () => {
        expect(cn("p-2", "p-4")).toBe("p-4");
    });

    it("drops falsy values", () => {
        expect(cn("p-2", false, null, undefined, "", "m-1")).toBe("p-2 m-1");
    });
});
