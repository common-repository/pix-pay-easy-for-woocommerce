(() => {
    // this is for showing blink text - awaiting for payment.
    const blinkText = document.getElementById("blinkText");
    if (blinkText) {
        setInterval(() => {
            blinkText.style.visibility = (blinkText.style.visibility === "hidden") ? "visible" : "hidden";
        }, 500);
    }


    (() => {
        "use strict";
        //this is for copy qr link function
        const s = document.querySelectorAll(".payeasy-copy");
        if (s)
            for (let t = 0; t < s.length; t++)
                s[t].addEventListener("click", (t) => {
                    if ((t.preventDefault(), navigator && navigator.clipboard && navigator.clipboard.writeText)) {
                        navigator.clipboard.writeText(t.target.dataset.copy);
                        let e = t.target.nextElementSibling.textContent;
                        "Copied" !== e &&
                            ((t.target.nextElementSibling.textContent = "Copied"),
                                setTimeout(function () {
                                    t.target.nextElementSibling.textContent = e;
                                }, 1e3));
                    }
                });

        //this is for checking the payment status every 20s.
        jQuery(function (t) {
            !(function e() {
                "wc-completed" != payeasy_params.status &&
                    t
                        .ajax({ url: woocommerce_params.ajax_url, type: "POST", data: { action: "payeasycheck", order_id: payeasy_params.order_id, _ajax_nonce: payeasy_params.payeasy_ajax_check_nonce } })
                        .done(function (t) {
                            (((payeasy_params.order_status_paid == t || "wc-completed" == t) && payeasy_params.order_status_pending == payeasy_params.status)) && location.reload(!0);
                        })
                        .always(function (t) {
                            setTimeout(e, 20000);
                        });
            })();
        });
    })();
})();
