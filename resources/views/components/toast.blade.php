<div
    x-data="{ show: false, message: '' }"
    x-on:toast.window="
        message = $event.detail.message;
        show = true;
        clearTimeout(window.__toastTimeout);
        window.__toastTimeout = setTimeout(() => { show = false }, 3400);
    "
    class="toast"
    :class="{ show: show }"
    x-text="message"
></div>
