// Makes sure the confirm_password input matches with password.

var password = document.getElementById("password");
var confirm_password = document.getElementById("confirm_password");

password.onchange = validatePassword;
confirm_password.onkeyup = validatePassword;

function validatePassword() {
    // If passwords don't match, give red background:
    if(password.value != confirm_password.value) {
        confirm_password.setCustomValidity("Passwords Don't Match");
        confirm_password.setAttribute("style", "background-color: var(--warning-color)");
    } else { // Otherwise, default background:
        confirm_password.setCustomValidity('');
        confirm_password.setAttribute("style", "background-color: var(--light-color)");
    }
}