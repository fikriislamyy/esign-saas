import { ref } from "vue";
import { valueUpdater } from "@/Components/data-table/utils";

describe("valueUpdater", () => {
    it("sets a ref to a plain value", () => {
        const myRef = ref(10);

        valueUpdater(20, myRef);

        expect(myRef.value).toBe(20);
    });

    it("calls a function updater with the current value", () => {
        const myRef = ref(5);
        const updater = vi.fn((v) => v * 2);

        valueUpdater(updater, myRef);

        expect(updater).toHaveBeenCalledWith(5);
        expect(myRef.value).toBe(10);
    });

    it("works with ref holding an object", () => {
        const myRef = ref({ count: 0 });

        valueUpdater({ count: 5 }, myRef);

        expect(myRef.value).toEqual({ count: 5 });
    });

    it("works with function updater on object ref", () => {
        const myRef = ref({ name: "Alice", age: 30 });
        const updater = (obj) => ({ ...obj, age: 31 });

        valueUpdater(updater, myRef);

        expect(myRef.value).toEqual({ name: "Alice", age: 31 });
    });

    it("can update string values", () => {
        const myRef = ref("hello");

        valueUpdater("world", myRef);

        expect(myRef.value).toBe("world");
    });

    it("can update with function returning string", () => {
        const myRef = ref("hello");
        const updater = (s) => s.toUpperCase();

        valueUpdater(updater, myRef);

        expect(myRef.value).toBe("HELLO");
    });

    it("handles null values", () => {
        const myRef = ref(null);

        valueUpdater(42, myRef);

        expect(myRef.value).toBe(42);
    });

    it("can set to null", () => {
        const myRef = ref(42);

        valueUpdater(null, myRef);

        expect(myRef.value).toBeNull();
    });
});
