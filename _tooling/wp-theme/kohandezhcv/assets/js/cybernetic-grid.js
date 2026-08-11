/* Cybernetic grid shader background for the portfolio hero.
   Vanilla WebGL port of the "CyberneticGridShader" React/three.js component:
   no dependencies, transparent canvas, recolored to the page accent (#70e1b5)
   so the page's existing color harmony is preserved. Renders behind the hero
   text (z-index -1 inside .portfolio-hero, pointer-events none). */
(function () {
  "use strict";

  var host = document.querySelector(".portfolio-hero");
  if (!host) return;

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  var canvas = document.createElement("canvas");
  canvas.className = "hero-shader";
  canvas.setAttribute("aria-hidden", "true");
  var gl = canvas.getContext("webgl", {
    alpha: true,
    antialias: true,
    premultipliedAlpha: true,
    powerPreference: "low-power"
  });
  if (!gl) return;
  host.insertBefore(canvas, host.firstChild);

  var VERT = "attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}";

  var FRAG = [
    "precision highp float;",
    "uniform vec2 iResolution;",
    "uniform float iTime;",
    "uniform vec2 iMouse;",
    "float random(vec2 st){return fract(sin(dot(st.xy,vec2(12.9898,78.233)))*43758.5453123);}",
    "void main(){",
    "  vec2 uv=(gl_FragCoord.xy-0.5*iResolution.xy)/iResolution.y;",
    "  vec2 mouse=(iMouse-0.5*iResolution.xy)/iResolution.y;",
    "  float t=iTime*0.2;",
    "  float mouseDist=length(uv-mouse);",
    "  float warp=sin(mouseDist*20.0-t*4.0)*0.1;",
    "  warp*=smoothstep(0.4,0.0,mouseDist);",
    "  uv+=warp;",
    "  vec2 gridUv=abs(fract(uv*10.0)-0.5);",
    "  float line=pow(1.0-min(gridUv.x,gridUv.y),50.0);",
    // Page accent #70e1b5 instead of the component's blue grid.
    "  vec3 gridColor=vec3(0.439,0.882,0.710);",
    "  vec3 color=gridColor*line*(0.5+sin(t*2.0)*0.2);",
    "  float energy=sin(uv.x*20.0+t*5.0)*sin(uv.y*20.0+t*3.0);",
    "  energy=smoothstep(0.8,1.0,energy);",
    // Soft green-white pulses instead of magenta, keeps the palette monochrome+green.
    "  color+=vec3(0.85,1.0,0.92)*energy*line*0.6;",
    "  float glow=smoothstep(0.16,0.0,mouseDist);",
    "  color+=gridColor*glow*0.35;",
    "  color+=random(uv+t*0.1)*0.03;",
    // Transparent background: alpha follows brightness (premultiplied).
    "  float a=clamp(max(color.r,max(color.g,color.b)),0.0,1.0);",
    "  gl_FragColor=vec4(color,a);",
    "}"
  ].join("\n");

  function compile(type, src) {
    var s = gl.createShader(type);
    gl.shaderSource(s, src);
    gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
      gl.deleteShader(s);
      return null;
    }
    return s;
  }

  var vs = compile(gl.VERTEX_SHADER, VERT);
  var fs = compile(gl.FRAGMENT_SHADER, FRAG);
  if (!vs || !fs) { canvas.remove(); return; }

  var prog = gl.createProgram();
  gl.attachShader(prog, vs);
  gl.attachShader(prog, fs);
  gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { canvas.remove(); return; }
  gl.useProgram(prog);

  var buf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  var loc = gl.getAttribLocation(prog, "p");
  gl.enableVertexAttribArray(loc);
  gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

  var uRes = gl.getUniformLocation(prog, "iResolution");
  var uTime = gl.getUniformLocation(prog, "iTime");
  var uMouse = gl.getUniformLocation(prog, "iMouse");

  // Software rasterizers (SwiftShader/llvmpipe — headless bots, GPU-less VMs)
  // burn main-thread CPU per frame; animating there wrecks responsiveness.
  var softwareGL = false;
  try {
    var dbg = gl.getExtension("WEBGL_debug_renderer_info");
    var renderer = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : (gl.getParameter(gl.RENDERER) || "");
    softwareGL = /swiftshader|llvmpipe|softpipe|software rasterizer|subzero/i.test(String(renderer));
  } catch (e) { /* keep animating if detection fails */ }

  var dpr = Math.min(window.devicePixelRatio || 1, 2);
  var mouseX = 0, mouseY = 0, haveMouse = false;

  function resize() {
    var w = host.clientWidth, h = host.clientHeight;
    canvas.width = Math.max(1, Math.round(w * dpr));
    canvas.height = Math.max(1, Math.round(h * dpr));
    gl.viewport(0, 0, canvas.width, canvas.height);
    gl.uniform2f(uRes, canvas.width, canvas.height);
    if (!haveMouse) { mouseX = canvas.width / 2; mouseY = canvas.height / 2; }
  }

  function onMove(e) {
    var r = canvas.getBoundingClientRect();
    haveMouse = true;
    mouseX = (e.clientX - r.left) * dpr;
    mouseY = canvas.height - (e.clientY - r.top) * dpr;
  }

  var start = performance.now();
  var raf = 0;
  var last = 0;
  var FRAME_MS = 1000 / 30; // 30fps cap — visually identical for a slow grid drift

  function draw(now) {
    gl.uniform1f(uTime, (now - start) / 1000);
    gl.uniform2f(uMouse, mouseX, mouseY);
    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
  }

  function loop(now) {
    raf = requestAnimationFrame(loop);
    if (now - last < FRAME_MS) return;
    last = now;
    draw(now);
  }

  function startLoop() {
    if (!raf) raf = requestAnimationFrame(loop);
  }

  function stopLoop() {
    if (raf) { cancelAnimationFrame(raf); raf = 0; }
  }

  window.addEventListener("resize", resize);
  window.addEventListener("mousemove", onMove, { passive: true });
  resize();

  var staticOnly = reduceMotion.matches || softwareGL;

  if (staticOnly) {
    draw(start); // single static frame, no animation
  } else {
    startLoop();
  }

  // Pause when the hero is off-screen or the tab is hidden.
  if ("IntersectionObserver" in window && !staticOnly) {
    new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        en.isIntersecting ? startLoop() : stopLoop();
      });
    }).observe(host);
  }
  document.addEventListener("visibilitychange", function () {
    if (staticOnly) return;
    document.hidden ? stopLoop() : startLoop();
  });
})();
