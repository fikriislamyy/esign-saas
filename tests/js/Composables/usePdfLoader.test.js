import axios from "axios";
import * as pdfjsLib from "pdfjs-dist";
import { loadPdf } from "@/Composables/usePdfLoader";

vi.mock("axios");
vi.mock("pdfjs-dist");

describe("usePdfLoader", () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it("gets the pdf from the given url", async () => {
        axios.get.mockResolvedValue({ data: { data: "SGVsbG8=" } });
        pdfjsLib.getDocument.mockReturnValue({ promise: Promise.resolve("PDF") });

        await loadPdf("https://example.com/doc.pdf");

        expect(axios.get).toHaveBeenCalledWith("https://example.com/doc.pdf");
    });

    it("decodes base64 data to uint8array with correct bytes", async () => {
        const base64 = btoa("hello");
        axios.get.mockResolvedValue({ data: { data: base64 } });
        pdfjsLib.getDocument.mockReturnValue({ promise: Promise.resolve("PDF") });

        await loadPdf("https://example.com/doc.pdf");

        const callArg = pdfjsLib.getDocument.mock.calls[0][0];
        expect(callArg.data).toEqual(new Uint8Array([104, 101, 108, 108, 111]));
    });

    it("returns the pdf.js document promise", async () => {
        axios.get.mockResolvedValue({ data: { data: "SGVsbG8=" } });
        const pdfDoc = { numPages: 5 };
        pdfjsLib.getDocument.mockReturnValue({ promise: Promise.resolve(pdfDoc) });

        const result = await loadPdf("https://example.com/doc.pdf");

        expect(result).toEqual(pdfDoc);
    });

    it("propagates axios errors", async () => {
        const error = new Error("Network error");
        axios.get.mockRejectedValue(error);

        await expect(loadPdf("https://example.com/doc.pdf")).rejects.toThrow(
            "Network error"
        );
    });

    it("handles empty base64 data", async () => {
        axios.get.mockResolvedValue({ data: { data: "" } });
        pdfjsLib.getDocument.mockReturnValue({ promise: Promise.resolve("PDF") });

        await loadPdf("https://example.com/doc.pdf");

        const callArg = pdfjsLib.getDocument.mock.calls[0][0];
        expect(callArg.data).toEqual(new Uint8Array([]));
    });

    it("handles base64 with special characters", async () => {
        const base64 = btoa("hello+world/test=");
        axios.get.mockResolvedValue({ data: { data: base64 } });
        pdfjsLib.getDocument.mockReturnValue({ promise: Promise.resolve("PDF") });

        await loadPdf("https://example.com/doc.pdf");

        expect(pdfjsLib.getDocument).toHaveBeenCalled();
    });
});
