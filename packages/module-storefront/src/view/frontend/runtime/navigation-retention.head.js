(function (doc, nav) {
    "use strict";

    var script = doc.currentScript;
    var marker = script && script.getAttribute("data-marker");
    var activation = nav && nav.activation;
    if (!marker || !activation || !activation.from || activation.navigationType === "reload") {
        return;
    }

    var link = doc.createElement("link");
    link.setAttribute("rel", "expect");
    link.setAttribute("href", "#" + marker);
    link.setAttribute("blocking", "render");
    doc.head.appendChild(link);
})(document, window.navigation);
