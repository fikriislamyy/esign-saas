import { ref } from "vue";

const loading = ref(false);
const loadingText = ref("Loading...");

const feedbackOpen = ref(false);
const feedbackType = ref("success");
const feedbackTitle = ref("");
const feedbackMessage = ref("");
const feedbackButtonText = ref("Continue");

const confirmationOpen = ref(false);
const confirmationTitle = ref("Are you sure?");
const confirmationMessage = ref("");
const confirmationButtonText = ref("Confirm");
const confirmationCancelText = ref("Cancel");

let confirmationAction = null;

export function useFeedback() {
    function showLoading(text = "Loading...") {
        loadingText.value = text;
        loading.value = true;
    }

    function hideLoading() {
        loading.value = false;
    }

    function showSuccess(message, title = "Success", buttonText = "Continue") {
        feedbackType.value = "success";
        feedbackTitle.value = title;
        feedbackMessage.value = message;
        feedbackButtonText.value = buttonText;
        feedbackOpen.value = true;
    }

    function showError(
        message,
        title = "Something went wrong",
        buttonText = "Close",
    ) {
        feedbackType.value = "error";
        feedbackTitle.value = title;
        feedbackMessage.value = message;
        feedbackButtonText.value = buttonText;
        feedbackOpen.value = true;
    }

    function showConfirmation({
        title = "Are you sure?",
        message = "Please confirm this action.",
        confirmText = "Confirm",
        cancelText = "Cancel",
        onConfirm = null,
    } = {}) {
        confirmationTitle.value = title;
        confirmationMessage.value = message;
        confirmationButtonText.value = confirmText;
        confirmationCancelText.value = cancelText;

        confirmationAction = onConfirm;

        confirmationOpen.value = true;
    }

    function closeFeedback() {
        feedbackOpen.value = false;
    }

    function closeConfirmation() {
        confirmationOpen.value = false;
        confirmationAction = null;
    }

    async function confirmAction() {
        const action = confirmationAction;

        closeConfirmation();

        if (typeof action === "function") {
            await action();
        }
    }

    return {
        loading,
        loadingText,

        feedbackOpen,
        feedbackType,
        feedbackTitle,
        feedbackMessage,
        feedbackButtonText,

        confirmationOpen,
        confirmationTitle,
        confirmationMessage,
        confirmationButtonText,
        confirmationCancelText,

        showLoading,
        hideLoading,

        showSuccess,
        showError,

        showConfirmation,

        closeFeedback,
        closeConfirmation,
        confirmAction,
    };
}
