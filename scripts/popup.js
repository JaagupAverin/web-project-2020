// Provides assisting functions for dealing with popup windows.

function Popup(button_id, on_display_function, on_hide_function) {
    this.enabled = true;
    this.visible = false;

    this.button_id = button_id;
    this.on_display_function = on_display_function;
    this.on_hide_function = on_hide_function;
}

// Consists of { id: Popup } pairs.
var POPUPS = {};

// Element with popup_id will be displayed/hidden automatically.
// on_display_function() will be called if element button_id is clicked.
// on_hide_function() will be called if element button_id is clicked again OR
// when the mouse clicks outside of the popup area.
export function addPopup(popup_id, button_id, on_display_function, on_hide_function) {
    POPUPS[popup_id] = new Popup(button_id, on_display_function, on_hide_function);
}

var MOUSE = {};
// Must be called whenever the mouse moves.
export function updatePopupsOnMousemove(event) {
    MOUSE.x = event.clientX;
    MOUSE.y = event.clientY;
}

// Must be called whenever the mouse is clicked.
export function updatePopupsOnMouseclick() {
    for (var popup_id in POPUPS) {
        updatePopupOnMouseclick(popup_id);
    }
}

// Used to manually enable or disable the popup. If disabled, popup button will not work. Edge cases only.
export function setPopupEnabled(popup_id, enabled) {
    POPUPS[popup_id].enabled = enabled;
    if (!enabled)
        setPopupVisible(popup_id, false);
}

export function setPopupVisible(popup_id, visible) {
    var popup = POPUPS[popup_id];

    if (visible == popup.visible)
        return;

    if (visible) {
        popup.on_display_function();
        popup.visible = true;
    }
    else {
        popup.on_hide_function();
        popup.visible = false;
        popup.mouse_on_popup = false;
    }
}

function updatePopupOnMouseclick(popup_id) {
    var popup = POPUPS[popup_id];

    if (!popup.enabled)
        return;

    var mouse_on_button = false;
    var mouse_on_popup = false;

    var popup_rect = document.getElementById(popup_id).getBoundingClientRect();
    var button_rect = document.getElementById(popup.button_id).getBoundingClientRect();

    if (popup.visible) {
        if (MOUSE.x >= popup_rect.left && MOUSE.x <= popup_rect.right &&
            MOUSE.y >= popup_rect.top && MOUSE.y <= popup_rect.bottom)
            mouse_on_popup = true;
        else
            mouse_on_popup = false;
    }

    if (MOUSE.x >= button_rect.left && MOUSE.x <= button_rect.right &&
        MOUSE.y >= button_rect.top && MOUSE.y <= button_rect.bottom)
        mouse_on_button = true;
    else
        mouse_on_button = false;

    if (mouse_on_button)
        setPopupVisible(popup_id, !popup.visible);
    else if (!mouse_on_popup)
        setPopupVisible(popup_id, false);
}