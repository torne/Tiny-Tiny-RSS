/* http://textsnippets.com/posts/show/835 */

Position.GetWindowSize = function(w) {
        w = w ? w : window;
        var width = w.innerWidth || (w.document.documentElement.clientWidth || w.document.body.clientWidth);
        var height = w.innerHeight || (w.document.documentElement.clientHeight || w.document.body.clientHeight);
        return [width, height];
};

/* http://textsnippets.com/posts/show/836 */

Position.Center = function(element, parent) {
        var w, h, pw, ph;
        var d = Element.getDimensions(element);
        w = d.width;
        h = d.height;
        Position.prepare();
        if (!parent) {
                var ws = Position.GetWindowSize();
                pw = ws[0];
                ph = ws[1];
        } else {
                pw = parent.offsetWidth;
                ph = parent.offsetHeight;
        }
        element.style.top = (ph/2) - (h/2) -  Position.deltaY + "px";
        element.style.left = (pw/2) - (w/2) -  Position.deltaX + "px";
};



