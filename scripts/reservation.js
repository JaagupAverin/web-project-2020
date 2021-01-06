// Keeps track of checkboxes within the table of active foods.
// 
// Checkboxes only exist if the table of active foods is present.
// Therefore their presence must be verified.

window.onload = function() {
    // Due to lazy programming, the header of the table also has a checkbox. Remove it:
    var redundant_checkbox = document.getElementById("redundant_checkbox");
    if (redundant_checkbox)
        redundant_checkbox.parentElement.remove();

    // Due to lazy programming, the footer of the table also has a checkbox.
    // Turn it into a "select_all" feature:
    var check_all_checkbox = document.getElementById("check_all");
    if (check_all_checkbox)
        check_all_checkbox.setAttribute('name', 'check_all');

    onCheckboxChange();
};

// This checkbox will automatically check or uncheck all other checkboxes.
const check_all_checkbox = document.getElementById('check_all');
if (check_all_checkbox) {
    check_all_checkbox.addEventListener('change', (event) => {
        checkboxes = document.getElementsByName('food_ids[]');
        for (var i = 0, n = checkboxes.length; i < n; i++) {
            checkboxes[i].checked = event.target.checked;
        }
    });
}

// Analyze every checkbox. If none are checked, disable the buttons (as they would have no purpose).
function onCheckboxChange() {
    var checkedState = getCheckedState();

    var cancel_button = document.getElementById("cancel_button");

    if (checkedState == 'any' || checkedState == 'all') { // Enable button
        cancel_button.style.pointerEvents = "all";
        cancel_button.style.backgroundColor = "rgba(0, 0, 0, 10%)";
        cancel_button.style.color = "rgba(0, 0, 0, 60%)";
    }
    else { // Disable button
        cancel_button.style.pointerEvents = "none";
        cancel_button.style.backgroundColor = "rgba(0, 0, 0, 5%)";
        cancel_button.style.color = "rgba(0, 0, 0, 20%)";
    }

    var fulfill_button = document.getElementById("fulfill_button");
    if (fulfill_button) {
        if (checkedState == 'any' || checkedState == 'all') { // Enable button
            fulfill_button.style.pointerEvents = "all";
            fulfill_button.style.backgroundColor = "rgba(100, 140, 0, 30%)";
            fulfill_button.style.color = "rgba(0, 0, 0, 70%)";
        }
        else { // Disable button
            fulfill_button.style.pointerEvents = "none";
            fulfill_button.style.backgroundColor = "rgba(0, 0, 0, 5%)";
            fulfill_button.style.color = "rgba(0, 0, 0, 20%)";
        }   
    }

    if (check_all_checkbox) {
        if (checkedState == 'all')
            check_all_checkbox.checked = true;
        else
            check_all_checkbox.checked = false;
    }
}

const checkedState = {
    ALL: 'all',
    ANY: 'any',
    NONE: 'none'
}
// Returns whether all, any or no checkboxes are currently checked.
function getCheckedState() {
    var any = false;
    var all = true;

    checkboxes = document.getElementsByName('food_ids[]');
    if (checkboxes.length == 0)
        return checkedState.NONE;

    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked)
            any = true;
        else
            all = false;
    }

    if (all)
        return checkedState.ALL;
    else
        return any ? checkedState.ANY : checkedState.NONE;
}