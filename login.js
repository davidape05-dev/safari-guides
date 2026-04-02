document.addEventListener("DOMContentLoaded" , function () {
    document.querySelectorAll(".toggle-password")>forEach(function (eye) {
        eye.addEventListener("click", function () {
            const input = this.previousElementSibling;
            
            if (!input) {
                console.error("Password input not found");
                return;
            }

            input.type = input.type === "password" ? "text" : "password";

        });
    });
});