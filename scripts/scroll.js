// Responsible for making sure that fixed element won't overlap with the footer. All such elements must have a class of "fixed".

var FIXED_ELEMENTS = [];

var FOOTER_HEIGHT = 0;

// Adds all elements with the class "fixed" to an array, to be kept track of.
export function initializeScrollFunction() {
    // Read global variables from CSS:
    var computed_style = window.getComputedStyle(document.documentElement);
    FOOTER_HEIGHT = parseInt(computed_style.getPropertyValue('--footer-height'), 10);

    // Create an array of fixed elements, with their initial positions (bottoms):
    var fixed_elements = document.getElementsByClassName("fixed");
    for (var i = 0; i != fixed_elements.length; i++) {
        FIXED_ELEMENTS.push([
            fixed_elements[i],
            parseInt(window.getComputedStyle(fixed_elements[i]).getPropertyValue("bottom"), 10)
        ]);
    }
    scrollFunction();
}

// Assure fixed elements don't overlap with the footer:
export function scrollFunction() {
    var distance_from_bottom = document.body.scrollHeight - window.innerHeight - window.scrollY;
    var extra_margin = FOOTER_HEIGHT - distance_from_bottom;
    if (extra_margin < 0)
        extra_margin = 0;

    for (var i = 0; i != FIXED_ELEMENTS.length; i++) {
        var bottom = FIXED_ELEMENTS[i][1] + extra_margin;
        FIXED_ELEMENTS[i][0].style.bottom = bottom.toString() + "px";
    }
}