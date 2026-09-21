import { mount } from "@vue/test-utils";
import FileDropzone from "@/Components/FileDropzone.vue";

describe("FileDropzone", () => {
    it("emits a dropped PDF file", async () => {
        const wrapper = mount(FileDropzone);
        const file = new File(["pdf"], "contract.pdf", { type: "application/pdf" });

        await wrapper.get("label").trigger("drop", { dataTransfer: { files: [file] } });

        expect(wrapper.emitted("select")).toEqual([[file]]);
    });

    it("rejects non-PDF files", async () => {
        const wrapper = mount(FileDropzone);
        const file = new File(["text"], "notes.txt", { type: "text/plain" });

        const input = wrapper.get('input[type="file"]');
        Object.defineProperty(input.element, "files", { value: [file] });

        await input.trigger("change");

        expect(wrapper.emitted("error")).toEqual([["Only PDF files can be uploaded."]]);
    });
});
