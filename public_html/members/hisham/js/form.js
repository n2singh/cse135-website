document.addEventListener("DOMContentLoaded", () => {

    const form = document.querySelector("form");
    const errorOutput = document.getElementById("error-output");
    const infoOutput  = document.getElementById("info-output");
    const formErrorsInput = document.getElementById("form-errors");
    let form_errors = [];
    const allowedMap = {
        name: /^[A-Za-z '.-]$/,
        topic: /^[A-Za-z0-9 .,!?'\-]$/,
        email: /^[A-Za-z0-9@._\-+]$/,
        comments: null   // comments accepts all characters
    };

    document.querySelectorAll("input, textarea").forEach(input => {
        input.addEventListener("beforeinput", (e) => {
            if (!e.data) return;

            const rule = allowedMap[input.name];
            if (!rule) return;

            if (!rule.test(e.data)) {
                flash(input);
                showTemporaryMessage(`Illegal character "${e.data}" in ${input.name}`);
            }
        });
    });

    function flash(el) {
        el.classList.add("flash-illegal");
        setTimeout(() => el.classList.remove("flash-illegal"), 500);
    }

    function showTemporaryMessage(msg) {
        errorOutput.textContent = msg;
        setTimeout(() => (errorOutput.textContent = ""), 1500);
    }

    const msgBox = document.getElementById("msg");
    const counter = document.getElementById("message-counter");

    msgBox.addEventListener("input", () => {
        const max = msgBox.maxLength;
        const remaining = max - msgBox.value.length;

        counter.textContent = `${remaining} characters remaining`;

        if (remaining <= 5)       counter.style.color = "#ff4d4d";  // red
        else if (remaining <= 50) counter.style.color = "yellow";   // yellow
        else                      counter.style.color = "lightgreen";
    });

    function applyCustomMessages(input) {
        let v = input.value.trim();

        switch (input.name) {
            case "name": {
                // 1. Must start with uppercase A–Z
                if (!/^[A-Z]/.test(v)) {
                    input.setCustomValidity("Name must start with an uppercase letter (A-Z).");
                    break;
                }

                // 2. Only allowed characters
                if (!/^[A-Za-z .,'-]+$/.test(v)) {
                    input.setCustomValidity(
                        "Name may only contain letters, spaces, commas, periods, and hyphens."
                    );
                    break;
                }

                // 3. Minimum length
                if (v.length < 2) {
                    input.setCustomValidity("Name must be at least 2 characters long.");
                    break;
                }

                // If all checks pass
                input.setCustomValidity("");
                break;
            }

            case "email":
                if (!v.includes("@"))
                    input.setCustomValidity("Email must contain '@'.");
                else if (!/^[^@]+@[^@]+\.[^@]+$/.test(v))
                    input.setCustomValidity("Email must have a valid domain (example: user@site.com).");
                else
                    input.setCustomValidity("");
                break;

            case "topic":
                if (v.length < 2)
                    input.setCustomValidity("Topic must be at least 2 characters.");
                else if (!/^[A-Za-z0-9 .,!?'\-]+$/.test(v))
                    input.setCustomValidity("Topic may contain only letters, digits, spaces, punctuation.");
                else
                    input.setCustomValidity("");
                break;

            case "comments":
                if (v.length < 5)
                    input.setCustomValidity("Message must be at least 5 characters.");
                else
                    input.setCustomValidity("");
                break;
        }
    }

    const progressEl = document.querySelector("progress");

    document.querySelectorAll("input, textarea").forEach(input => {
        input.addEventListener("input", () => {
            applyCustomMessages(input);
             if (progressEl && input.required) {
            const prevValid = input.dataset.wasValid === "true";
            const nowValid  = input.checkValidity();

            if (!prevValid && nowValid) progressEl.value++;
            if (prevValid && !nowValid) progressEl.value--;

            input.dataset.wasValid = String(nowValid);
        }
        });
    });

    form.addEventListener("submit", (e) => {
        errorOutput.textContent = "";
        infoOutput.textContent  = "";

        let blockSubmit = false;

        document.querySelectorAll("input, textarea").forEach(input => {
            applyCustomMessages(input);   // refresh custom message

            if (!input.checkValidity()) {
                blockSubmit = true;

                // log error in form_errors
                form_errors.push({
                    field: input.name,
                    value: input.value,
                    reason: input.validationMessage
                });
            }
        });

        if (blockSubmit) {
            e.preventDefault();
            formErrorsInput.value = JSON.stringify(form_errors);
            errorOutput.textContent = "Please correct the red highlighted fields.";
            form.reportValidity();
            return;
        }
        formErrorsInput.value = JSON.stringify(form_errors);
        infoOutput.textContent = "Form submitted successfully!";
    });
});