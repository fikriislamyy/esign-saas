import { navigation } from "@/Components/Layout/navigation";

describe("navigation", () => {
    it("gives every navigation item a title, icon, and route", () => {
        const items = navigation.flatMap((section) => section.items);

        for (const item of items) {
            expect(item.title).toEqual(expect.any(String));
            expect(item.icon).toBeTruthy();
            expect(item.route).toEqual(expect.any(String));
        }
    });

    it("does not contain duplicate routes", () => {
        const routes = navigation.flatMap((section) => section.items.map((item) => item.route));

        expect(new Set(routes).size).toBe(routes.length);
    });
});
