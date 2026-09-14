import axios from "axios";
import * as pdfjsLib from "pdfjs-dist";

function base64ToBytes(base64) {
    const binary = atob(base64);

    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

// Fetches the PDF as JSON/base64 so download managers don't intercept it,
// then hands raw bytes to pdf.js instead of a URL.
export async function loadPdf(url) {
    const response = await axios.get(url);

    const bytes = base64ToBytes(response.data.data);

    return pdfjsLib.getDocument({ data: bytes }).promise;
}
