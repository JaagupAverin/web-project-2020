// Stores and loads filters. Handles the basket.
// quantities, filters and BAKET_ITEM_TEMPLATE variables are set in customer.php

import { initializeScrollFunction, scrollFunction } from "./scroll.js";
import { addPopup, setPopupEnabled, updatePopupsOnMousemove, updatePopupsOnMouseclick} from "./popup.js";
    
window.onscroll = scrollFunction;
window.onmousedown = updatePopupsOnMouseclick;
document.addEventListener('mousemove', function(event) {
    updatePopupsOnMousemove(event);
}, false);

window.onload = function () {
    initializeScrollFunction();

    addPopup("basket", "basket_toggle_button", displayBasket, hideBasket);
    setPopupEnabled("basket", false);

    var basket_expiration = localStorage.getItem("basket_expiration");
    if (basket_expiration) {
        var time = JSON.parse(basket_expiration);
        var now = new Date();
        if (now.getTime() >= time)
            localStorage.removeItem("basket");
    }

    loadBasketFromLocalStorage();
    updateHtmlToMatchBasket();

    // Insert currently applied filters into the form (UI):
    for (var key in filters) {
        if (key == 'query') {
            document.getElementById(key).value = filters[key];
        }
        else if (key == 'query_type') {
            if (filters[key] == 'food_name')
                document.getElementById("by_food").checked = true;
            else if (filters[key] == 'restaurant_name')
                document.getElementById("by_restaurant").checked = true;
        }
        else if (key == 'sort') {
            if (filters[key] == 'full_price')
                document.getElementById("cheaper_first").checked = true;
            else if (filters[key] == 'food_name')
                document.getElementById("alphabetical").checked = true;
        }
        else {
            if (filters[key] == "on")
                document.getElementById(key).checked = true;
        }
    }
    saveFiltersToCookies();
}

// Before redirecting to checkout.php, insert all of the values from basket into the POST form.
window.onCheckout = insertItemsToCheckoutForm;
function insertItemsToCheckoutForm() {
    var items = {};
    for (var key in basket) {
        items[key] = basket[key].quantity;
    }
    document.getElementById("checkout_list").value = JSON.stringify(items);
}

window.onFiltersSubmit = saveFiltersToCookies;
function saveFiltersToCookies() {
    filters['no_red_meats']      = document.getElementById('no_red_meats').checked   ? "on" : "off";
    filters['no_white_meats']    = document.getElementById('no_white_meats').checked ? "on" : "off";
    filters['no_fish']           = document.getElementById('no_fish').checked        ? "on" : "off";
    filters['no_gluten']         = document.getElementById('no_gluten').checked      ? "on" : "off";
    filters['no_dairy']          = document.getElementById('no_dairy').checked       ? "on" : "off";
    window.document.cookie = "filters=" + JSON.stringify(filters);
}

window.clearFilters = clearFilters;
function clearFilters() {
    document.cookie = 'filters=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    document.getElementById("filters_form").reset();
    document.getElementById("filters_form").submit();
}

/*------------------------------------------------------------------*/
// Basket
/*------------------------------------------------------------------*/

function displayBasket() {
    document.getElementById("basket").style.height = "var(--height)";
    
    var toggle_button = document.getElementById("basket_toggle_button");
    toggle_button.style.top = "5px";
    toggle_button.style.right = "5px";
    toggle_button.querySelector(".open").style.opacity = "0";
    toggle_button.querySelector(".close").style.opacity = "1";
}

function hideBasket() {
    document.getElementById("basket").style.height = "0";
    
    var toggle_button = document.getElementById("basket_toggle_button");
    toggle_button.style.top = "20px";
    toggle_button.style.right = "15px";
    toggle_button.querySelector(".open").style.opacity = "1";
    toggle_button.querySelector(".close").style.opacity = "0";
}

function setBasketButtonVisible(visible) {
    var basket_button = document.getElementById("basket_toggle_button");
    if (visible) {
        basket_button.style.pointerEvents = "all";
        basket_button.style.opacity = "1";
    }
    else {
        basket_button.style.pointerEvents = "none";
        basket_button.style.opacity = "0.6";
    }
}

// Consists of { id: BasketEntry } pairs.
// Saved to and loaded from localStorage if possible.
// Also inserted into the checkout form upon checkout.
var basket = {}

// listen for changes to localStorage
window.onstorage = function(e) {
    loadBasketFromLocalStorage();
    updateHtmlToMatchBasket();
};

function loadBasketFromLocalStorage() {
    var storage = localStorage.getItem("basket");
    basket = {}
    if (storage)
        basket = JSON.parse(storage);
    
    // Go over each item that was stored in localStorage and verify it is still available
    // according to the latest quantities fetched from the server.
    for (var id in basket) {
        var total_quantity = (quantities[id]) ? quantities[id] : 0; // food has been removed from DB -> 0
        if (basket[id].quantity > total_quantity) {
            basket[id].quantity = total_quantity;
            alert("Due to lack of quantity, an item has been removed from your basket: " + name);
            if (basket[id].quantity == 0)
                delete basket[id];
        }
    }

    var now = new Date();
    localStorage.setItem("basket_expiration", JSON.stringify(now.getTime() + 3600000)); // in 1 hour
}

function BasketEntry(name, price, quantity) {
    this.name = name;
    this.price = price;
    this.quantity = quantity;
}

function getPriceAndQuantityAsString(id) {
    var entry = basket[id];
    return entry.price.toFixed(2) + "€ (x" + entry.quantity + ")";
}

function getBasketTotalQuantity() {
    var result = 0;
    for (var id in basket) {
        result += basket[id].quantity;
    }
    return result;
}

function getBasketTotalPrice() {
    var result = 0;
    for (var id in basket) {
        result += basket[id].price * basket[id].quantity;
    }
    return result;
}

// In order to create new element in JS, you need to create a template and insert the HTML into it.
// This is an assist function for that purpose.
function htmlToElement(html) {
    var template = document.createElement('template');
    html = html.trim(); // Never return a text node of whitespace as the result
    template.innerHTML = html;
    return template.content.firstChild;
}
// Certain characters in HTML (such as <>) have special meaning and must be replaced before a string
// can safely be inserted into HTML.
function escapeHtml(text) {
    var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Modify HTML to match the data in 'basket' (and 'quantities').
function updateHtmlToMatchBasket() {
    // Update general quantities and prices in basket:
    document.querySelector("#basket_toggle_button #counter").textContent = getBasketTotalQuantity();
    document.querySelector("#basket #checkout_button").setAttribute("value", "Reserve\n(" + getBasketTotalPrice().toFixed(2) + "€)");

    // Disable basket if empty:
    if (getBasketTotalQuantity() == 0) {
        setPopupEnabled("basket", false);
        setBasketButtonVisible(false);
    }
    else {
        setPopupEnabled("basket", true);
        setBasketButtonVisible(true);
    }

    var basket_list = document.getElementById("basket_list");

    // Empty old list:
    while (basket_list.lastElementChild)
        basket_list.removeChild(basket_list.lastElementChild);
    
    // Generate new list of basket items:
    for (var id in basket) {
        var new_basket_item_html = BASKET_ITEM_TEMPLATE
            .replace("'%ID%'",  id)
            .replace("'%NAME%'", escapeHtml(basket[id].name))
            .replace("'%PRICE_AND_QUANTITY%'", getPriceAndQuantityAsString(id));

        var new_basket_item = htmlToElement(new_basket_item_html);
        basket_list.appendChild(new_basket_item);
    }

    // Update remaining quantities for all of the foods:
    for (var id in quantities) {
        var food_item = document.getElementById(id);
        if (!food_item) // HTML element may not be present due to filters.
            continue;

        var remaining_quantity = quantities[id];
        if (basket[id])
            remaining_quantity -= basket[id].quantity;

        var remaining_quantity_str = "";
        if (remaining_quantity < 100)
            remaining_quantity_str = remaining_quantity.toString();
        else
            remaining_quantity_str = "99+";
        food_item.querySelector(".quantity").textContent = "Quantity: " + remaining_quantity_str;

        // Disable purchase button if remaining quantity is zero:
        var disable = remaining_quantity == 0;
        food_item.querySelector(".order_tag").style.opacity = disable ? "0.5" : "1";
        food_item.querySelector(".quantity").style.opacity = disable ? "0.9" : "1";
        food_item.querySelector(".order_section").style.pointerEvents = disable ? "none" : "all";
        food_item.querySelector(".quantity").style.color = disable ? "var(--warning-color)" : "rgba(40, 40, 40, 40%)";
    }
}

// Adds a new entry or updates an existing entry within basket.
window.addToBasket = addToBasket;
function addToBasket(id, name, price, amount) {
    var new_quantity = amount;
    if (basket[id])
        new_quantity += basket[id].quantity;

    var total_quantity = (quantities[id]) ? quantities[id] : 0; // food has been removed from DB -> 0
    if (new_quantity > total_quantity) {
        new_quantity = total_quantity;
        alert("Due to lack of quantity, an item has been removed from your basket: " + name);
        if (new_quantity == 0)
            return;
    }

    if (basket[id])
        basket[id].quantity = new_quantity;
    else
        basket[id] = new BasketEntry(name, parseFloat(price.replace(',', '.')), new_quantity);
    localStorage.setItem("basket", JSON.stringify(basket));
    updateHtmlToMatchBasket();
}

// Removes a single entry from the basket.
// Removes the item from basket altogether if 0 entries remain.
window.removeFromBasket = removeFromBasket;
function removeFromBasket(id) {
    basket[id].quantity--;
    if (basket[id].quantity == 0)
        delete basket[id];
    localStorage.setItem("basket", JSON.stringify(basket));
    updateHtmlToMatchBasket();
}