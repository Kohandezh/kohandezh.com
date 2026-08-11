/*!
 * jquery.shim.js — minimal jQuery-compatibility shim for kohandezh.com
 *
 * Replaces the 39 KB jquery.min.js with a ~3 KB subset implementing ONLY the
 * API surface actually used by this site's own scripts (main.js, gsapAnimation.js,
 * countto.js, carousel.js, animation-change-text.js) plus a native form-validation
 * replacement for the dropped 26 KB jquery-validate.js.
 *
 * NOT a general jQuery replacement — covers exactly the methods called on this site.
 * Exposes both `jQuery` and `$` globals. All plugin files (countto.js, …) keep
 * working unchanged because they attach to jQuery.fn like the real library.
 */
(function (global) {
    "use strict";

    // ---- internal data cache (jQuery-style, keyed by element) ----
    var dataCache = new WeakMap();
    var uid = 0;

    function getData(el, key) {
        var store = dataCache.get(el);
        if (store && key in store) return store[key];
        // fall back to data-* attribute (try exact, then camelCased)
        var camel = key.replace(/-([a-z])/g, function (_, c) { return c.toUpperCase(); });
        if (el.dataset) {
            if (key in el.dataset) return el.dataset[key];
            if (camel in el.dataset) return el.dataset[camel];
        }
        return el.getAttribute("data-" + key);
    }
    function setData(el, key, val) {
        var store = dataCache.get(el);
        if (!store) { store = {}; dataCache.set(el, store); }
        store[key] = val;
    }
    function removeData(el, key) {
        var store = dataCache.get(el);
        if (store) delete store[key];
    }

    // ---- event store for .off / .triggerHandler ----
    var eventsForOff = new WeakMap();
    function recordEvent(el, type, wrapped) {
        var m = eventsForOff.get(el);
        if (!m) { m = {}; eventsForOff.set(el, m); }
        (m[type] || (m[type] = [])).push(wrapped);
    }

    function camelProp(name) {
        return name.replace(/-([a-z])/g, function (_, c) { return c.toUpperCase(); });
    }

    // ---- HTML string → element ----
    function createHtml(html) {
        var selfClosing = /^<([a-zA-Z][\w-]*)\s*\/>$/;
        var m = html.trim().match(selfClosing);
        var tpl = m ? "<" + m[1] + "></" + m[1] + ">" : html.trim();
        var tmp = document.createElement("div");
        tmp.innerHTML = tpl;
        return tmp.firstElementChild || tmp.firstChild;
    }

    function setAttrs(el, attrs) {
        for (var k in attrs) {
            if (!Object.prototype.hasOwnProperty.call(attrs, k)) continue;
            var v = attrs[k];
            if (k === "class" || k === "className") el.className = v;
            else if (k === "text") el.textContent = v;
            else if (k === "html") el.innerHTML = v;
            else if (v !== null && v !== undefined) el.setAttribute(k, v);
        }
    }

    function toArr(obj) {
        var out = [];
        if (!obj) return out;
        if (obj.length == null) { out.push(obj); return out; }
        for (var i = 0; i < obj.length; i++) if (obj[i] != null) out.push(obj[i]);
        return out;
    }

    // ---- the constructor ----
    function jQuery(selector) {
        if (!(this instanceof jQuery)) return new jQuery(selector);
        this.length = 0;

        // $(fn) → DOM ready
        if (typeof selector === "function") {
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function () { selector(jQuery); });
            } else {
                selector(jQuery);
            }
            return this;
        }
        if (!selector) return this;

        // $(DOMElement) / $(window) / $(document)
        if (selector.nodeType || selector === window) {
            this[0] = selector; this.length = 1; return this;
        }
        // $([[DOMElements]]) / $(NodeList) / $(jQuery)
        if (typeof selector === "object") {
            if (selector instanceof jQuery) {
                for (var i = 0; i < selector.length; i++) { this[i] = selector[i]; }
                this.length = selector.length; return this;
            }
            var arr = toArr(selector);
            for (var j = 0; j < arr.length; j++) { this[j] = arr[j]; }
            this.length = arr.length; return this;
        }

        // string → either HTML creation or CSS selector
        var str = String(selector).trim();
        if (str.charAt(0) === "<") {
            var el = createHtml(str);
            if (el) {
                var attrs = arguments[1];
                if (attrs && typeof attrs === "object" && !(attrs instanceof jQuery)) setAttrs(el, attrs);
                this[0] = el; this.length = 1;
            }
            return this;
        }
        var root = document;
        var ctx = arguments[1];
        if (ctx) root = (ctx.nodeType ? ctx : (ctx[0] || document));
        try {
            var list = root.querySelectorAll(str);
            for (var k = 0; k < list.length; k++) { this[k] = list[k]; }
            this.length = list.length;
        } catch (e) { /* invalid selector → empty set */ }
        return this;
    }

    var fn = jQuery.prototype = jQuery.fn = {
        constructor: jQuery,
        length: 0,
        splice: Array.prototype.splice
    };

    function pushStack(obj, nodes) {
        var ret = jQuery(nodes);
        ret.prevObject = obj;
        return ret;
    }

    // ---- traversal ----
    fn.find = function (sel) {
        var found = [];
        for (var i = 0; i < this.length; i++) {
            var base = this[i];
            if (!base || !base.querySelectorAll) continue;
            var nl = base.querySelectorAll(sel);
            for (var j = 0; j < nl.length; j++) found.push(nl[j]);
        }
        return pushStack(this, dedupe(found));
    };
    fn.closest = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var el = this[i];
            if (!el) continue;
            var match = el.closest ? el.closest(sel) : null;
            if (match && match !== document) out.push(match);
        }
        return pushStack(this, dedupe(out));
    };
    fn.parent = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var p = this[i] && this[i].parentElement;
            if (p && (!sel || p.matches(sel))) out.push(p);
        }
        return pushStack(this, dedupe(out));
    };
    fn.parents = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var el = this[i] && this[i].parentElement;
            while (el && el !== document) {
                if (!sel || el.matches(sel)) out.push(el);
                el = el.parentElement;
            }
        }
        return pushStack(this, dedupe(out));
    };
    fn.children = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var el = this[i];
            if (!el || !el.children) continue;
            for (var j = 0; j < el.children.length; j++) {
                var c = el.children[j];
                if (!sel || c.matches(sel)) out.push(c);
            }
        }
        return pushStack(this, dedupe(out));
    };
    fn.eq = function (i) {
        i = +i;
        var el = i < 0 ? this[this.length + i] : this[i];
        return pushStack(this, el ? [el] : []);
    };
    fn.first = function () { return this.eq(0); };
    fn.last = function () { return this.eq(this.length - 1); };
    fn.next = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var n = this[i] && this[i].nextElementSibling;
            if (n && (!sel || n.matches(sel))) out.push(n);
        }
        return pushStack(this, dedupe(out));
    };
    fn.prev = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var n = this[i] && this[i].previousElementSibling;
            if (n && (!sel || n.matches(sel))) out.push(n);
        }
        return pushStack(this, dedupe(out));
    };
    fn.siblings = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var el = this[i]; if (!el || !el.parentElement) continue;
            for (var j = 0; j < el.parentElement.children.length; j++) {
                var c = el.parentElement.children[j];
                if (c !== el && (!sel || c.matches(sel))) out.push(c);
            }
        }
        return pushStack(this, dedupe(out));
    };
    fn.filter = function (sel) {
        var out = [];
        var fnSel = typeof sel === "function";
        for (var i = 0; i < this.length; i++) {
            var el = this[i];
            var ok = fnSel ? !!sel.call(el, i, el) : (el && el.matches && el.matches(sel));
            if (ok) out.push(el);
        }
        return pushStack(this, out);
    };
    fn.not = function (sel) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var el = this[i];
            var ok = el && el.matches && el.matches(sel);
            if (!ok) out.push(el);
        }
        return pushStack(this, out);
    };
    fn.is = function (sel) {
        for (var i = 0; i < this.length; i++) {
            if (this[i] && this[i].matches && this[i].matches(sel)) return true;
        }
        return false;
    };
    fn.add = function (other) {
        var merged = toArr(this);
        var o = other instanceof jQuery ? toArr(other) : (other && other.nodeType ? [other] : toArr(jQuery(other)));
        for (var i = 0; i < o.length; i++) merged.push(o[i]);
        return pushStack(this, dedupe(merged));
    };

    // ---- iteration ----
    fn.ready = function (cb) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function () { cb(jQuery); });
        } else {
            cb(jQuery);
        }
        return this;
    };
    fn.each = function (cb) {
        for (var i = 0; i < this.length; i++) {
            if (cb.call(this[i], i, this[i]) === false) break;
        }
        return this;
    };
    fn.map = function (cb) {
        var out = [];
        for (var i = 0; i < this.length; i++) {
            var v = cb.call(this[i], i, this[i]);
            if (v != null) out.push(v);
        }
        return out;
    };
    fn.get = function (i) {
        if (i == null) return toArr(this);
        return i < 0 ? this[this.length + i] : this[i];
    };

    // ---- classes ----
    fn.hasClass = function (c) {
        for (var i = 0; i < this.length; i++) if (this[i] && this[i].classList && this[i].classList.contains(c)) return true;
        return false;
    };
    fn.addClass = function (c) {
        var names = String(c).split(/\s+/);
        return this.each(function () {
            if (this.classList) for (var i = 0; i < names.length; i++) if (names[i]) this.classList.add(names[i]);
        });
    };
    fn.removeClass = function (c) {
        if (c == null) return this.each(function () { if (this.className != null) this.className = ""; });
        var names = String(c).split(/\s+/);
        return this.each(function () {
            if (this.classList) for (var i = 0; i < names.length; i++) if (names[i]) this.classList.remove(names[i]);
        });
    };
    fn.toggleClass = function (c, force) {
        var names = String(c).split(/\s+/);
        return this.each(function () {
            if (this.classList) for (var i = 0; i < names.length; i++) {
                if (names[i]) {
                    if (force === undefined) this.classList.toggle(names[i]);
                    else force ? this.classList.add(names[i]) : this.classList.remove(names[i]);
                }
            }
        });
    };

    // ---- attributes / properties / data ----
    fn.attr = function (k, v) {
        if (v === undefined) return this[0] && this[0].getAttribute ? this[0].getAttribute(k) : null;
        return this.each(function () { if (this.setAttribute) this.setAttribute(k, v); });
    };
    fn.removeAttr = function (k) {
        return this.each(function () { if (this.removeAttribute) this.removeAttribute(k); });
    };
    fn.prop = function (k, v) {
        if (v === undefined) return this[0] ? this[0][k] : undefined;
        return this.each(function () { this[k] = v; });
    };
    fn.data = function (k, v) {
        if (v === undefined) {
            if (k === undefined) {
                var all = {};
                if (this[0] && this[0].dataset) Object.assign(all, this[0].dataset);
                var store = this[0] && dataCache.get(this[0]);
                if (store) Object.assign(all, store);
                return all;
            }
            return this[0] ? getData(this[0], k) : undefined;
        }
        return this.each(function () { setData(this, k, v); });
    };
    fn.removeData = function (k) {
        return this.each(function () { removeData(this, k); });
    };
    fn.val = function (v) {
        if (v === undefined) return this[0] ? this[0].value : undefined;
        return this.each(function () { this.value = v == null ? "" : v; });
    };

    // ---- content ----
    fn.text = function (v) {
        if (v === undefined) {
            var s = "";
            for (var i = 0; i < this.length; i++) if (this[i]) s += this[i].textContent || "";
            return s;
        }
        return this.each(function () { this.textContent = v == null ? "" : v; });
    };
    fn.html = function (v) {
        if (v === undefined) return this[0] ? this[0].innerHTML : undefined;
        return this.each(function () { this.innerHTML = v == null ? "" : v; });
    };
    fn.empty = function () {
        return this.each(function () { while (this.firstChild) this.removeChild(this.firstChild); });
    };

    // ---- DOM insertion ----
    function domify(arg) {
        if (arg instanceof jQuery) return toArr(arg);
        if (typeof arg === "string" && arg.trim().charAt(0) === "<") return [createHtml(arg)];
        if (arg && arg.nodeType) return [arg];
        return toArr(arg);
    }
    fn.append = function (arg) {
        var nodes = domify(arg);
        return this.each(function () {
            for (var i = 0; i < nodes.length; i++) if (nodes[i]) this.appendChild(nodes[i].cloneNode ? nodes[i].cloneNode(true) : nodes[i]);
        });
    };
    fn.prepend = function (arg) {
        var nodes = domify(arg);
        return this.each(function () {
            for (var i = nodes.length - 1; i >= 0; i--) if (nodes[i]) this.insertBefore(nodes[i].cloneNode ? nodes[i].cloneNode(true) : nodes[i], this.firstChild);
        });
    };
    fn.appendTo = function (sel) {
        var targets = toArr(jQuery(sel));
        var nodes = toArr(this);
        targets.forEach(function (t) { nodes.forEach(function (n) { t.appendChild(n); }); });
        return this;
    };
    fn.insertBefore = function (sel) {
        var targets = toArr(jQuery(sel));
        var nodes = toArr(this);
        targets.forEach(function (t) {
            var ref = t.parentNode; if (!ref) return;
            nodes.forEach(function (n) { ref.insertBefore(n, t); });
        });
        return this;
    };
    fn.after = function (arg) {
        var nodes = domify(arg);
        return this.each(function () {
            var parent = this.parentNode; if (!parent) return;
            var ref = this.nextSibling;
            for (var i = 0; i < nodes.length; i++) if (nodes[i]) parent.insertBefore(nodes[i].cloneNode ? nodes[i].cloneNode(true) : nodes[i], ref);
        });
    };
    fn.remove = function () {
        return this.each(function () { if (this.parentNode) this.parentNode.removeChild(this); });
    };
    fn.clone = function () { return jQuery(toArr(this).map(function (n) { return n.cloneNode(true); })); };

    // ---- CSS / dimensions ----
    fn.css = function (k, v) {
        if (typeof k === "object") {
            return this.each(function () {
                for (var key in k) if (Object.prototype.hasOwnProperty.call(k, key)) this.style[camelProp(key)] = k[key];
            });
        }
        if (v === undefined) return this[0] ? (this[0].ownerDocument && this[0].ownerDocument.defaultView ? this[0].ownerDocument.defaultView.getComputedStyle(this[0]).getPropertyValue(k) : "") : undefined;
        return this.each(function () { this.style[camelProp(k)] = v; });
    };
    fn.width = function () { return this[0] ? this[0].getBoundingClientRect().width : null; };
    fn.height = function (v) {
        var el = this[0];
        if (el === window) return window.innerHeight;
        if (el === document) return document.documentElement.clientHeight;
        if (!el) return null;
        if (v === undefined) return el.offsetHeight;
        return this.each(function () { this.style.height = typeof v === "number" ? v + "px" : v; });
    };
    fn.innerHeight = function () { return this[0] ? this[0].clientHeight : null; };
    fn.outerHeight = function () { return this[0] ? this[0].offsetHeight : null; };
    fn.scrollTop = function (v) {
        var el = this[0];
        if (el === window || el === document) {
            if (v === undefined) return window.pageYOffset || document.documentElement.scrollTop;
            window.scrollTo(window.pageXOffset, v); return this;
        }
        if (v === undefined) return el ? el.scrollTop : 0;
        if (el) el.scrollTop = v;
        return this;
    };
    fn.offset = function () {
        if (!this[0]) return { top: 0, left: 0 };
        var r = this[0].getBoundingClientRect();
        return { top: r.top + (window.pageYOffset || document.documentElement.scrollTop), left: r.left + (window.pageXOffset || document.documentElement.scrollLeft) };
    };
    fn.position = function () {
        if (!this[0]) return { top: 0, left: 0 };
        return { top: this[0].offsetTop, left: this[0].offsetLeft };
    };

    // ---- events ----
    fn.on = function (types, selector, handler) {
        if (typeof selector === "function") { handler = selector; selector = null; }
        var typeList = String(types).split(/\s+/);
        return this.each(function () {
            var node = this;
            typeList.forEach(function (type) {
                if (!type) return;
                var wrapped;
                if (selector) {
                    wrapped = function (e) {
                        var t = e.target.closest(selector);
                        if (t) return handler.call(t, e);
                    };
                } else {
                    wrapped = function (e) { return handler.call(node, e); };
                }
                node.addEventListener(type, wrapped, false);
                recordEvent(node, type, wrapped);
            });
        });
    };
    fn.off = function (types) {
        var typeList = types ? String(types).split(/\s+/) : null;
        return this.each(function () {
            var m = eventsForOff.get(this);
            if (!m) return;
            var keys = typeList && typeList[0] ? typeList : Object.keys(m);
            var self = this;
            keys.forEach(function (type) {
                var arr = m[type];
                if (!arr) return;
                arr.forEach(function (w) { self.removeEventListener(type, w, false); });
                delete m[type];
            });
        });
    };
    fn.one = function (types, handler) {
        var self = this;
        function wrap(e) { handler.call(this, e); jQuery(this).off(types, wrap); }
        return this.on(types, wrap);
    };
    fn.trigger = function (type) {
        var ev = type instanceof Event ? type : new Event(type, { bubbles: true });
        return this.each(function () { this.dispatchEvent(ev); });
    };
    fn.triggerHandler = function (type, args) {
        if (!this[0]) return undefined;
        var m = eventsForOff.get(this[0]);
        if (!m || !m[type]) return undefined;
        var result;
        var ev = { type: type, target: this[0], preventDefault: function () {}, stopPropagation: function () {} };
        m[type].forEach(function (w) { result = w.call(this[0], Object.assign(ev, args || {})); }, this);
        return result;
    };

    // ---- animation (width tween only — used by animation-change-text.js) ----
    fn.animate = function (props, duration, easing, complete) {
        if (typeof easing === "function") { complete = easing; easing = null; }
        duration = duration || 400;
        return this.each(function () {
            var el = this;
            Object.keys(props).forEach(function (key) {
                var prop = camelProp(key);
                var start = parseFloat(el.style[prop] || getComputedStyle(el)[prop]) || 0;
                var end = parseFloat(props[key]);
                var unit = String(props[key]).replace(/[\d.\-]/g, "") || "px";
                var t0 = performance.now();
                function step(now) {
                    var p = Math.min(1, (now - t0) / duration);
                    el.style[prop] = (start + (end - start) * p) + unit;
                    if (p < 1) requestAnimationFrame(step);
                    else if (complete) complete.call(el);
                }
                requestAnimationFrame(step);
            });
        });
    };
    fn.stop = function () { return this; };

    // ---- form ----
    fn.serialize = function () {
        var form = this[0];
        if (!form || !form.elements) return "";
        var parts = [];
        for (var i = 0; i < form.elements.length; i++) {
            var f = form.elements[i];
            if (!f.name || f.disabled) continue;
            var t = (f.type || "").toLowerCase();
            if ((t === "checkbox" || t === "radio") && !f.checked) continue;
            parts.push(encodeURIComponent(f.name) + "=" + encodeURIComponent(f.value));
        }
        return parts.join("&");
    };

    // ---- native validation replacement for jquery-validate.js ----
    // Supports .validate({ submitHandler, rules, messages, errorClass, errorElement,
    // errorPlacement, success, invalidHandler, highlight, unhighlight }).
    // Renders inline error labels (class ".error", id "{name}-error", for="{name}")
    // like jquery-validate, so the site's existing .error CSS keeps working — NOT
    // browser bubbles. Re-validates each field on input/blur.
    fn.validate = function (opts) {
        opts = opts || {};
        var errorClass = opts.errorClass || "error";
        var errorElement = opts.errorElement || "label";
        var form = this[0];
        if (!form || form.tagName !== "FORM") return this;
        form.setAttribute("novalidate", "novalidate");

        function errorLabel(field) {
            var name = field.getAttribute("name") || field.getAttribute("id") || "";
            return name ? form.querySelector(errorElement + "." + errorClass + "#" + cssEsc(name) + "-error") : null;
        }
        function cssEsc(s) { return String(s).replace(/([!"#$%&'()*+,./:;<=>?@[\]^`{|}~])/g, "\\$1"); }

        function messageFor(field) {
            var name = field.getAttribute("name") || "";
            // explicit message override? messages: { name: "..." } or { name: { rule: "..." } }
            if (opts.messages && opts.messages[name]) {
                var m = opts.messages[name];
                if (typeof m === "string") return m;
            }
            return field.validationMessage || "Invalid value.";
        }

        function showError(field) {
            var msg = messageFor(field);
            var name = field.getAttribute("name") || field.getAttribute("id") || ("f" + Math.random().toString(36).slice(2));
            field.classList.add(errorClass);
            var label = errorLabel(field);
            if (!label) {
                label = document.createElement(errorElement);
                label.className = errorClass;
                label.setAttribute("id", name + "-error");
                label.setAttribute("for", name);
                if (opts.errorPlacement) opts.errorPlacement(label, field);
                else field.parentNode.insertBefore(label, field.nextSibling);
            }
            label.textContent = msg;
            if (opts.highlight) opts.highlight(field, errorClass);
        }
        function clearError(field) {
            field.classList.remove(errorClass);
            var label = errorLabel(field);
            if (label && label.parentNode) label.parentNode.removeChild(label);
            if (opts.unhighlight) opts.unhighlight(field, errorClass);
        }
        function validateField(field) {
            if (!field.willValidate) return true;
            var ok = field.checkValidity();
            if (ok) clearError(field);
            else showError(field);
            return ok;
        }

        // live re-validation (clears errors as user fixes them)
        form.addEventListener("blur", function (e) { if (e.target.willValidate) validateField(e.target); }, true);
        form.addEventListener("input", function (e) {
            if (!e.target.willValidate) return;
            // only clear on input (don't re-show until blur), matches jquery-validate feel
            if (e.target.classList.contains(errorClass) && e.target.checkValidity()) clearError(e.target);
        }, true);

        form.addEventListener("submit", function (e) {
            // clear all prior error labels first
            Array.prototype.forEach.call(form.querySelectorAll(errorElement + "." + errorClass + "[id$='-error']"), function (el) {
                if (el.parentNode) el.parentNode.removeChild(el);
            });
            var fields = form.querySelectorAll("input, select, textarea");
            var firstInvalid = null;
            Array.prototype.forEach.call(fields, function (f) {
                if (!f.willValidate || f.disabled || f.type === "submit") return;
                if (!validateField(f) && !firstInvalid) firstInvalid = f;
            });
            if (firstInvalid) {
                e.preventDefault();
                if (opts.invalidHandler) opts.invalidHandler(e, { numberOfInvalids: function () { return form.querySelectorAll("." + errorClass).length; } });
                try { firstInvalid.focus({ preventScroll: false }); } catch (ex) { firstInvalid.focus(); }
                return;
            }
            e.preventDefault();
            if (typeof opts.submitHandler === "function") opts.submitHandler(form);
        });
        return this;
    };

    // plugins attached at load by their own files register on jQuery.fn
    // (countto.js sets jQuery.fn.countTo). Provide a no-op infiniteslide so the
    // (now CSS-driven) marquee guard never throws if the plugin file is absent.
    fn.infiniteslide = function () { return this; };

    // ---- static helpers ----
    jQuery.each = function (obj, cb) {
        if (obj && typeof obj.length === "number") {
            for (var i = 0; i < obj.length; i++) { if (cb.call(obj[i], i, obj[i]) === false) break; }
        } else if (obj && typeof obj === "object") {
            for (var k in obj) { if (Object.prototype.hasOwnProperty.call(obj, k)) { if (cb.call(obj[k], k, obj[k]) === false) break; } }
        }
        return obj;
    };
    jQuery.extend = function () {
        var args = Array.prototype.slice.call(arguments);
        var deep = false, target = args[0], i = 1;
        if (typeof target === "boolean") { deep = target; target = args[1]; i = 2; }
        target = target || {};
        function merge(dst, src) {
            for (var k in src) {
                if (!Object.prototype.hasOwnProperty.call(src, k)) continue;
                if (deep && src[k] && typeof src[k] === "object" && !(src[k] instanceof Array)) {
                    dst[k] = dst[k] || {};
                    merge(dst[k], src[k]);
                } else { dst[k] = src[k]; }
            }
        }
        for (; i < args.length; i++) if (args[i]) merge(target, args[i]);
        return target;
    };
    jQuery.isArray = function (v) { return Array.isArray(v); };
    jQuery.inArray = function (v, arr) { return Array.prototype.indexOf.call(arr || [], v); };
    jQuery.map = function (arr, cb) {
        var out = [], src = arr || [];
        for (var i = 0; i < src.length; i++) { var v = cb.call(src[i], src[i], i); if (v != null) out = out.concat(v); }
        return out;
    };
    jQuery.grep = function (arr, cb) { return (arr || []).filter(function (el, i) { return cb.call(el, i, el); }); };
    jQuery.makeArray = function (arr) { return toArr(arr); };
    jQuery.param = function (obj) {
        var parts = [];
        for (var k in obj) if (Object.prototype.hasOwnProperty.call(obj, k)) parts.push(encodeURIComponent(k) + "=" + encodeURIComponent(obj[k]));
        return parts.join("&");
    };

    // ---- $.ajax (XHR-based; used by the contact form) ----
    jQuery.ajax = function (opts) {
        opts = opts || {};
        var type = (opts.type || "GET").toUpperCase();
        var url = opts.url;
        var data = opts.data;
        var dataType = (opts.dataType || "").toLowerCase();
        var xhr = new XMLHttpRequest();
        if (type === "GET" && data) { url += (url.indexOf("?") === -1 ? "?" : "&") + (typeof data === "string" ? data : jQuery.param(data)); data = null; }
        xhr.open(type, url, true);
        if (type === "POST") xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        if (dataType === "json") xhr.setRequestHeader("Accept", "application/json");
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            var isSuccess = xhr.status >= 200 && xhr.status < 300;
            if (isSuccess && opts.success) {
                var resp = xhr.responseText;
                if (dataType === "json") { try { resp = JSON.parse(resp); } catch (e) { if (opts.error) return opts.error(xhr, "parsererror"); } }
                opts.success(resp, xhr.statusText);
            } else if (!isSuccess && opts.error) {
                opts.error(xhr, xhr.statusText);
            }
            if (opts.complete) opts.complete(xhr, isSuccess ? "success" : "error");
        };
        if (opts.beforeSend) opts.beforeSend(xhr);
        xhr.send(data && typeof data !== "string" ? jQuery.param(data) : data || null);
        return xhr;
    };
    jQuery.ajaxSetup = function () {};

    // ---- helpers ----
    function dedupe(arr) {
        var out = [], seen = [];
        for (var i = 0; i < arr.length; i++) {
            if (arr[i] == null) continue;
            if (seen.indexOf(arr[i]) === -1) { seen.push(arr[i]); out.push(arr[i]); }
        }
        return out;
    }

    global.jQuery = global.$ = jQuery;
})(typeof window !== "undefined" ? window : this);
