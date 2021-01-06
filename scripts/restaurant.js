import { initializeScrollFunction, scrollFunction } from "./scroll.js";
import { addPopup, updatePopupsOnMousemove, updatePopupsOnMouseclick, setPopupVisible } from "./popup.js";
    
window.onscroll = scrollFunction;
window.onmousedown = updatePopupsOnMouseclick;
document.addEventListener('mousemove', function(event) {
    updatePopupsOnMousemove(event);
}, false);

window.onload = function() {
    initializeScrollFunction();
    addPopup("popup-window", "popup-button", showPopupWindow, hidePopupWindow);
    addPopup("notifications", "notif-button", showNotifications, hideNotifications);
}

function showPopupWindow() {
    document.getElementById("popup-window").style.display = "inline-block";
}

function hidePopupWindow() {
    document.getElementById("popup-window").style.display = "none";
}

function showNotifications() {
    document.getElementById("notifications").style.display = "inline-block";
}

function hideNotifications() {
    document.getElementById("notifications").style.display = "none";
}

/*------------------------------------------------------------------*/

var slider = document.getElementById("item-sale"); // slider for sale item value 0-100
var new_price = document.getElementById("new-price"); // counted price
var orig_price = document.getElementById("item-price"); // input original price

// counts 
function getPrice(original, sale) {
    var price = original-original*sale/100;
    return price.toPrecision(3);
}

function onPriceOrSaleChange() {
    new_price.innerHTML = getPrice(orig_price.value, slider.value);
}
orig_price.oninput = onPriceOrSaleChange;
slider.oninput = onPriceOrSaleChange;

window.modifyItem = modifyItem;
function modifyItem(id) {
    // Read all the necessary values from specified item...
    var item = document.getElementById(id.toString());

    var name         = item.querySelector(".name").innerHTML;
    var orig_price   = item.querySelector(".price").getAttribute("data-original-price");
    var sale         = item.querySelector(".sale").innerHTML;
    var quantity     = item.querySelector(".quantity").innerHTML;
    var tags         = item.querySelector(".tags").getElementsByTagName("li");
    var expiration   = item.querySelector(".expiration");

    var tag_ids = [];
    for (var i = 0; i != tags.length; ++i) {
        tag_ids.push(parseInt(tags[i].getAttribute("data-id")));
    }

    var year  = expiration.querySelector(".year").innerHTML;
    var month = expiration.querySelector(".month").innerHTML;
    var day   = expiration.querySelector(".day").innerHTML;
    
    var month_as_num = new Date(Date.parse(month + " 1, 2012")).getMonth() + 1;
    var date = year + '-' +
               (month_as_num < 10 ? "0" : "") + month_as_num + '-' +
               (day.length == 1 ? "0" : "") + day;
    var time = expiration.querySelector(".time").innerHTML;

    // ... and insert them into the add-item form:
    document.getElementById("item-name").value     = name;
    document.getElementById("item-price").value    = orig_price;
    document.getElementById("item-sale").value     = sale;
    document.getElementById("item-quantity").value = quantity;
    document.getElementById("item-image").required = false;
    document.getElementById("item-date").value     = date;
    document.getElementById("item-time").value     = time;
    
    document.getElementById("tag-no-red-meat").checked   = tag_ids.includes(1);
    document.getElementById("tag-no-white-meat").checked = tag_ids.includes(2);
    document.getElementById("tag-no-fish").checked       = tag_ids.includes(3);
    document.getElementById("tag-no-gluten").checked     = tag_ids.includes(4);
    document.getElementById("tag-no-dairy").checked      = tag_ids.includes(5);
    
    // Insert item ID into hidden form field:
    var elems = document.querySelectorAll(".modified_item_id");
    for (var i = 0; i != elems.length; ++i)
        elems[i].value = id;

    // Adjust other UI elements:
    setPopupVisible("popup-window", true);
    onPriceOrSaleChange();

    // rename the heading
    var in_heading = document.getElementById("popup-heading");
    in_heading.innerHTML = "Edit item:";

    // different text for add button
    var b_add = document.getElementById("b_add");
    b_add.value = "Save changes";

    // add discard button
    var b_discard = document.getElementById("b_discard");
    b_discard.style.display = "inline-block";

    // add delete button
    var b_delete = document.getElementById("b_delete");
    b_delete.style.display = "inline-block";
}

window.abortItemModification = abortItemModification;
function abortItemModification() {
    var form = document.getElementById("popup-form");
    form.reset();
    document.getElementById("item-image").required = true;

    // Remove item ID from hidden form field:
    var elems = document.querySelectorAll(".modified_item_id");
    for (var i = 0; i != elems.length; ++i)
        elems[i].value = "";

    // Adjust other UI elements:
    setPopupVisible("popup-window", false);
    onPriceOrSaleChange();

    // reset the heading
    var in_heading = document.getElementById("popup-heading");
    in_heading.innerHTML = "Your new food item:";

    // default text for add button
    var b_add = document.getElementById("b_add");
    b_add.value = "Add item";

    // hide discard button
    var b_discard = document.getElementById("b_discard");
    b_discard.style.display = "none";
    
    // hide delete button
    var b_delete = document.getElementById("b_delete");
    b_delete.style.display = "none";
}