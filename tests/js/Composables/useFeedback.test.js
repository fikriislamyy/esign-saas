import { useFeedback } from "@/Composables/useFeedback";

describe("useFeedback", () => {
    beforeEach(() => {
        const { hideLoading, closeFeedback, closeConfirmation } = useFeedback();
        hideLoading();
        closeFeedback();
        closeConfirmation();
    });

    it("shares one state between callers", () => {
        const a = useFeedback();
        const b = useFeedback();

        a.showLoading("Saving…");

        expect(b.loading.value).toBe(true);
        expect(b.loadingText.value).toBe("Saving…");
    });

    it("showLoading sets the loading text", () => {
        const feedback = useFeedback();

        feedback.showLoading("Uploading...");

        expect(feedback.loading.value).toBe(true);
        expect(feedback.loadingText.value).toBe("Uploading...");
    });

    it("showLoading uses default text when not provided", () => {
        const feedback = useFeedback();

        feedback.showLoading();

        expect(feedback.loading.value).toBe(true);
        expect(feedback.loadingText.value).toBe("Loading...");
    });

    it("hideLoading clears the loading state", () => {
        const feedback = useFeedback();
        feedback.showLoading();

        feedback.hideLoading();

        expect(feedback.loading.value).toBe(false);
    });

    it("showSuccess opens a success dialog with given copy", () => {
        const feedback = useFeedback();

        feedback.showSuccess("Saved successfully", "Success!", "OK");

        expect(feedback.feedbackOpen.value).toBe(true);
        expect(feedback.feedbackType.value).toBe("success");
        expect(feedback.feedbackTitle.value).toBe("Success!");
        expect(feedback.feedbackMessage.value).toBe("Saved successfully");
        expect(feedback.feedbackButtonText.value).toBe("OK");
    });

    it("showSuccess uses default title and button text", () => {
        const feedback = useFeedback();

        feedback.showSuccess("Done");

        expect(feedback.feedbackType.value).toBe("success");
        expect(feedback.feedbackTitle.value).toBe("Success");
        expect(feedback.feedbackButtonText.value).toBe("Continue");
        expect(feedback.feedbackMessage.value).toBe("Done");
    });

    it("showError opens an error dialog with given copy", () => {
        const feedback = useFeedback();

        feedback.showError("Something broke", "Error", "Retry");

        expect(feedback.feedbackOpen.value).toBe(true);
        expect(feedback.feedbackType.value).toBe("error");
        expect(feedback.feedbackTitle.value).toBe("Error");
        expect(feedback.feedbackMessage.value).toBe("Something broke");
        expect(feedback.feedbackButtonText.value).toBe("Retry");
    });

    it("showError uses default title and button text", () => {
        const feedback = useFeedback();

        feedback.showError("Oops");

        expect(feedback.feedbackType.value).toBe("error");
        expect(feedback.feedbackTitle.value).toBe("Something went wrong");
        expect(feedback.feedbackButtonText.value).toBe("Close");
        expect(feedback.feedbackMessage.value).toBe("Oops");
    });

    it("closeFeedback closes the feedback dialog", () => {
        const feedback = useFeedback();
        feedback.showSuccess("Saved");

        feedback.closeFeedback();

        expect(feedback.feedbackOpen.value).toBe(false);
    });

    it("showConfirmation opens a confirmation dialog with given copy", () => {
        const feedback = useFeedback();

        feedback.showConfirmation({
            title: "Delete user?",
            message: "This cannot be undone.",
            confirmText: "Delete",
            cancelText: "Keep",
        });

        expect(feedback.confirmationOpen.value).toBe(true);
        expect(feedback.confirmationTitle.value).toBe("Delete user?");
        expect(feedback.confirmationMessage.value).toBe("This cannot be undone.");
        expect(feedback.confirmationButtonText.value).toBe("Delete");
        expect(feedback.confirmationCancelText.value).toBe("Keep");
    });

    it("showConfirmation uses default copy when not provided", () => {
        const feedback = useFeedback();

        feedback.showConfirmation();

        expect(feedback.confirmationOpen.value).toBe(true);
        expect(feedback.confirmationTitle.value).toBe("Are you sure?");
        expect(feedback.confirmationMessage.value).toBe("Please confirm this action.");
        expect(feedback.confirmationButtonText.value).toBe("Confirm");
        expect(feedback.confirmationCancelText.value).toBe("Cancel");
    });

    it("showConfirmation stores the onConfirm callback", () => {
        const feedback = useFeedback();
        const callback = vi.fn();

        feedback.showConfirmation({ onConfirm: callback });

        expect(typeof callback).toBe("function");
    });

    it("confirmAction runs the stored callback once", async () => {
        const feedback = useFeedback();
        const onConfirm = vi.fn();
        feedback.showConfirmation({ onConfirm });

        await feedback.confirmAction();

        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it("confirmAction closes the confirmation dialog", async () => {
        const feedback = useFeedback();
        feedback.showConfirmation({ onConfirm: () => {} });

        await feedback.confirmAction();

        expect(feedback.confirmationOpen.value).toBe(false);
    });

    it("confirmAction running twice calls the callback only once", async () => {
        const feedback = useFeedback();
        const onConfirm = vi.fn();
        feedback.showConfirmation({ onConfirm });

        await feedback.confirmAction();
        await feedback.confirmAction();

        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it("confirmAction with no callback does not crash", async () => {
        const feedback = useFeedback();
        feedback.showConfirmation({ onConfirm: null });

        await expect(feedback.confirmAction()).resolves.not.toThrow();
    });

    it("closeConfirmation closes the dialog", () => {
        const feedback = useFeedback();
        feedback.showConfirmation();

        feedback.closeConfirmation();

        expect(feedback.confirmationOpen.value).toBe(false);
    });

    it("closeConfirmation discards the callback", async () => {
        const feedback = useFeedback();
        const onConfirm = vi.fn();
        feedback.showConfirmation({ onConfirm });

        feedback.closeConfirmation();
        await feedback.confirmAction();

        expect(onConfirm).not.toHaveBeenCalled();
    });
});
