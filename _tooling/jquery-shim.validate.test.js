/* jquery.shim validate() functional test (jsdom).
 * Drives a real form through the shim's .validate() and asserts inline .error
 * labels appear/clear + submitHandler fires only when valid. Proves Fix A.
 * Run: NODE_PATH=<jsdom dir> node _tooling/jquery-shim.validate.test.js
 */
"use strict";
var fs = require("fs");
var path = require("path");
var { JSDOM } = require("jsdom");

var shimSrc = fs.readFileSync(path.resolve(__dirname, "../assets/js/jquery.shim.js"), "utf8");
var passed = 0, failed = 0;
function ok(name, cond) { if (cond) passed++; else { failed++; console.log("  ✗ FAIL:", name); } }

var dom = new JSDOM(
  "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body>" +
  "<form id='f'><input name='nm' required><input type='email' name='em' required>" +
  "<button type='submit'>go</button></form></body></html>",
  { runScripts: "outside-only", pretendToBeVisual: true }
);
var w = dom.window, d = w.document;
// load the shim into the window context (defines window.jQuery / window.$)
w.eval(shimSrc);
var $ = w.$;
ok("shim exposes window.$", typeof $ === "function");
ok("shim exposes $.fn.validate", typeof $.fn.validate === "function");

var form = d.getElementById("f");
var submitCount = 0;
$(form).validate({
  submitHandler: function (f) { submitCount++; }
});

function labels() { return d.querySelectorAll("label.error[id$='-error']").length; }
function fieldHasError(name) { return d.querySelector("[name='" + name + "']").classList.contains("error"); }

// --- Step 1: submit empty → must block + show inline .error labels (not browser bubbles) ---
form.dispatchEvent(new w.Event("submit", { bubbles: true, cancelable: true }));
ok("T1 empty submit → submitHandler NOT called", submitCount === 0);
ok("T2 inline .error labels created (2)", labels() === 2);
ok("T3 'nm' field has .error class", fieldHasError("nm"));
ok("T4 'em' field has .error class", fieldHasError("em"));
var nmLabel = d.getElementById("nm-error");
ok("T5 label has for=nm", nmLabel && nmLabel.getAttribute("for") === "nm");
ok("T6 label carries .error class", nmLabel && nmLabel.classList.contains("error"));
ok("T7 label has a non-empty message", nmLabel && nmLabel.textContent.trim().length > 0);

// --- Step 2: fix 'nm' → on input the error should clear (matches jquery-validate feel) ---
d.querySelector("[name='nm']").value = "Kohandezh";
d.querySelector("[name='nm']").dispatchEvent(new w.Event("input", { bubbles: true }));
ok("T8 after valid input on nm, its .error label is cleared", d.getElementById("nm-error") === null);
ok("T9 nm field lost .error class after valid input", !fieldHasError("nm"));
// 'em' still invalid
ok("T10 em still flagged (untouched)", fieldHasError("em"));

// --- Step 3: now fill valid email + submit → errors clear, submitHandler fires once ---
d.querySelector("[name='em']").value = "test@example.com";
d.querySelector("[name='em']").dispatchEvent(new w.Event("input", { bubbles: true }));
form.dispatchEvent(new w.Event("submit", { bubbles: true, cancelable: true }));
ok("T11 fully-valid submit → submitHandler called exactly once", submitCount === 1);
ok("T12 all inline .error labels cleared after valid submit", labels() === 0);
ok("T13 em field lost .error class after valid submit", !fieldHasError("em"));

// --- Step 4: break email again + blur → error re-appears (live re-validation) ---
d.querySelector("[name='em']").value = "not-an-email";
d.querySelector("[name='em']").dispatchEvent(new w.Event("blur", { bubbles: true }));
ok("T14 blur on invalid field re-shows its .error label", d.getElementById("em-error") !== null);

console.log("\n---- jquery.shim validate(): " + passed + " passed, " + failed + " failed ----");
process.exit(failed ? 1 : 0);
